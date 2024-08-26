<?php

declare(strict_types=1);

namespace Dot\Log\Writer;

use Dot\Log\Exception\InvalidArgumentException;
use Dot\Log\Exception\RuntimeException;
use Dot\Log\Filter\FilterInterface;
use Dot\Log\Filter\Priority;
use Dot\Log\Formatter\FormatterInterface;
use Dot\Log\Manager\FilterPluginManager;
use Dot\Log\Manager\FormatterPluginManager;
use ErrorException;
use Exception;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ErrorHandler;
use Traversable;

abstract class AbstractWriter implements WriterInterface
{
    protected FilterPluginManager $filterPlugins;

    protected FormatterPluginManager $formatterPlugins;

    protected array $filters = [];

    protected FormatterInterface $formatter;

    /**
     * Use Laminas\Stdlib\ErrorHandler to report errors during calls to write
     */
    protected bool $convertWriteErrorsToExceptions = true;

    /**
     * Error level passed to Laminas\Stdlib\ErrorHandler::start for errors reported during calls to write
     */
    protected int|bool $errorsToExceptionsConversionLevel = E_WARNING;

    /**
     * Constructor
     *
     * Set options for a writer. Accepted options are:
     * - filters: array of filters to add to this filter
     * - formatter: formatter for this writer
     */
    public function __construct(iterable $options = null)
    {
        if ($options instanceof Traversable) {
            $options = iterator_to_array($options);
        }

        if (is_array($options)) {
            if (isset($options['filter_manager'])) {
                $this->setFilterPluginManager($options['filter_manager']);
            }

            if (isset($options['formatter_manager'])) {
                $this->setFormatterPluginManager($options['formatter_manager']);
            }

            if (isset($options['filters'])) {
                $filters = $options['filters'];
                if (is_int($filters) || is_string($filters) || $filters instanceof FilterInterface) {
                    $this->addFilter($filters);
                } elseif (is_array($filters)) {
                    foreach ($filters as $filter) {
                        if (is_int($filter) || is_string($filter) || $filter instanceof FilterInterface) {
                            $this->addFilter($filter);
                        } elseif (is_array($filter)) {
                            if (! isset($filter['name'])) {
                                throw new InvalidArgumentException(
                                    'Options must contain a name for the filter'
                                );
                            }
                            $filterOptions = $filter['options'] ?? null;
                            $this->addFilter($filter['name'], $filterOptions);
                        }
                    }
                }
            }

            if (isset($options['formatter'])) {
                $formatter = $options['formatter'];
                if (is_string($formatter) || $formatter instanceof FormatterInterface) {
                    $this->setFormatter($formatter);
                } elseif (is_array($formatter)) {
                    if (! isset($formatter['name'])) {
                        throw new InvalidArgumentException('Options must contain a name for the formatter');
                    }
                    $formatterOptions = $formatter['options'] ?? null;
                    $this->setFormatter($formatter['name'], $formatterOptions);
                }
            }
        }
    }

    /**
     * Add a filter specific to this writer.
     */
    public function addFilter(int|string|FilterInterface $filter, ?array $options = null): WriterInterface
    {
        if (is_int($filter)) {
            $filter = new Priority($filter);
        }

        if (is_string($filter)) {
            $filter = $this->filterPlugin($filter, $options);
        }

        if (! $filter instanceof FilterInterface) {
            throw new InvalidArgumentException(sprintf(
                'Filter must implement %s\Filter\FilterInterface; received "%s"',
                __NAMESPACE__,
                is_object($filter) ? get_class($filter) : gettype($filter)
            ));
        }

        $this->filters[] = $filter;
        return $this;
    }

    public function getFilterPluginManager(): FilterPluginManager
    {
        if (null === $this->filterPlugins) {
            $this->setFilterPluginManager(new FilterPluginManager(new ServiceManager()));
        }
        return $this->filterPlugins;
    }

    public function setFilterPluginManager($plugins): static
    {
        if (is_string($plugins)) {
            $plugins = new $plugins();
        }
        if (! $plugins instanceof FilterPluginManager) {
            throw new InvalidArgumentException(sprintf(
                'Writer plugin manager must extend %s; received %s',
                FilterPluginManager::class,
                is_object($plugins) ? get_class($plugins) : gettype($plugins)
            ));
        }

        $this->filterPlugins = $plugins;
        return $this;
    }

    public function filterPlugin($name, ?array $options = null)
    {
        return $this->getFilterPluginManager()->get($name, $options);
    }

    public function getFormatterPluginManager(): FormatterPluginManager
    {
        if (null === $this->formatterPlugins) {
            $this->setFormatterPluginManager(new FormatterPluginManager(new ServiceManager()));
        }
        return $this->formatterPlugins;
    }

    public function setFormatterPluginManager($plugins): static
    {
        if (is_string($plugins)) {
            $plugins = new $plugins();
        }
        if (! $plugins instanceof FormatterPluginManager) {
            throw new InvalidArgumentException(
                sprintf(
                    'Writer plugin manager must extend %s; received %s',
                    FormatterPluginManager::class,
                    is_object($plugins) ? get_class($plugins) : gettype($plugins)
                )
            );
        }

        $this->formatterPlugins = $plugins;
        return $this;
    }

    public function formatterPlugin($name, ?array $options = null)
    {
        return $this->getFormatterPluginManager()->get($name, $options);
    }

    /**
     * Log a message to this writer.
     * @throws ErrorException
     */
    public function write(array $event): void
    {
        foreach ($this->filters as $filter) {
            if (! $filter->filter($event)) {
                return;
            }
        }

        $errorHandlerStarted = false;

        if ($this->convertWriteErrorsToExceptions && ! ErrorHandler::started()) {
            ErrorHandler::start($this->errorsToExceptionsConversionLevel);
            $errorHandlerStarted = true;
        }

        try {
            $this->doWrite($event);
        } catch (Exception $e) {
            if ($errorHandlerStarted) {
                ErrorHandler::stop();
            }
            throw $e;
        }

        if ($errorHandlerStarted) {
            $error = ErrorHandler::stop();
            if ($error) {
                throw new RuntimeException("Unable to write", 0, $error);
            }
        }
    }

    public function setFormatter(FormatterInterface|string $formatter, ?array $options = null): WriterInterface
    {
        if (is_string($formatter)) {
            $formatter = $this->formatterPlugin($formatter, $options);
        }

        if (! $formatter instanceof FormatterInterface) {
            throw new InvalidArgumentException(sprintf(
                'Formatter must implement %s\Formatter\FormatterInterface; received "%s"',
                __NAMESPACE__,
                is_object($formatter) ? get_class($formatter) : gettype($formatter)
            ));
        }

        $this->formatter = $formatter;
        return $this;
    }

    protected function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }

    protected function hasFormatter(): bool
    {
        return $this->formatter instanceof FormatterInterface;
    }

    public function setConvertWriteErrorsToExceptions(bool $convertErrors): void
    {
        $this->convertWriteErrorsToExceptions = $convertErrors;
    }

    /**
     * Perform shutdown activities such as closing open resources
     */
    public function shutdown()
    {
    }

    /**
     * Write a message to the log
     */
    abstract protected function doWrite(array $event): void;
}


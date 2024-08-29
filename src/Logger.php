<?php

declare(strict_types=1);

namespace Dot\Log;

use DateTime;
use Dot\Log\Exception\InvalidArgumentException;
use Dot\Log\Exception\RuntimeException;
use Dot\Log\Manager\ProcessorPluginManager;
use Dot\Log\Manager\WriterPluginManager;
use Dot\Log\Processor\ProcessorInterface;
use Dot\Log\Writer\WriterInterface;
use ErrorException;
use Exception;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\SplPriorityQueue;
use Traversable;

use function array_reverse;
use function count;
use function error_get_last;
use function error_reporting;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function register_shutdown_function;
use function restore_error_handler;
use function restore_exception_handler;
use function set_error_handler;
use function set_exception_handler;
use function sprintf;
use function var_export;

use const E_COMPILE_ERROR;
use const E_COMPILE_WARNING;
use const E_CORE_ERROR;
use const E_CORE_WARNING;
use const E_DEPRECATED;
use const E_ERROR;
use const E_NOTICE;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_STRICT;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;

class Logger implements LoggerInterface
{
    /**
     * @link http://tools.ietf.org/html/rfc3164
     *
     * @const int defined from the BSD Syslog message severities
     */
    public const EMERG  = 0;
    public const ALERT  = 1;
    public const CRIT   = 2;
    public const ERR    = 3;
    public const WARN   = 4;
    public const NOTICE = 5;
    public const INFO   = 6;
    public const DEBUG  = 7;

    /**
     * Map native PHP errors to priority
     */
    public static array $errorPriorityMap = [
        E_NOTICE            => self::NOTICE,
        E_USER_NOTICE       => self::NOTICE,
        E_WARNING           => self::WARN,
        E_CORE_WARNING      => self::WARN,
        E_USER_WARNING      => self::WARN,
        E_ERROR             => self::ERR,
        E_USER_ERROR        => self::ERR,
        E_CORE_ERROR        => self::ERR,
        E_RECOVERABLE_ERROR => self::ERR,
        E_PARSE             => self::ERR,
        E_COMPILE_ERROR     => self::ERR,
        E_COMPILE_WARNING   => self::ERR,
        E_STRICT            => self::DEBUG,
        E_DEPRECATED        => self::DEBUG,
        E_USER_DEPRECATED   => self::DEBUG,
    ];

    /**
     * Registered error handler
     */
    protected static bool $registeredErrorHandler = false;

    /**
     * Registered shutdown error handler
     */
    protected static bool $registeredFatalErrorShutdownFunction = false;

    /**
     * Registered exception handler
     */
    protected static bool $registeredExceptionHandler = false;

    /**
     * List of priority code => priority (short) name
     */
    protected array $priorities = [
        self::EMERG  => 'EMERG',
        self::ALERT  => 'ALERT',
        self::CRIT   => 'CRIT',
        self::ERR    => 'ERR',
        self::WARN   => 'WARN',
        self::NOTICE => 'NOTICE',
        self::INFO   => 'INFO',
        self::DEBUG  => 'DEBUG',
    ];

    protected SplPriorityQueue $writers;

    protected SplPriorityQueue $processors;

    protected ?WriterPluginManager $writerPlugins;

    protected ?ProcessorPluginManager $processorPlugins;

    /**
     * Constructor
     *
     * Set options for a logger. Accepted options are:
     * - writers: array of writers to add to this logger
     * - exceptionhandler: if true register this logger as exceptionhandler
     * - errorhandler: if true register this logger as errorhandler
     */
    public function __construct(?iterable $options = null)
    {
        $this->writers    = new SplPriorityQueue();
        $this->processors = new SplPriorityQueue();

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (! $options) {
            return;
        }

        // Inject writer plugin manager, if available
        if (
            isset($options['writer_plugin_manager'])
            && $options['writer_plugin_manager'] instanceof AbstractPluginManager
        ) {
            $this->setWriterPluginManager($options['writer_plugin_manager']);
        }

        // Inject processor plugin manager, if available
        if (
            isset($options['processor_plugin_manager'])
            && $options['processor_plugin_manager'] instanceof AbstractPluginManager
        ) {
            $this->setProcessorPluginManager($options['processor_plugin_manager']);
        }

        if (isset($options['writers']) && is_array($options['writers'])) {
            foreach ($options['writers'] as $writer) {
                if (! isset($writer['name'])) {
                    throw new InvalidArgumentException('Options must contain a name for the writer');
                }

                $priority      = $writer['priority'] ?? null;
                $writerOptions = $writer['options'] ?? null;

                $this->addWriter($writer['name'], $priority, $writerOptions);
            }
        }

        if (isset($options['processors']) && is_array($options['processors'])) {
            foreach ($options['processors'] as $processor) {
                if (! isset($processor['name'])) {
                    throw new InvalidArgumentException('Options must contain a name for the processor');
                }

                $priority         = $processor['priority'] ?? null;
                $processorOptions = $processor['options'] ?? null;

                $this->addProcessor($processor['name'], $priority, $processorOptions);
            }
        }

        if (isset($options['exceptionhandler']) && $options['exceptionhandler'] === true) {
            static::registerExceptionHandler($this);
        }

        if (isset($options['errorhandler']) && $options['errorhandler'] === true) {
            static::registerErrorHandler($this);
        }

        if (isset($options['fatal_error_shutdownfunction']) && $options['fatal_error_shutdownfunction'] === true) {
            static::registerFatalErrorShutdownFunction($this);
        }
    }

    /**
     * Shutdown all writers
     */
    public function __destruct()
    {
        foreach ($this->writers as $writer) {
            try {
                $writer->shutdown();
            } catch (Exception $e) {
            }
        }
    }

    public function getWriterPluginManager(): ?WriterPluginManager
    {
        if (null === $this->writerPlugins) {
            $this->setWriterPluginManager(new WriterPluginManager(new ServiceManager()));
        }
        return $this->writerPlugins;
    }

    public function setWriterPluginManager(WriterPluginManager $writerPlugins): static
    {
        $this->writerPlugins = $writerPlugins;
        return $this;
    }

    /**
     * Get writer instance
     *
     * @psalm-suppress InvalidReturnStatement
     */
    public function writerPlugin(string $name, ?array $options = null): WriterInterface
    {
        return $this->getWriterPluginManager()->get($name, $options);
    }

    /**
     * Add a writer to a logger
     */
    public function addWriter(WriterInterface|string $writer, int $priority = 1, ?array $options = null): static
    {
        if (is_string($writer)) {
            $writer = $this->writerPlugin($writer, $options);
        } elseif (! $writer instanceof Writer\WriterInterface) {
            throw new InvalidArgumentException(sprintf(
                'Writer must implement %s\Writer\WriterInterface; received "%s"',
                __NAMESPACE__,
                $writer::class
            ));
        }
        $this->writers->insert($writer, $priority);

        return $this;
    }

    public function getWriters(): SplPriorityQueue
    {
        return $this->writers;
    }

    public function setWriters(SplPriorityQueue $writers): static
    {
        foreach ($writers->toArray() as $writer) {
            if (! $writer instanceof Writer\WriterInterface) {
                throw new InvalidArgumentException(
                    'Writers must be a SplPriorityQueue of Laminas\Log\Writer'
                );
            }
        }
        $this->writers = $writers;
        return $this;
    }

    public function getProcessorPluginManager(): ?ProcessorPluginManager
    {
        if (null === $this->processorPlugins) {
            $this->setProcessorPluginManager(new ProcessorPluginManager(new ServiceManager()));
        }
        return $this->processorPlugins;
    }

    public function setProcessorPluginManager(string|ProcessorPluginManager $plugins): static
    {
        if (is_string($plugins)) {
            $plugins = new $plugins();
        }
        if (! $plugins instanceof ProcessorPluginManager) {
            throw new InvalidArgumentException(sprintf(
                'processor plugin manager must extend %s\ProcessorPluginManager; received %s',
                __NAMESPACE__,
                $plugins::class
            ));
        }

        $this->processorPlugins = $plugins;
        return $this;
    }

    /**
     * @psalm-suppress InvalidReturnStatement
     */
    public function processorPlugin(string $name, ?array $options = null): ProcessorInterface
    {
        return $this->getProcessorPluginManager()->get($name, $options);
    }

    public function addProcessor(
        ProcessorInterface|string $processor,
        int $priority = 1,
        ?array $options = null
    ): static {
        if (is_string($processor)) {
            $processor = $this->processorPlugin($processor, $options);
        } elseif (! $processor instanceof Processor\ProcessorInterface) {
            throw new InvalidArgumentException(sprintf(
                'Processor must implement Laminas\Log\ProcessorInterface; received "%s"',
                $processor::class
            ));
        }
        $this->processors->insert($processor, $priority);

        return $this;
    }

    public function getProcessors(): SplPriorityQueue
    {
        return $this->processors;
    }

    public function log(int $priority, mixed $message, iterable $extra = []): static
    {
        if (($priority < 0) || ($priority >= count($this->priorities))) {
            throw new InvalidArgumentException(sprintf(
                '$priority must be an integer >= 0 and < %d; received %s',
                count($this->priorities),
                var_export($priority, true)
            ));
        }
        if (is_object($message) && ! method_exists($message, '__toString')) {
            throw new InvalidArgumentException(
                '$message must implement magic __toString() method'
            );
        }

        if (! is_array($extra) && ! $extra instanceof Traversable) {
            throw new InvalidArgumentException(
                '$extra must be an array or implement Traversable'
            );
        } elseif ($extra instanceof Traversable) {
            $extra = ArrayUtils::iteratorToArray($extra);
        }

        if ($this->writers->count() === 0) {
            throw new RuntimeException('No log writer specified');
        }

        $timestamp = new DateTime();

        if (is_array($message)) {
            $message = var_export($message, true);
        }

        $event = [
            'timestamp'    => $timestamp,
            'priority'     => $priority,
            'priorityName' => $this->priorities[$priority],
            'message'      => (string) $message,
            'extra'        => $extra,
        ];

        /** @var ProcessorInterface $processor */
        foreach ($this->processors->toArray() as $processor) {
            $event = $processor->process($event);
        }

        /** @var WriterInterface $writer */
        foreach ($this->writers->toArray() as $writer) {
            $writer->write($event);
        }

        return $this;
    }

    public function emerg(string $message, iterable $extra = []): LoggerInterface
    {
        return $this->log(self::EMERG, $message, $extra);
    }

    public function alert(string $message, iterable $extra = []): LoggerInterface
    {
        return $this->log(self::ALERT, $message, $extra);
    }

    public function crit(string $message, iterable $extra = []): LoggerInterface
    {
        return $this->log(self::CRIT, $message, $extra);
    }

    public function err(string $message, iterable $extra = []): LoggerInterface
    {
        return $this->log(self::ERR, $message, $extra);
    }

    public function warn(string $message, iterable $extra = []): LoggerInterface
    {
        return $this->log(self::WARN, $message, $extra);
    }

    public function notice(string $message, iterable $extra = []): LoggerInterface
    {
        return $this->log(self::NOTICE, $message, $extra);
    }

    public function info(string $message, iterable $extra = []): LoggerInterface
    {
        return $this->log(self::INFO, $message, $extra);
    }

    public function debug(string $message, iterable $extra = []): LoggerInterface
    {
        return $this->log(self::DEBUG, $message, $extra);
    }

    /**
     * Register logging system as an error handler to log PHP errors
     *
     * @link http://www.php.net/manual/function.set-error-handler.php
     */
    public static function registerErrorHandler(Logger $logger, bool $continueNativeHandler = false): bool|null|callable
    {
        // Only register once per instance
        if (static::$registeredErrorHandler) {
            return false;
        }

        $errorPriorityMap = static::$errorPriorityMap;

        $previous = set_error_handler(
            function ($level, $message, $file, $line) use ($logger, $errorPriorityMap, $continueNativeHandler) {
                $iniLevel = error_reporting();

                if ($iniLevel & $level) {
                    if (isset($errorPriorityMap[$level])) {
                        $priority = $errorPriorityMap[$level];
                    } else {
                        $priority = Logger::INFO;
                    }
                    $logger->log($priority, $message, [
                        'errno' => $level,
                        'file'  => $file,
                        'line'  => $line,
                    ]);
                }

                return ! $continueNativeHandler;
            }
        );

        static::$registeredErrorHandler = true;
        return $previous;
    }

    public static function unregisterErrorHandler(): void
    {
        restore_error_handler();
        static::$registeredErrorHandler = false;
    }

    /**
     * Register a shutdown handler to log fatal errors
     *
     * @link http://www.php.net/manual/function.register-shutdown-function.php
     */
    public static function registerFatalErrorShutdownFunction(Logger $logger): bool
    {
        // Only register once per instance
        if (static::$registeredFatalErrorShutdownFunction) {
            return false;
        }

        $errorPriorityMap = static::$errorPriorityMap;

        register_shutdown_function(function () use ($logger, $errorPriorityMap) {
            $error = error_get_last();

            if (
                null === $error
                || ! in_array(
                    $error['type'],
                    [
                        E_ERROR,
                        E_PARSE,
                        E_CORE_ERROR,
                        E_CORE_WARNING,
                        E_COMPILE_ERROR,
                        E_COMPILE_WARNING,
                    ],
                    true
                )
            ) {
                return;
            }

            $logger->log(
                $errorPriorityMap[$error['type']],
                $error['message'],
                [
                    'file' => $error['file'],
                    'line' => $error['line'],
                ]
            );
        });

        static::$registeredFatalErrorShutdownFunction = true;

        return true;
    }

    /**
     * Register logging system as an exception handler to log PHP exceptions
     *
     * @link http://www.php.net/manual/en/function.set-exception-handler.php
     */
    public static function registerExceptionHandler(Logger $logger): bool
    {
        // Only register once per instance
        if (static::$registeredExceptionHandler) {
            return false;
        }

        $errorPriorityMap = static::$errorPriorityMap;

        set_exception_handler(function ($exception) use ($logger, $errorPriorityMap) {
            $logMessages = [];

            do {
                $priority = Logger::ERR;
                if ($exception instanceof ErrorException && isset($errorPriorityMap[$exception->getSeverity()])) {
                    $priority = $errorPriorityMap[$exception->getSeverity()];
                }

                $extra = [
                    'file'  => $exception->getFile(),
                    'line'  => $exception->getLine(),
                    'trace' => $exception->getTrace(),
                ];

                $logMessages[] = [
                    'priority' => $priority,
                    'message'  => $exception->getMessage(),
                    'extra'    => $extra,
                ];
                $exception     = $exception->getPrevious();
            } while ($exception);

            foreach (array_reverse($logMessages) as $logMessage) {
                $logger->log($logMessage['priority'], $logMessage['message'], $logMessage['extra']);
            }
        });

        static::$registeredExceptionHandler = true;
        return true;
    }

    public static function unregisterExceptionHandler(): void
    {
        restore_exception_handler();
        static::$registeredExceptionHandler = false;
    }
}

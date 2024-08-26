<?php

declare(strict_types=1);

namespace Dot\Log\Processor;

class Backtrace implements ProcessorInterface
{
    /**
     * Maximum stack level of backtrace (PHP > 5.4.0)
     */
    protected int $traceLimit = 10;

    /**
     * Classes within these namespaces in the stack are ignored
     */
    protected array $ignoredNamespaces = ['Laminas\\Log'];

    /**
     * Set options for a backtrace processor. Accepted options are:
     * - ignoredNamespaces: array of namespaces to be excluded from the logged backtrace
     */
    public function __construct(?array $options = null)
    {
        if (! empty($options['ignoredNamespaces'])) {
            $this->ignoredNamespaces = array_merge($this->ignoredNamespaces, (array) $options['ignoredNamespaces']);
        }
    }

    /**
     * Adds the origin of the log() call to the event extras
     */
    public function process(array $event): array
    {
        $trace = $this->getBacktrace();

        array_shift($trace); // ignore $this->getBacktrace();
        array_shift($trace); // ignore $this->process()

        $i = 0;
        while (
            isset($trace[$i]['class'])
            && $this->shouldIgnoreFrame($trace[$i]['class'])
        ) {
            $i++;
        }

        $origin = [
            'file'     => $trace[$i - 1]['file'] ?? null,
            'line'     => $trace[$i - 1]['line'] ?? null,
            'class'    => $trace[$i]['class'] ?? null,
            'function' => $trace[$i]['function'] ?? null,
        ];

        $extra = $origin;
        if (isset($event['extra'])) {
            $extra = array_merge($origin, $event['extra']);
        }
        $event['extra'] = $extra;

        return $event;
    }

    public function getIgnoredNamespaces(): array
    {
        return $this->ignoredNamespaces;
    }

    /**
     * Provide backtrace as slim as possible
     */
    protected function getBacktrace(): array
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->traceLimit);
    }

    /**
     * Determine whether the current frame in the backtrace should be ignored based on the class name
     */
    protected function shouldIgnoreFrame($class): bool
    {
        foreach ($this->ignoredNamespaces as $ignoredNamespace) {
            if (false !== strpos($class, $ignoredNamespace)) {
                return true;
            }
        }

        return false;
    }
}


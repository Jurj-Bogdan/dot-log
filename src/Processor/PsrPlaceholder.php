<?php

declare(strict_types=1);

namespace Dot\Log\Processor;

class PsrPlaceholder implements ProcessorInterface
{
    public function process(array $event): array
    {
        if (false === strpos($event['message'], '{')) {
            return $event;
        }

        $replacements = [];
        foreach ($event['extra'] as $key => $val) {
            if (
                $val === null
                || is_scalar($val)
                || (is_object($val) && method_exists($val, "__toString"))
            ) {
                $replacements['{' . $key . '}'] = $val;
                continue;
            }

            if (is_object($val)) {
                $replacements['{' . $key . '}'] = '[object ' . get_class($val) . ']';
                continue;
            }

            $replacements['{' . $key . '}'] = '[' . gettype($val) . ']';
        }

        $event['message'] = strtr($event['message'], $replacements);
        return $event;
    }
}


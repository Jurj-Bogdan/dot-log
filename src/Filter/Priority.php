<?php

declare(strict_types=1);

namespace Dot\Log\Filter;

use Dot\Log\Exception\InvalidArgumentException;
use Traversable;

class Priority implements FilterInterface
{
    protected int $priority;

    protected string $operator;

    /**
     * Filter logging by $priority. By default, it will accept any log
     * event whose priority value is less than or equal to $priority.
     */
    public function __construct($priority, $operator = null)
    {
        if ($priority instanceof Traversable) {
            $priority = iterator_to_array($priority);
        }
        if (is_array($priority)) {
            $operator = $priority['operator'] ?? null;
            $priority = $priority['priority'] ?? null;
        }
        if (! is_int($priority) && ! ctype_digit($priority)) {
            throw new InvalidArgumentException(sprintf(
                'Priority must be a number, received "%s"',
                gettype($priority)
            ));
        }

        $this->priority = (int) $priority;
        $this->operator = $operator ?? '<=';
    }

    /**
     * Returns TRUE to accept the message, FALSE to block it.
     */
    public function filter(array $event): bool|int
    {
        return version_compare((string) $event['priority'], (string) $this->priority, $this->operator);
    }
}

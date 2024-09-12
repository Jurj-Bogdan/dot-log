<?php

declare(strict_types=1);

namespace DotTest\Log;

use Dot\Log\Filter\FilterInterface;

class MockFilter implements FilterInterface
{
    /**
     * array of log events
     */
    public array $events = [];

    /**
     * Returns TRUE to accept the message
     */
    public function filter(array $event): bool
    {
        $this->events[] = $event;
        return true;
    }
}

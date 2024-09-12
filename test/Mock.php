<?php

declare(strict_types=1);

namespace DotTest\Log;

use Dot\Log\Writer\AbstractWriter;

class Mock extends AbstractWriter
{
    /**
     * array of log events
     */
    public array $events = [];

    /**
     * shutdown called?
     */
    public bool $shutdown = false;

    /**
     * Write a message to the log.
     */
    public function doWrite(array $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Record shutdown
     */
    public function shutdown(): void
    {
        $this->shutdown = true;
    }
}

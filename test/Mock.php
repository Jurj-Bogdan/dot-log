<?php

declare(strict_types=1);

namespace DotTest\Log;

use Dot\Log\Writer\AbstractWriter;

class Mock extends AbstractWriter
{
    /**
     * array of log events
     *
     * @var array
     */
    public $events = [];

    /**
     * shutdown called?
     *
     * @var bool
     */
    public $shutdown = false;

    /**
     * Write a message to the log.
     */
    public function doWrite(array $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Record shutdown
     *
     * @return void
     */
    public function shutdown()
    {
        $this->shutdown = true;
    }
}

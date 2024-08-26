<?php

declare(strict_types=1);

namespace Dot\Log\Writer;

class Noop extends AbstractWriter
{
    /**
     * Write a message to the log.
     */
    protected function doWrite(array $event): void
    {
    }
}

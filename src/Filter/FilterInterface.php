<?php

declare(strict_types=1);

namespace Dot\Log\Filter;

interface FilterInterface
{
    /**
     * Returns TRUE to accept the message, FALSE to block it.
     */
    public function filter(array $event);
}

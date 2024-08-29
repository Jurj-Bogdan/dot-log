<?php

declare(strict_types=1);

namespace Dot\Log;

interface LoggerInterface
{
    public function emerg(string $message, iterable $extra = []): LoggerInterface;

    public function alert(string $message, iterable $extra = []): LoggerInterface;

    public function crit(string $message, iterable $extra = []): LoggerInterface;

    public function err(string $message, iterable $extra = []): LoggerInterface;

    public function warn(string $message, iterable $extra = []): LoggerInterface;

    public function notice(string $message, iterable $extra = []): LoggerInterface;

    public function info(string $message, iterable $extra = []): LoggerInterface;

    public function debug(string $message, iterable $extra = []): LoggerInterface;
}

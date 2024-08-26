<?php

declare(strict_types=1);

namespace Dot\Log\Manager;

use Dot\Log\Factory\WriterFactory;
use Dot\Log\Writer\Noop;
use Dot\Log\Writer\Stream;
use Dot\Log\Writer\WriterInterface;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;

class WriterPluginManager extends AbstractPluginManager
{
    protected $aliases = [
        'noop'           => Noop::class,
        'stream'         => Stream::class,

        // The following are for backwards compatibility only; users
        // should update their code to use the noop writer instead.
        'null'                 => Noop::class,
        'laminaslogwriternull' => Noop::class,
    ];

    protected $factories = [
        Noop::class           => WriterFactory::class,
        Stream::class         => WriterFactory::class,
    ];

    protected $instanceOf = WriterInterface::class;

    /**
     * Allow many writers of the same type
     */
    protected $sharedByDefault = false;

    /**
     * Validate the plugin is of the expected type.
     *
     * Validates against `$instanceOf`.
     */
    public function validate(mixed $instance): void
    {
        if (! $instance instanceof $this->instanceOf) {
            throw new InvalidServiceException(sprintf(
                '%s can only create instances of %s; %s is invalid',
                static::class,
                $this->instanceOf,
                is_object($instance) ? get_class($instance) : gettype($instance)
            ));
        }
    }
}


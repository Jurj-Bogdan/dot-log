<?php

declare(strict_types=1);

namespace Dot\Log\Manager;

use Dot\Log\Formatter\FormatterInterface;
use Dot\Log\Formatter\Simple;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\InvokableFactory;

class FormatterPluginManager extends AbstractPluginManager
{
    protected $aliases = [
        'simple'           => Simple::class,
    ];

    protected $factories = [
        Simple::class           => InvokableFactory::class,
    ];

    protected $instanceOf = FormatterInterface::class;

    /**
     * Allow many formatters of the same type
     */
    protected $sharedByDefault = false;

    /**
     * Validate the plugin is of the expected type.
     *
     * Validates against `$instanceOf`.
     */
    public function validate($instance): void
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

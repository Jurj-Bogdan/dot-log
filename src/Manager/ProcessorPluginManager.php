<?php

declare(strict_types=1);

namespace Dot\Log\Manager;

use Dot\Log\Processor\Backtrace;
use Dot\Log\Processor\ProcessorInterface;
use Dot\Log\Processor\PsrPlaceholder;
use Dot\Log\Processor\ReferenceId;
use Dot\Log\Processor\RequestId;
use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\InvokableFactory;

class ProcessorPluginManager extends AbstractPluginManager
{
    protected $aliases = [
        'backtrace'      => Backtrace::class,
        'psrplaceholder' => PsrPlaceholder::class,
        'referenceid'    => ReferenceId::class,
        'requestid'      => RequestId::class,
    ];

    protected $factories = [
        Backtrace::class      => InvokableFactory::class,
        PsrPlaceholder::class => InvokableFactory::class,
        ReferenceId::class    => InvokableFactory::class,
        RequestId::class      => InvokableFactory::class,
    ];

    protected $instanceOf = ProcessorInterface::class;

    /**
     * Allow many processors of the same type
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


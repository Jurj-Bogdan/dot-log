<?php

declare(strict_types=1);

namespace Dot\Log\Factory;

use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

class WriterFactory implements FactoryInterface
{
    /**
     * Options to pass to the constructor if any.
     */
    private null|array $creationOptions;

    public function __construct(?array $creationOptions = null)
    {
        if (is_array($creationOptions)) {
            $this->setCreationOptions($creationOptions);
        }
    }

    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): object
    {
        $options = (array) $options;

        $options = $this->populateOptions($options, $container, 'filter_manager', 'LogFilterManager');
        $options = $this->populateOptions($options, $container, 'formatter_manager', 'LogFormatterManager');

        return new $requestedName($options);
    }

    /**
     * Populates the options array with the correct container value.
     */
    private function populateOptions(
        array $options,
        ContainerInterface $container,
        string $name,
        string $defaultService
    ): array
    {
        if (isset($options[$name]) && is_string($options[$name])) {
            $options[$name] = $container->get($options[$name]);
            return $options;
        }

        if (! isset($options[$name]) && $container->has($defaultService)) {
            $options[$name] = $container->get($defaultService);
            return $options;
        }

        return $options;
    }

    /**
     * Create an instance of the named service.
     *
     * First, it checks if `$canonicalName` resolves to a class, and, if so, uses
     * that value to proxy to `__invoke()`.
     *
     * Next, if `$requestedName` is non-empty and resolves to a class, this
     * method uses that value to proxy to `__invoke()`.
     *
     * Finally, if the above each fail, it raises an exception.
     *
     * The approach above is performed as version 2 has two distinct behaviors
     * under which factories are invoked:
     *
     * - If an alias was used, $canonicalName is the resolved name, and
     *   $requestedName is the service name requested, in which case $canonicalName
     *   is likely the qualified class name;
     * - Otherwise, $canonicalName is the normalized name, and $requestedName
     *   is the original service name requested (typically the qualified class name).
     *
     * @throws ContainerExceptionInterface
     */
    public function createService(
        ServiceLocatorInterface $serviceLocator,
        ?string $canonicalName = null,
        ?string $requestedName = null
    ): object
    {
        if (is_string($canonicalName) && class_exists($canonicalName)) {
            return $this($serviceLocator->getServiceLocator(), $canonicalName, $this->creationOptions);
        }

        if (is_string($requestedName) && class_exists($requestedName)) {
            return $this($serviceLocator->getServiceLocator(), $requestedName, $this->creationOptions);
        }

        throw new InvalidServiceException(sprintf(
            '%s requires that the requested name is provided on invocation; '
            . 'please update your tests or consuming container',
            self::class
        ));
    }

    public function setCreationOptions(array $creationOptions): void
    {
        $this->creationOptions = $creationOptions;
    }
}

<?php

declare(strict_types=1);

namespace Codeception\Lib\Connector;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

use function function_exists;
use function is_array;
use function is_object;
use function property_exists;

/**
 * @property KernelInterface $kernel
 */
class Symfony extends HttpKernelBrowser
{
    private ContainerInterface $container;
    private bool $hasPerformedRequest = false;

    public function __construct(
        HttpKernelInterface $kernel,
        /** @var array<non-empty-string, object> */
        public array $persistentServices = [],
        private bool $reboot = true
    ) {
        parent::__construct($kernel);
        $this->followRedirects();
        $this->container = $this->resolveContainer();
        $this->rebootKernel();
    }

    protected function doRequest(object $request): Response
    {
        if ($this->reboot) {
            $this->hasPerformedRequest ? $this->rebootKernel() : $this->hasPerformedRequest = true;
        }

        return parent::doRequest($request);
    }

    /**
     * Reboots the kernel.
     *
     * Services from the list of persistent services
     * are updated from service container before kernel shutdown
     * and injected into newly initialized container after kernel boot.
     */
    public function rebootKernel(): void
    {
        // Optimized: Single pass to update persistent services
        // Eliminates double lookup (has + get) with direct get + instanceof check
        foreach ($this->persistentServices as $name => $_) {
            $service = $this->container->get($name);
            if (is_object($service)) {
                $this->persistentServices[$name] = $service;
            }
        }

        $this->persistDoctrineConnections();

        if ($this->kernel instanceof Kernel) {
            $this->kernel->shutdown();
            $this->kernel->boot();
        }

        $this->container = $this->resolveContainer();

        // Optimized: Pre-filter settable services to avoid exception overhead
        foreach ($this->persistentServices as $name => $service) {
            // Only attempt to set if container allows it (avoid exception in hot path)
            if ($this->container->has($name)) {
                try {
                    $this->container->set($name, $service);
                } catch (InvalidArgumentException $e) {
                    if (function_exists('codecept_debug')) {
                        codecept_debug("[Symfony] Can't set persistent service {$name}: {$e->getMessage()}");
                    }
                }
            }
        }

        $this->getProfiler()?->enable();
    }

    private function resolveContainer(): ContainerInterface
    {
        $container = $this->kernel->getContainer();

        /** @var ContainerInterface $testContainer */
        $testContainer = $container->has('test.service_container') ? $container->get('test.service_container') : $container;

        return $testContainer;
    }

    private function getProfiler(): ?Profiler
    {
        if (!$this->container->has('profiler')) {
            return null;
        }

        $profiler = $this->container->get('profiler');

        return $profiler instanceof Profiler ? $profiler : null;
    }

    /**
     * Optimized: Direct property manipulation instead of closure overhead
     * Uses Reflection only once and caches the accessor
     */
    private function persistDoctrineConnections(): void
    {
        static $parametersProperty = null;

        $container = $this->kernel->getContainer();

        // One-time reflection setup, then direct access
        if ($parametersProperty === null && property_exists($container, 'parameters')) {
            $parametersProperty = new \ReflectionProperty($container, 'parameters');
            $parametersProperty->setAccessible(true);
        }

        if ($parametersProperty !== null) {
            $parameters = $parametersProperty->getValue($container);
            if (is_array($parameters) && isset($parameters['doctrine.connections'])) {
                unset($parameters['doctrine.connections']);
                $parametersProperty->setValue($container, $parameters);
            }
        }
    }
}

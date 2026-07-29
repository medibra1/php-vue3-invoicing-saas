<?php

declare(strict_types=1);

namespace App\Core\Container;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * Dependency injection container (implements PSR-11).
 *
 * Provides auto-wiring: when a class has no explicit binding, the
 * container inspects its constructor via reflection and recursively
 * resolves each typed dependency. This avoids having to manually
 * declare every service (as you would with a YAML config file in
 * Symfony, for example).
 *
 * Three registration modes:
 * - bind()      : a new instance is created on every resolution.
 * - singleton() : a single instance is created once and reused.
 * - instance()  : register an already-built object (useful to inject
 *                 the current tenant resolved by a middleware, for
 *                 example).
 */
class Container implements ContainerInterface
{
    /** @var array<string, callable(Container): object> Registered factories */
    private array $bindings = [];

    /** @var array<string, object> Already built instances (cache) */
    private array $instances = [];

    /** @var array<string, true> Identifiers that must be cached after resolution */
    private array $singletons = [];

    public function __construct()
    {
        // So any class can type-hint Container in its constructor and get
        // *this* shared instance via auto-wiring, instead of a fresh,
        // disconnected one (autowire(Container::class) would otherwise
        // build an empty container with none of the app's bindings).
        $this->instances[self::class] = $this;
    }

    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $this->normalize($concrete);
    }

    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $this->normalize($concrete);
        $this->singletons[$abstract] = true;
    }

    /**
     * Registers an already-instantiated object. Any later resolution
     * of $abstract will return this exact object, bypassing auto-wiring.
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id])
            || isset($this->instances[$id])
            || class_exists($id);
    }

    /**
     * Resolves and returns an instance for the given identifier.
     *
     * @throws RuntimeException if the identifier cannot be resolved.
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // No explicit binding -> fall back to auto-wiring via reflection.
        $factory = $this->bindings[$id] ?? fn (Container $c) => $c->autowire($id);
        $object = $factory($this);

        if (isset($this->singletons[$id])) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    /**
     * Instantiates the target controller via the container, then calls
     * the requested method, injecting:
     *   1. route parameters (e.g. {id} -> $routeParams['id']);
     *   2. service dependencies resolved by the container.
     *
     * This is the entry point used by the Router/Kernel to dispatch a
     * request to a controller.
     *
     * @param array{0: class-string, 1: string} $handler       [Controller::class, 'method']
     * @param array<string, string>             $routeParams   parameters extracted from the URL
     */
    public function call(array $handler, array $routeParams = []): mixed
    {
        [$class, $method] = $handler;
        $instance = $this->get($class);

        $reflectionMethod = new ReflectionMethod($instance, $method);
        $args = $this->resolveParameters($reflectionMethod->getParameters(), $routeParams);

        return $instance->{$method}(...$args);
    }

    /**
     * Like get(), but overrides specific constructor parameters by name
     * instead of relying purely on auto-wiring. Used by
     * MiddlewarePipeline for middleware that need per-route
     * configuration — e.g. PermissionMiddleware needs to know *which*
     * permission a given route requires, which a plain class-string
     * middleware entry has no way to carry.
     *
     * Bypasses bind()/singleton() registrations on purpose: a
     * parameterized middleware is built fresh per route, never shared.
     *
     * @param array<string, mixed> $parameters Constructor parameter name => value
     */
    public function makeWith(string $class, array $parameters): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        return $reflection->newInstanceArgs($this->resolveParameters($constructor->getParameters(), $parameters));
    }

    /**
     * Builds an instance of $class by recursively resolving the typed
     * dependencies of its constructor.
     */
    private function autowire(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve [{$class}]: class not found.");
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = $this->resolveParameters($constructor->getParameters());

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolves a list of ReflectionParameter into concrete values.
     *
     * Priority order for each parameter:
     *   1. present in $explicitParams — a URL parameter (call()) or a
     *      caller-supplied override (makeWith());
     *   2. type-hinted to a class/interface -> resolved via the container;
     *   3. the parameter's default value, if any.
     * Otherwise, an exception is thrown: better to fail early and
     * explicitly than to silently inject `null`.
     *
     * @param ReflectionParameter[] $parameters
     * @param array<string, mixed>  $explicitParams
     * @return array<int, mixed>
     */
    private function resolveParameters(array $parameters, array $explicitParams = []): array
    {
        $resolved = [];

        foreach ($parameters as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $explicitParams)) {
                $resolved[] = $explicitParams[$name];
                continue;
            }

            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $resolved[] = $this->get($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $resolved[] = $param->getDefaultValue();
                continue;
            }

            throw new RuntimeException(
                "Cannot resolve parameter [{$name}]: "
                . "no route value, no injectable type, no default value."
            );
        }

        return $resolved;
    }

    /**
     * Normalizes a binding into a callable(Container): object factory.
     * A plain class name becomes a factory that delegates to auto-wiring.
     */
    private function normalize(callable|string $concrete): callable
    {
        if (is_string($concrete)) {
            return fn (Container $c) => $c->autowire($concrete);
        }

        return $concrete;
    }
}

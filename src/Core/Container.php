<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Core;

/**
 * Lightweight Dependency Container for Arma Panel.
 */
class Container {

    /**
     * Stored service instances.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Stored factory definitions.
     *
     * @var array<string, callable>
     */
    private array $factories = [];

    /**
     * Bind a shared instance into the container.
     *
     * @param string $id
     * @param mixed $instance
     */
    public function instance(string $id, $instance): void {
        $this->instances[$id] = $instance;
    }

    /**
     * Bind a service factory into the container.
     *
     * @param string $id
     * @param callable $factory
     */
    public function bind(string $id, callable $factory): void {
        $this->factories[$id] = $factory;
    }

    /**
     * Bind a singleton factory into the container.
     *
     * @param string $id
     * @param callable $factory
     */
    public function singleton(string $id, callable $factory): void {
        $this->factories[$id] = function () use ($id, $factory) {
            static $object = null;
            if ($object === null) {
                $object = $factory($this);
            }
            return $object;
        };
    }

    /**
     * Get a service from the container.
     *
     * @param string $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function get(string $id) {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            $instance = ($this->factories[$id])($this);
            return $instance;
        }

        throw new \RuntimeException(sprintf('Service "%s" not found in Arma Panel container.', $id));
    }

    /**
     * Check if a service exists in the container.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }
}

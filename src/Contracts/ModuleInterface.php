<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Contracts;

interface ModuleInterface {
    /**
     * Unique identifier for the module (e.g. 'authentication', 'tickets').
     */
    public function getId(): string;

    /**
     * User-friendly module title.
     */
    public function getTitle(): string;

    /**
     * Whether this module is mandatory and cannot be disabled.
     */
    public function isRequired(): bool;

    /**
     * Boot the module (register hooks, routes, listeners).
     */
    public function boot(): void;
}

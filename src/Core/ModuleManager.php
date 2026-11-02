<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Core;

use Arma\ArmaPanel\Contracts\ModuleInterface;

/**
 * Module Manager handling module registration, lifecycle, and toggle states.
 */
class ModuleManager {

    private SettingsManager $settings;

    /**
     * Registered modules.
     *
     * @var array<string, ModuleInterface>
     */
    private array $modules = [];

    public function __construct(SettingsManager $settings) {
        $this->settings = $settings;
    }

    /**
     * Register a module instance.
     *
     * @param ModuleInterface $module
     */
    public function register(ModuleInterface $module): void {
        $this->modules[$module->getId()] = $module;
    }

    /**
     * Boot all active modules.
     */
    public function bootAll(): void {
        foreach ($this->modules as $id => $module) {
            // If module is required or enabled in settings, boot it
            if ($module->isRequired() || $this->settings->isModuleEnabled($id)) {
                $module->boot();
            }
        }
    }

    /**
     * Check if a module is enabled.
     *
     * @param string $id
     * @return bool
     */
    public function isEnabled(string $id): bool {
        if (isset($this->modules[$id]) && $this->modules[$id]->isRequired()) {
            return true;
        }

        return $this->settings->isModuleEnabled($id);
    }

    /**
     * Get all registered modules.
     *
     * @return array<string, ModuleInterface>
     */
    public function getModules(): array {
        return $this->modules;
    }
}

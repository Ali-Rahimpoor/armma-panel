<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\CustomerPanel;

use Closure;

/**
 * Value object representing a sidebar navigation item.
 *
 * The route can be provided either as a plain string or as a lazy Closure.
 * Lazy routes are essential: navigation items are registered during
 * `plugins_loaded`, when WordPress has not created its rewrite object yet.
 * Calling get_permalink() that early fatals ("get_page_permastruct() on
 * null"), so URLs must only be resolved on demand (at render time).
 */
class NavigationItem {

    private string $id;
    private string $label;
    private string $iconSvg;
    private string|Closure $route;
    private ?string $module;
    private int $position;
    private ?string $badge;
    private string $badgeColor;

    public function __construct(
        string $id,
        string $label,
        string $iconSvg,
        string|Closure $route,
        ?string $module = null,
        int $position = 10,
        ?string $badge = null,
        string $badgeColor = 'danger'
    ) {
        $this->id         = $id;
        $this->label      = $label;
        $this->iconSvg    = $iconSvg;
        $this->route      = $route;
        $this->module     = $module;
        $this->position   = $position;
        $this->badge      = $badge;
        $this->badgeColor = $badgeColor;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getLabel(): string {
        return $this->label;
    }

    public function getIconSvg(): string {
        return $this->iconSvg;
    }

    /**
     * Resolve the route. Lazy closures are evaluated at most once and the
     * resolved URL is cached for the object lifetime.
     */
    public function getRoute(): string {
        if ($this->route instanceof Closure) {
            try {
                $resolved = ($this->route)();
            } catch (\Throwable $e) {
                $resolved = '';
            }

            $this->route = is_string($resolved) && $resolved !== '' ? $resolved : '/';

            return $this->route;
        }

        return $this->route;
    }

    public function getModule(): ?string {
        return $this->module;
    }

    public function getPosition(): int {
        return $this->position;
    }

    public function getBadge(): ?string {
        return $this->badge;
    }

    public function getBadgeColor(): string {
        return $this->badgeColor;
    }
}

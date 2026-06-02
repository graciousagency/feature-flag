<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Override;

/**
 * In-memory, per-process runtime overrides for feature flags.
 * Not shared across workers or requests.
 */
final class OverrideStore
{
    /** @var array<string, bool> */
    private array $overrides = [];

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->overrides);
    }

    public function get(string $name): bool
    {
        return $this->overrides[$name];
    }

    public function set(string $name, bool $enabled): void
    {
        $this->overrides[$name] = $enabled;
    }

    public function clear(string $name): void
    {
        unset($this->overrides[$name]);
    }
}

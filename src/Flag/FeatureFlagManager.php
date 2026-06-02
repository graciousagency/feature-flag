<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Flag;

use Gracious\FeatureFlagBundle\Exception\UnknownFeatureException;
use Gracious\FeatureFlagBundle\Override\OverrideStore;

final class FeatureFlagManager implements FeatureFlagManagerInterface
{
    /**
     * @param array<string, array{enabled: bool, description: string|null}> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly OverrideStore $overrides,
    ) {}

    #[\Override]
    public function isEnabled(string $name): bool
    {
        $this->assertKnown($name);

        if ($this->overrides->has($name)) {
            return $this->overrides->get($name);
        }

        return $this->config[$name]['enabled'];
    }

    #[\Override]
    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    #[\Override]
    public function get(string $name): Flag
    {
        $this->assertKnown($name);

        return new Flag($name, $this->isEnabled($name), $this->config[$name]['description']);
    }

    #[\Override]
    public function all(): array
    {
        $flags = [];
        foreach (array_keys($this->config) as $name) {
            $flags[$name] = $this->get($name);
        }

        return $flags;
    }

    #[\Override]
    public function enable(string $name): void
    {
        $this->assertKnown($name);
        $this->overrides->set($name, true);
    }

    #[\Override]
    public function disable(string $name): void
    {
        $this->assertKnown($name);
        $this->overrides->set($name, false);
    }

    #[\Override]
    public function reset(string $name): void
    {
        $this->assertKnown($name);
        $this->overrides->clear($name);
    }

    private function assertKnown(string $name): void
    {
        if (!$this->has($name)) {
            throw new UnknownFeatureException($name);
        }
    }
}

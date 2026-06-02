<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Flag;

interface FeatureFlagManagerInterface
{
    public function isEnabled(string $name): bool;

    public function has(string $name): bool;

    public function get(string $name): Flag;

    /**
     * @return array<string, Flag>
     */
    public function all(): array;

    public function enable(string $name): void;

    public function disable(string $name): void;

    public function reset(string $name): void;
}

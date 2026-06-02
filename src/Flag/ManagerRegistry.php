<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Flag;

use Gracious\FeatureFlagBundle\Exception\UnknownManagerException;
use Psr\Container\ContainerInterface;

final readonly class ManagerRegistry
{
    public function __construct(
        private ContainerInterface $managers,
        private string $defaultManager = 'default',
    ) {}

    public function get(?string $name = null): FeatureFlagManagerInterface
    {
        $name ??= $this->defaultManager;

        if (!$this->managers->has($name)) {
            throw new UnknownManagerException($name);
        }

        $manager = $this->managers->get($name);
        \assert($manager instanceof FeatureFlagManagerInterface);

        return $manager;
    }

    public function getDefault(): FeatureFlagManagerInterface
    {
        return $this->get($this->defaultManager);
    }
}

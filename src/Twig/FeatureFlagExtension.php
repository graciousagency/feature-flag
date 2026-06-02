<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Twig;

use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;

final class FeatureFlagExtension extends AbstractExtension
{
    public function __construct(private readonly ManagerRegistry $registry) {}

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('feature', $this->isEnabled(...)),
        ];
    }

    #[\Override]
    public function getTests(): array
    {
        return [
            new TwigTest('feature_enabled', $this->isEnabled(...)),
        ];
    }

    private function isEnabled(string $name, ?string $manager = null): bool
    {
        return $this->registry->get($manager)->isEnabled($name);
    }
}

<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Attribute;

use Gracious\FeatureFlagBundle\Exception\ExceptionFactoryInterface;
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

final readonly class FeatureGuardListener
{
    public function __construct(
        private ManagerRegistry $registry,
        private ExceptionFactoryInterface $exceptionFactory,
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        $attributes = $event->getAttributes(RequireFeature::class);

        foreach ($attributes as $attribute) {
            $this->guard($attribute);
        }
    }

    private function guard(RequireFeature $requirement): void
    {
        $flag = $this->registry->get($requirement->manager)->get($requirement->name);

        if ($flag->enabled !== $requirement->enabled) {
            throw $this->exceptionFactory->create($flag, $requirement->enabled);
        }
    }
}

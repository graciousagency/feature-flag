<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Attribute;

use Gracious\FeatureFlagBundle\Attribute\FeatureGuardListener;
use Gracious\FeatureFlagBundle\Attribute\RequireFeature;
use Gracious\FeatureFlagBundle\Exception\DefaultExceptionFactory;
use Gracious\FeatureFlagBundle\Exception\FeatureNotAvailableException;
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManager;
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Gracious\FeatureFlagBundle\Override\OverrideStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class GuardFixtures
{
    #[RequireFeature('enabled_flag')]
    public function requiresEnabled(): void {}

    #[RequireFeature('enabled_flag', enabled: false)]
    public function requiresDisabled(): void {}

    #[RequireFeature('paid', manager: 'billing')]
    public function requiresBillingPaid(): void {}

    public function noAttribute(): void {}
}

#[RequireFeature('enabled_flag', enabled: false)]
final class ClassGuardedFixture
{
    public function action(): void {}
}

#[RequireFeature('enabled_flag')]
final class ClassAndMethodGuardedFixture
{
    #[RequireFeature('paid', manager: 'billing')]
    public function action(): void {}
}

final class FeatureGuardListenerTest extends TestCase
{
    private function listener(): FeatureGuardListener
    {
        $locator = new ServiceLocator([
            'default' => static fn() => new FeatureFlagManager(
                ['enabled_flag' => ['enabled' => true, 'description' => null]],
                new OverrideStore(),
            ),
            'billing' => static fn() => new FeatureFlagManager(
                ['paid' => ['enabled' => false, 'description' => null]],
                new OverrideStore(),
            ),
        ]);

        return new FeatureGuardListener(
            new ManagerRegistry($locator, 'default'),
            new DefaultExceptionFactory(),
        );
    }

    private function event(string $method): ControllerEvent
    {
        /** @var callable(): mixed $controller */
        $controller = [new GuardFixtures(), $method];

        return $this->eventFor($controller);
    }

    /**
     * @param callable(): mixed $controller
     */
    private function eventFor(callable $controller): ControllerEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ControllerEvent(
            $kernel,
            $controller,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    public function testPassesWhenRequirementMet(): void
    {
        $this->listener()->onKernelController($this->event('requiresEnabled'));
        $this->expectNotToPerformAssertions();
    }

    public function testBlocksWhenRequiredEnabledButDisabled(): void
    {
        // flag is enabled in config, so "requiresDisabled" must block
        $this->expectException(FeatureNotAvailableException::class);
        $this->listener()->onKernelController($this->event('requiresDisabled'));
    }

    public function testNoAttributeIsNoop(): void
    {
        $this->listener()->onKernelController($this->event('noAttribute'));
        $this->expectNotToPerformAssertions();
    }

    public function testClassLevelAttributeIsEnforced(): void
    {
        // The class requires 'enabled_flag' disabled, but it is enabled -> block.
        /** @var callable(): mixed $controller */
        $controller = [new ClassGuardedFixture(), 'action'];

        $this->expectException(FeatureNotAvailableException::class);
        $this->listener()->onKernelController($this->eventFor($controller));
    }

    public function testNamedManagerIsHonored(): void
    {
        // 'paid' is disabled in the 'billing' manager but the attribute requires
        // it enabled -> block, proving $requirement->manager is honored.
        $this->expectException(FeatureNotAvailableException::class);
        $this->listener()->onKernelController($this->event('requiresBillingPaid'));
    }

    public function testClassAndMethodAttributesAreBothEvaluated(): void
    {
        // Class-level requires 'enabled_flag' enabled (passes), method-level requires
        // 'paid' (billing) enabled (fails) -> block, proving both are evaluated.
        /** @var callable(): mixed $controller */
        $controller = [new ClassAndMethodGuardedFixture(), 'action'];

        $this->expectException(FeatureNotAvailableException::class);
        $this->listener()->onKernelController($this->eventFor($controller));
    }
}

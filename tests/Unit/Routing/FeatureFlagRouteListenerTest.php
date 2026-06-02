<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Routing;

use Gracious\FeatureFlagBundle\Exception\DefaultExceptionFactory;
use Gracious\FeatureFlagBundle\Exception\FeatureNotAvailableException;
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManager;
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Gracious\FeatureFlagBundle\Override\OverrideStore;
use Gracious\FeatureFlagBundle\Routing\FeatureFlagRouteListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class FeatureFlagRouteListenerTest extends TestCase
{
    private function listener(): FeatureFlagRouteListener
    {
        $locator = new ServiceLocator([
            'default' => static fn() => new FeatureFlagManager(
                [
                    'on' => ['enabled' => true, 'description' => null],
                    'off' => ['enabled' => false, 'description' => null],
                ],
                new OverrideStore(),
            ),
        ]);

        return new FeatureFlagRouteListener(
            new ManagerRegistry($locator, 'default'),
            new DefaultExceptionFactory(),
        );
    }

    /**
     * @param string|array<string, mixed>|null $config
     */
    private function event(string|array|null $config, bool $main = true): RequestEvent
    {
        $request = new Request();
        if ($config !== null) {
            $request->attributes->set('_feature_flag', $config);
        }

        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }

    public function testNoAttributeIsNoop(): void
    {
        $this->listener()->onKernelRequest($this->event(null));
        $this->expectNotToPerformAssertions();
    }

    public function testSubRequestIgnored(): void
    {
        $this->listener()->onKernelRequest($this->event('off', main: false));
        $this->expectNotToPerformAssertions();
    }

    public function testStringFormRequiresEnabled(): void
    {
        $this->listener()->onKernelRequest($this->event('on'));
        $this->expectNotToPerformAssertions();
    }

    public function testStringFormBlocksWhenDisabled(): void
    {
        $this->expectException(FeatureNotAvailableException::class);
        $this->listener()->onKernelRequest($this->event('off'));
    }

    public function testArrayFormRequiresDisabled(): void
    {
        $this->listener()->onKernelRequest($this->event(['name' => 'off', 'enabled' => false]));
        $this->expectNotToPerformAssertions();
    }

    public function testArrayFormBlocksWhenStateMismatches(): void
    {
        $this->expectException(FeatureNotAvailableException::class);
        $this->listener()->onKernelRequest($this->event(['name' => 'on', 'enabled' => false]));
    }

    public function testArrayFormWithoutNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->listener()->onKernelRequest($this->event(['enabled' => true]));
    }
}

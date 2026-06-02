<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Controller;

use Gracious\FeatureFlagBundle\Controller\FeatureFlagController;
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManager;
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Gracious\FeatureFlagBundle\Override\OverrideStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FeatureFlagControllerTest extends TestCase
{
    private function controller(): FeatureFlagController
    {
        $locator = new ServiceLocator([
            'default' => static fn() => new FeatureFlagManager(
                ['on' => ['enabled' => true, 'description' => 'On flag']],
                new OverrideStore(),
            ),
            'billing' => static fn() => new FeatureFlagManager(
                ['paid' => ['enabled' => false, 'description' => null]],
                new OverrideStore(),
            ),
        ]);

        return new FeatureFlagController(new ManagerRegistry($locator, 'default'));
    }

    private function disabledController(): FeatureFlagController
    {
        $locator = new ServiceLocator([
            'default' => static fn() => new FeatureFlagManager(
                ['on' => ['enabled' => true, 'description' => 'On flag']],
                new OverrideStore(),
            ),
        ]);

        return new FeatureFlagController(new ManagerRegistry($locator, 'default'), apiEnabled: false);
    }

    public function testListDefaultManager(): void
    {
        $response = $this->controller()->list(new Request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [['name' => 'on', 'enabled' => true, 'description' => 'On flag']],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testListNamedManager(): void
    {
        $response = $this->controller()->list(new Request(['manager' => 'billing']));

        self::assertSame(
            [['name' => 'paid', 'enabled' => false, 'description' => null]],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testShowSingleFlag(): void
    {
        $response = $this->controller()->show('on', new Request());

        self::assertSame(
            ['name' => 'on', 'enabled' => true, 'description' => 'On flag'],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testUnknownFlagIs404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->show('missing', new Request());
    }

    public function testUnknownManagerIs404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->list(new Request(['manager' => 'nope']));
    }

    public function testDisabledApiListIs404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->disabledController()->list(new Request());
    }

    public function testDisabledApiShowIs404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->disabledController()->show('on', new Request());
    }
}

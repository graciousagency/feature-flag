<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Twig;

use Gracious\FeatureFlagBundle\Flag\FeatureFlagManager;
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Gracious\FeatureFlagBundle\Override\OverrideStore;
use Gracious\FeatureFlagBundle\Twig\FeatureFlagExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class FeatureFlagExtensionTest extends TestCase
{
    private function twig(): Environment
    {
        $locator = new ServiceLocator([
            'default' => static fn() => new FeatureFlagManager(
                ['on' => ['enabled' => true, 'description' => null]],
                new OverrideStore(),
            ),
            'billing' => static fn() => new FeatureFlagManager(
                ['paid' => ['enabled' => false, 'description' => null]],
                new OverrideStore(),
            ),
        ]);

        $twig = new Environment(new ArrayLoader([
            'func' => "{{ feature('on') ? 'yes' : 'no' }}",
            'func_manager' => "{{ feature('paid', 'billing') ? 'yes' : 'no' }}",
            'test' => "{{ 'on' is feature_enabled ? 'yes' : 'no' }}",
        ]));
        $twig->addExtension(new FeatureFlagExtension(new ManagerRegistry($locator, 'default')));

        return $twig;
    }

    public function testFunctionDefaultManager(): void
    {
        self::assertSame('yes', $this->twig()->render('func'));
    }

    public function testFunctionNamedManager(): void
    {
        self::assertSame('no', $this->twig()->render('func_manager'));
    }

    public function testTest(): void
    {
        self::assertSame('yes', $this->twig()->render('test'));
    }
}

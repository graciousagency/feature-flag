<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Fixtures;

use Gracious\FeatureFlagBundle\GraciousFeatureFlagBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * Toggles the `gracious_feature_flag.api.enabled` option for tests.
     * Vary the kernel environment alongside this so the compiled container
     * cache does not collide between the two configurations.
     */
    public static bool $apiEnabled = true;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new GraciousFeatureFlagBundle()];
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/gracious_ff/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/gracious_ff/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'php_errors' => ['log' => true],
        ]);

        $container->extension('twig', [
            'strict_variables' => true,
        ]);

        $container->extension('gracious_feature_flag', [
            'flags' => [
                'new_checkout' => ['enabled' => true, 'description' => 'New checkout flow'],
                'legacy' => ['enabled' => false],
            ],
            'managers' => [
                'billing' => [
                    'flags' => [
                        'invoices_v2' => ['enabled' => true],
                    ],
                ],
            ],
            'api' => ['enabled' => self::$apiEnabled],
        ]);

        $container->services()
            ->set(GuardedController::class)
            ->autowire()
            ->autoconfigure()
            ->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@GraciousFeatureFlagBundle/config/routes.php')->prefix('/_feature-flags', false);

        $routes->add('checkout', '/checkout')
            ->controller([GuardedController::class, 'enabledOnly']);
        $routes->add('legacy', '/legacy')
            ->controller([GuardedController::class, 'disabledOnly']);

        $routes->add('requires_legacy', '/requires-legacy')
            ->controller([GuardedController::class, 'requiresLegacy']); // requires 'legacy' enabled -> blocked (it is off)

        $routes->add('beta', '/beta')
            ->controller([GuardedController::class, 'enabledOnly'])
            ->defaults(['_feature_flag' => 'legacy']); // require 'legacy' enabled -> blocked (it is off)
    }
}

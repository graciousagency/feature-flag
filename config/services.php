<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gracious\FeatureFlagBundle\Attribute\FeatureGuardListener;
use Gracious\FeatureFlagBundle\Controller\FeatureFlagController;
use Gracious\FeatureFlagBundle\Exception\DefaultExceptionFactory;
use Gracious\FeatureFlagBundle\Exception\ExceptionFactoryInterface;
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Gracious\FeatureFlagBundle\Routing\FeatureFlagRouteListener;
use Gracious\FeatureFlagBundle\Twig\FeatureFlagExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('gracious_feature_flag.registry', ManagerRegistry::class)
        ->args([abstract_arg('managers service locator'), 'default']);
    $services->alias(ManagerRegistry::class, 'gracious_feature_flag.registry');

    $services->set('gracious_feature_flag.exception_factory', DefaultExceptionFactory::class);
    $services->alias(ExceptionFactoryInterface::class, 'gracious_feature_flag.exception_factory');

    $services->set('gracious_feature_flag.route_listener', FeatureFlagRouteListener::class)
        ->args([
            service('gracious_feature_flag.registry'),
            service(ExceptionFactoryInterface::class),
        ])
        ->tag('kernel.event_listener', [
            'event' => 'kernel.request',
            'method' => 'onKernelRequest',
            'priority' => 8,
        ]);

    $services->set('gracious_feature_flag.guard_listener', FeatureGuardListener::class)
        ->args([
            service('gracious_feature_flag.registry'),
            service(ExceptionFactoryInterface::class),
        ])
        ->tag('kernel.event_listener', [
            'event' => 'kernel.controller',
            'method' => 'onKernelController',
        ]);

    $services->set('gracious_feature_flag.twig', FeatureFlagExtension::class)
        ->args([service('gracious_feature_flag.registry')])
        ->tag('twig.extension');

    $services->set(FeatureFlagController::class)
        ->args([service('gracious_feature_flag.registry')])
        ->tag('controller.service_arguments')
        ->public();
};

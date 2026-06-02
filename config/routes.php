<?php

declare(strict_types=1);

use Gracious\FeatureFlagBundle\Controller\FeatureFlagController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('gracious_feature_flag_list', '')
        ->controller([FeatureFlagController::class, 'list'])
        ->methods(['GET']);

    $routes->add('gracious_feature_flag_show', '/{name}')
        ->controller([FeatureFlagController::class, 'show'])
        ->methods(['GET']);
};

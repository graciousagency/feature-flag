<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Fixtures;

use Gracious\FeatureFlagBundle\Attribute\RequireFeature;
use Symfony\Component\HttpFoundation\Response;

final class GuardedController
{
    #[RequireFeature('new_checkout')]
    public function enabledOnly(): Response
    {
        return new Response('checkout');
    }

    #[RequireFeature('legacy', enabled: false)]
    public function disabledOnly(): Response
    {
        return new Response('legacy off');
    }

    #[RequireFeature('legacy')]
    public function requiresLegacy(): Response
    {
        return new Response('legacy on');
    }
}

<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Exception;

use Gracious\FeatureFlagBundle\Exception\DefaultExceptionFactory;
use Gracious\FeatureFlagBundle\Exception\FeatureNotAvailableException;
use Gracious\FeatureFlagBundle\Flag\Flag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class DefaultExceptionFactoryTest extends TestCase
{
    public function testBuildsDefaultExceptionWithConfiguredStatus(): void
    {
        $factory = new DefaultExceptionFactory(FeatureNotAvailableException::class, 403);
        $e = $factory->create(new Flag('beta', false), true);

        self::assertInstanceOf(FeatureNotAvailableException::class, $e);
        self::assertInstanceOf(HttpExceptionInterface::class, $e);
        self::assertSame(403, $e->getStatusCode());
        self::assertStringContainsString('beta', $e->getMessage());
    }

    public function testDefaultsTo404(): void
    {
        $e = (new DefaultExceptionFactory())->create(new Flag('beta', true), false);
        self::assertInstanceOf(FeatureNotAvailableException::class, $e);
        self::assertSame(404, $e->getStatusCode());
    }
}

<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Exception;

use Gracious\FeatureFlagBundle\Exception\FeatureNotAvailableException;
use Gracious\FeatureFlagBundle\Exception\UnknownFeatureException;
use Gracious\FeatureFlagBundle\Exception\UnknownManagerException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ExceptionsTest extends TestCase
{
    public function testUnknownFeatureMessage(): void
    {
        $e = new UnknownFeatureException('beta');
        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertStringContainsString('beta', $e->getMessage());
    }

    public function testUnknownManagerMessage(): void
    {
        $e = new UnknownManagerException('billing');
        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertStringContainsString('billing', $e->getMessage());
    }

    public function testFeatureNotAvailableCarriesStatusCode(): void
    {
        $e = new FeatureNotAvailableException('beta', 403);
        self::assertInstanceOf(HttpExceptionInterface::class, $e);
        self::assertSame(403, $e->getStatusCode());
        self::assertStringContainsString('beta', $e->getMessage());
    }

    public function testFeatureNotAvailableDefaultsTo404(): void
    {
        self::assertSame(404, (new FeatureNotAvailableException('beta'))->getStatusCode());
    }
}

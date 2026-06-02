<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Flag;

use Gracious\FeatureFlagBundle\Exception\UnknownManagerException;
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManager;
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManagerInterface;
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Gracious\FeatureFlagBundle\Override\OverrideStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ManagerRegistryTest extends TestCase
{
    private function registry(): ManagerRegistry
    {
        $locator = new ServiceLocator([
            'default' => static fn(): FeatureFlagManagerInterface => new FeatureFlagManager(
                ['a' => ['enabled' => true, 'description' => null]],
                new OverrideStore(),
            ),
            'billing' => static fn(): FeatureFlagManagerInterface => new FeatureFlagManager(
                ['b' => ['enabled' => false, 'description' => null]],
                new OverrideStore(),
            ),
        ]);

        return new ManagerRegistry($locator, 'default');
    }

    public function testGetNamedManager(): void
    {
        self::assertTrue($this->registry()->get('billing')->has('b'));
    }

    public function testGetNullReturnsDefault(): void
    {
        self::assertTrue($this->registry()->get(null)->has('a'));
    }

    public function testGetDefault(): void
    {
        self::assertTrue($this->registry()->getDefault()->has('a'));
    }

    public function testUnknownManagerThrows(): void
    {
        $this->expectException(UnknownManagerException::class);
        $this->registry()->get('missing');
    }
}

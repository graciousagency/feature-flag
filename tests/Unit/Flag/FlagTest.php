<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Flag;

use Gracious\FeatureFlagBundle\Flag\Flag;
use PHPUnit\Framework\TestCase;

final class FlagTest extends TestCase
{
    public function testExposesReadonlyState(): void
    {
        $flag = new Flag('checkout', true, 'New checkout');

        self::assertSame('checkout', $flag->name);
        self::assertTrue($flag->enabled);
        self::assertSame('New checkout', $flag->description);
    }

    public function testDescriptionDefaultsToNull(): void
    {
        self::assertNull((new Flag('x', false))->description);
    }

    public function testToArray(): void
    {
        self::assertSame(
            ['name' => 'x', 'enabled' => true, 'description' => 'd'],
            (new Flag('x', true, 'd'))->toArray(),
        );
    }

    public function testWithEnabledReturnsNewInstance(): void
    {
        $flag = new Flag('x', false, 'd');
        $toggled = $flag->withEnabled(true);

        self::assertFalse($flag->enabled);
        self::assertTrue($toggled->enabled);
        self::assertSame('d', $toggled->description);
    }
}

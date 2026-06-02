<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Override;

use Gracious\FeatureFlagBundle\Override\OverrideStore;
use PHPUnit\Framework\TestCase;

final class OverrideStoreTest extends TestCase
{
    public function testHasIsFalseWhenUnset(): void
    {
        self::assertFalse((new OverrideStore())->has('x'));
    }

    public function testSetThenGet(): void
    {
        $store = new OverrideStore();
        $store->set('x', true);

        self::assertTrue($store->has('x'));
        self::assertTrue($store->get('x'));

        $store->set('x', false);
        self::assertFalse($store->get('x'));
    }

    public function testClearRemovesOverride(): void
    {
        $store = new OverrideStore();
        $store->set('x', true);
        $store->clear('x');

        self::assertFalse($store->has('x'));
    }

    public function testClearUnknownIsNoop(): void
    {
        $store = new OverrideStore();
        $store->clear('missing');

        self::assertFalse($store->has('missing'));
    }
}

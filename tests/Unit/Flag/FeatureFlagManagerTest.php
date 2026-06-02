<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Tests\Unit\Flag;

use Gracious\FeatureFlagBundle\Exception\UnknownFeatureException;
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManager;
use Gracious\FeatureFlagBundle\Flag\Flag;
use Gracious\FeatureFlagBundle\Override\OverrideStore;
use PHPUnit\Framework\TestCase;

final class FeatureFlagManagerTest extends TestCase
{
    private function manager(): FeatureFlagManager
    {
        return new FeatureFlagManager(
            [
                'on' => ['enabled' => true, 'description' => 'On flag'],
                'off' => ['enabled' => false, 'description' => null],
            ],
            new OverrideStore(),
        );
    }

    public function testIsEnabledReadsConfig(): void
    {
        $m = $this->manager();
        self::assertTrue($m->isEnabled('on'));
        self::assertFalse($m->isEnabled('off'));
    }

    public function testHas(): void
    {
        $m = $this->manager();
        self::assertTrue($m->has('on'));
        self::assertFalse($m->has('missing'));
    }

    public function testGetReturnsResolvedFlag(): void
    {
        $flag = $this->manager()->get('on');
        self::assertInstanceOf(Flag::class, $flag);
        self::assertSame('on', $flag->name);
        self::assertTrue($flag->enabled);
        self::assertSame('On flag', $flag->description);
    }

    public function testAllReturnsKeyedFlags(): void
    {
        $all = $this->manager()->all();
        self::assertSame(['on', 'off'], array_keys($all));
        self::assertTrue($all['on']->enabled);
    }

    public function testOverrideWinsOverConfig(): void
    {
        $m = $this->manager();
        $m->disable('on');
        self::assertFalse($m->isEnabled('on'));
        self::assertFalse($m->get('on')->enabled);

        $m->enable('off');
        self::assertTrue($m->isEnabled('off'));
    }

    public function testResetRestoresConfigValue(): void
    {
        $m = $this->manager();
        $m->disable('on');
        $m->reset('on');
        self::assertTrue($m->isEnabled('on'));
    }

    public function testIsEnabledOnUnknownThrows(): void
    {
        $this->expectException(UnknownFeatureException::class);
        $this->manager()->isEnabled('missing');
    }

    public function testGetUnknownThrows(): void
    {
        $this->expectException(UnknownFeatureException::class);
        $this->manager()->get('missing');
    }

    public function testEnableUnknownThrows(): void
    {
        $this->expectException(UnknownFeatureException::class);
        $this->manager()->enable('missing');
    }
}

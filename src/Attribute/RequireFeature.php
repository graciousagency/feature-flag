<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class RequireFeature
{
    public function __construct(
        public string $name,
        public bool $enabled = true,
        public ?string $manager = null,
    ) {}
}

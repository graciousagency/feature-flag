<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Exception;

final class UnknownFeatureException extends \InvalidArgumentException
{
    public function __construct(string $name)
    {
        parent::__construct(\sprintf('Unknown feature flag "%s".', $name));
    }
}

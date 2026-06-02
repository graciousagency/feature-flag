<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Exception;

final class UnknownManagerException extends \InvalidArgumentException
{
    public function __construct(string $name)
    {
        parent::__construct(\sprintf('Unknown feature flag manager "%s".', $name));
    }
}

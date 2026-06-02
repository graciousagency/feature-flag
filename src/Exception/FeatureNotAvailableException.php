<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class FeatureNotAvailableException extends HttpException
{
    public function __construct(string $featureName, int $statusCode = 404, ?\Throwable $previous = null)
    {
        parent::__construct(
            $statusCode,
            \sprintf('Feature "%s" is not available.', $featureName),
            $previous,
        );
    }
}

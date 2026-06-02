<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Exception;

use Gracious\FeatureFlagBundle\Flag\Flag;

/**
 * Builds the configured exception class. The class MUST accept
 * a (string $featureName, int $statusCode) constructor signature.
 */
final readonly class DefaultExceptionFactory implements ExceptionFactoryInterface
{
    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    public function __construct(
        private string $exceptionClass = FeatureNotAvailableException::class,
        private int $statusCode = 404,
    ) {}

    #[\Override]
    public function create(Flag $flag, bool $required): \Throwable
    {
        $class = $this->exceptionClass;

        return new $class($flag->name, $this->statusCode);
    }
}

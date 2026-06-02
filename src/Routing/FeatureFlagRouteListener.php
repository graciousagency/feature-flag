<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Routing;

use Gracious\FeatureFlagBundle\Exception\ExceptionFactoryInterface;
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class FeatureFlagRouteListener
{
    public function __construct(
        private ManagerRegistry $registry,
        private ExceptionFactoryInterface $exceptionFactory,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $config = $event->getRequest()->attributes->get('_feature_flag');
        if ($config === null) {
            return;
        }

        if (!\is_string($config) && !\is_array($config)) {
            throw new \InvalidArgumentException('The "_feature_flag" route default must be a string or an array.');
        }

        [$name, $required, $manager] = $this->normalize($config);

        $flag = $this->registry->get($manager)->get($name);
        if ($flag->enabled !== $required) {
            throw $this->exceptionFactory->create($flag, $required);
        }
    }

    /**
     * @param string|array<mixed, mixed> $config
     *
     * @return array{0: string, 1: bool, 2: string|null}
     */
    private function normalize(string|array $config): array
    {
        if (\is_string($config)) {
            return [$config, true, null];
        }

        if (!isset($config['name']) || !\is_scalar($config['name'])) {
            throw new \InvalidArgumentException('The "_feature_flag" route default requires a "name" key.');
        }

        $manager = $config['manager'] ?? null;

        return [
            (string) $config['name'],
            (bool) ($config['enabled'] ?? true),
            \is_scalar($manager) ? (string) $manager : null,
        ];
    }
}

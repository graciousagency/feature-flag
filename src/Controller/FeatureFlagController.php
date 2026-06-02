<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Controller;

use Gracious\FeatureFlagBundle\Exception\UnknownFeatureException;
use Gracious\FeatureFlagBundle\Exception\UnknownManagerException;
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManagerInterface;
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class FeatureFlagController
{
    public function __construct(
        private ManagerRegistry $registry,
        private bool $apiEnabled = true,
    ) {}

    public function list(Request $request): JsonResponse
    {
        $this->assertApiEnabled();
        $manager = $this->resolveManager($request);

        $data = array_map(
            static fn($flag): array => $flag->toArray(),
            array_values($manager->all()),
        );

        return new JsonResponse($data);
    }

    public function show(string $name, Request $request): JsonResponse
    {
        $this->assertApiEnabled();
        $manager = $this->resolveManager($request);

        try {
            return new JsonResponse($manager->get($name)->toArray());
        } catch (UnknownFeatureException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }

    private function assertApiEnabled(): void
    {
        if (!$this->apiEnabled) {
            throw new NotFoundHttpException('The feature flag API is disabled.');
        }
    }

    private function resolveManager(Request $request): FeatureFlagManagerInterface
    {
        $name = $request->query->get('manager');

        try {
            return $this->registry->get(\is_string($name) ? $name : null);
        } catch (UnknownManagerException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }
}

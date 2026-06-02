<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle;

use Gracious\FeatureFlagBundle\Controller\FeatureFlagController;
use Gracious\FeatureFlagBundle\Exception\ExceptionFactoryInterface;
use Gracious\FeatureFlagBundle\Exception\FeatureNotAvailableException;
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManager;
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManagerInterface;
use Gracious\FeatureFlagBundle\Override\OverrideStore;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class GraciousFeatureFlagBundle extends AbstractBundle
{
    #[\Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $rootNode = $definition->rootNode();

        $children = $rootNode->children();

        $this->defineFlagsNode($children->arrayNode('flags'));

        $managersChildren = $children->arrayNode('managers')
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children();

        $this->defineFlagsNode($managersChildren->arrayNode('flags'));

        $managersChildren->end()->end()->end();

        $children->arrayNode('exception')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('class')->defaultValue(FeatureNotAvailableException::class)->end()
                ->scalarNode('factory')->defaultNull()->end()
                ->integerNode('status_code')->defaultValue(404)->end()
            ->end()
        ->end();

        $children->arrayNode('api')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
            ->end()
        ->end();
    }

    /**
     * @param array{
     *     flags?: array<string, array{enabled?: bool, description?: string|null}>,
     *     managers?: array<string, array{flags?: array<string, array{enabled?: bool, description?: string|null}>}>,
     *     exception: array{class: string, factory: string|null, status_code: int},
     *     api: array{enabled: bool},
     * } $config
     */
    #[\Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $groups = ['default' => $this->normalizeFlags($config['flags'] ?? [])];
        foreach ($config['managers'] ?? [] as $name => $managerConfig) {
            $groups[$name] = $this->normalizeFlags($managerConfig['flags'] ?? []);
        }

        $locatorRefs = [];
        foreach ($groups as $name => $flags) {
            $storeId = \sprintf('gracious_feature_flag.override_store.%s', $name);
            $managerId = \sprintf('gracious_feature_flag.manager.%s', $name);

            $builder->register($storeId, OverrideStore::class);
            $builder->register($managerId, FeatureFlagManager::class)
                ->setArgument('$config', $flags)
                ->setArgument('$overrides', new Reference($storeId));

            $locatorRefs[$name] = new Reference($managerId);

            if ($name !== 'default') {
                $builder->registerAliasForArgument(
                    $managerId,
                    FeatureFlagManagerInterface::class,
                    \sprintf('%s manager', $name),
                );
            }
        }

        $builder->setAlias(FeatureFlagManagerInterface::class, 'gracious_feature_flag.manager.default');
        $builder->setAlias('gracious_feature_flag.manager', 'gracious_feature_flag.manager.default');

        $builder->getDefinition('gracious_feature_flag.registry')
            ->setArgument(0, ServiceLocatorTagPass::register($builder, $locatorRefs));

        if ($config['exception']['factory'] !== null) {
            $builder->setAlias(ExceptionFactoryInterface::class, $config['exception']['factory']);
        } else {
            $builder->getDefinition('gracious_feature_flag.exception_factory')
                ->setArgument('$exceptionClass', $config['exception']['class'])
                ->setArgument('$statusCode', $config['exception']['status_code']);
        }

        $builder->getDefinition(FeatureFlagController::class)
            ->setArgument('$apiEnabled', $config['api']['enabled']);

        if (!class_exists('Twig\\Extension\\AbstractExtension')) {
            $builder->removeDefinition('gracious_feature_flag.twig');
        }
    }

    /**
     * @param array<string, array{enabled?: bool, description?: string|null}> $flags
     *
     * @return array<string, array{enabled: bool, description: string|null}>
     */
    private function normalizeFlags(array $flags): array
    {
        $normalized = [];
        foreach ($flags as $name => $flag) {
            $normalized[$name] = [
                'enabled' => $flag['enabled'] ?? false,
                'description' => $flag['description'] ?? null,
            ];
        }

        return $normalized;
    }

    private function defineFlagsNode(ArrayNodeDefinition $node): void
    {
        $node
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->booleanNode('enabled')->defaultFalse()->end()
                    ->scalarNode('description')->defaultNull()->end()
                ->end()
            ->end();
    }
}

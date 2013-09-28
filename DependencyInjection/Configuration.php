<?php

namespace RedEyeApps\AWSCloudSearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('aws_cloud_search');

        //Node which defines an array of AWS indexes.
        $rootNode->
            children()
                ->arrayNode('indexes')
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('doc_endpoint')
                                ->isRequired(true)
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('search_endpoint')
                                ->isRequired(true)
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('lang')
                                ->isRequired(true)
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('id_prefix')
                                ->isRequired(false)
                                ->defaultValue('')
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ; 

        return $treeBuilder;
    }
}

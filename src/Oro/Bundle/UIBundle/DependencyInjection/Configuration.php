<?php

namespace Oro\Bundle\UIBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use Oro\Bundle\ConfigBundle\DependencyInjection\SettingsBuilder;
use Oro\Bundle\UIBundle\Tools\ArrayUtils;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see
 * {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('oro_ui');

        $rootNode->children()
            ->booleanNode('show_pin_button_on_start_page')
                ->defaultValue(true)
            ->end()
            ->arrayNode('placeholders')
                ->useAttributeAsKey('name')
                ->prototype('array')
                    ->children()
                        ->append($this->getPlaceholdersConfigTree())
                    ->end()
                ->end()
            ->end()
            ->append($this->getPlaceholderItemsConfigTree())
        ->end();

        SettingsBuilder::append(
            $rootNode,
            [
                'application_name'  => ['value' => 'ORO'],
                'application_title' => ['value' => 'ORO Business Application Platform'],
                'application_url'   => ['value' => 'http://localhost/oro/'],
                'navbar_position'   => ['value' => 'top'],
            ]
        );

        return $treeBuilder;
    }

    /**
     * Builds the configuration tree for placeholders
     *
     * @return NodeDefinition
     */
    protected function getPlaceholdersConfigTree()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('items');

        $node
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->children()
                ->append($this->getRemoveAttributeConfigTree())
                ->integerNode('order')->defaultValue(0)->end()
            ->end()
            ->validate()
                // remove all items with remove=TRUE
                ->ifTrue(
                    function ($v) {
                        return (isset($v['remove']) && $v['remove']);
                    }
                )
                ->thenUnset()
            ->end();

        $this->addItemsSorting($node);

        return $node;
    }

    /**
     * Builds the configuration tree for placeholder items
     *
     * @return NodeDefinition
     */
    protected function getPlaceholderItemsConfigTree()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('placeholder_items');

        $node
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->children()
                ->scalarNode('applicable')->end()
                ->scalarNode('acl')->end()
                ->scalarNode('action')->end()
                ->scalarNode('template')->end()
                ->variableNode('data')->end()
            ->end()
            ->validate()
                // remove all items if neither 'action' nor 'template' attribute is not specified
                ->ifTrue(
                    function ($v) {
                        return (empty($v['action']) && empty($v['template']));
                    }
                )
                ->thenUnset()
            ->end()
            ->validate()
                // both 'action' and 'template' attributes should not be specified
                ->ifTrue(
                    function ($v) {
                        return !empty($v['action']) && !empty($v['template']);
                    }
                )
                ->thenInvalid('Only one either "action" or "template" attribute can be defined. %s')
            ->end();

        return $node;
    }

    /**
     * Builds the configuration tree for 'remove' attribute
     *
     * @return NodeDefinition
     */
    protected function getRemoveAttributeConfigTree()
    {
        $builder = new TreeBuilder();
        $node    = $builder->root('remove', 'boolean');

        $node
            ->validate()
            // keep the 'remove' attribute only if its value is TRUE
            ->ifTrue(
                function ($v) {
                    return isset($v) && !$v;
                }
            )
            ->thenUnset()
            ->end();

        return $node;
    }

    /**
     * Add rules to sort items by 'order' attribute
     *
     * @param NodeDefinition $node
     */
    protected function addItemsSorting(NodeDefinition $node)
    {
        $node
            ->validate()
                ->always(
                    function ($v) {
                        return $this->sortItems($v);
                    }
                )
            ->end();
    }

    /**
     * Sorts the given items by 'order' attribute
     *
     * @param array $items
     * @return mixed
     */
    protected function sortItems($items)
    {
        ArrayUtils::sortBy($items, false, 'order');

        return array_keys($items);
    }
}

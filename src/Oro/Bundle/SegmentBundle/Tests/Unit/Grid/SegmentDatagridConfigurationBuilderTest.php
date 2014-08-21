<?php

namespace Oro\Bundle\SegmentBundle\Tests\Unit\Grid;

use Doctrine\ORM\Query;

use Oro\Bundle\SegmentBundle\Grid\SegmentDatagridConfigurationBuilder;
use Oro\Bundle\SegmentBundle\Tests\Unit\SegmentDefinitionTestCase;

class SegmentDatagridConfigurationBuilderTest extends SegmentDefinitionTestCase
{
    const TEST_GRID_NAME = 'test';

    public function testConfiguration()
    {
        $segment       = $this->getSegment();
        $doctrine      = $this->getDoctrine(
            [self::TEST_ENTITY => []],
            [self::TEST_ENTITY => [self::TEST_IDENTIFIER_NAME]]
        );
        $configManager = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigManager')
            ->disableOriginalConstructor()->getMock();

        $entityMetadata            = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Metadata\EntityMetadata')
            ->disableOriginalConstructor()->getMock();
        $entityMetadata->routeView = 'route';

        $configManager->expects($this->once())
            ->method('getEntityMetadata')
            ->with($segment->getEntity())
            ->will($this->returnValue($entityMetadata));

        $builder = new SegmentDatagridConfigurationBuilder(
            $this->getFunctionProvider(),
            $this->getVirtualFieldProvider(),
            $doctrine
        );

        $builder->setGridName(self::TEST_GRID_NAME);
        $builder->setSource($segment);
        $builder->setConfigManager($configManager);

        $result   = $builder->getConfiguration()->toArray();
        $expected = $this->getExpectedDefinition('route');

        $this->assertEquals($expected, $result);
    }

    /**
     * Test grid definition when no route exists for entity in config
     * no grid actions should be added
     */
    public function testNoRouteConfiguration()
    {
        $segment       = $this->getSegment();
        $doctrine      = $this->getDoctrine(
            [self::TEST_ENTITY => []],
            [self::TEST_ENTITY => [self::TEST_IDENTIFIER_NAME]]
        );
        $configManager = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigManager')
            ->disableOriginalConstructor()->getMock();

        $builder = new SegmentDatagridConfigurationBuilder(
            $this->getFunctionProvider(),
            $this->getVirtualFieldProvider(),
            $doctrine
        );

        $builder->setConfigManager($configManager);
        $builder->setGridName(self::TEST_GRID_NAME);
        $builder->setSource($segment);

        $result   = $builder->getConfiguration()->toArray();
        $expected = $this->getExpectedDefinition();

        $this->assertSame($expected, $result);
    }

    public function getExpectedDefinition($route = null)
    {
        $definition = [
            'name'    => self::TEST_GRID_NAME,
            'columns' => ['c1' => ['label' => 'User name', 'translatable' => false, 'frontend_type' => 'string']],
            'sorters' => ['columns' => ['c1' => ['data_name' => 'c1']]],
            'filters' => ['columns' => ['c1' => ['type' => 'string', 'data_name' => 'c1', 'translatable' => false]]],
            'source'  => [
                'query'        => [
                    'select' => ['t1.userName as c1'],
                    'from'   => [['table' => self::TEST_ENTITY, 'alias' => 't1']]
                ],
                'query_config' => [
                    'filters'        => [
                        [
                            'column'     => sprintf('t1.%s', self::TEST_IDENTIFIER_NAME),
                            'filter'     => 'segment',
                            'filterData' => ['value' => self::TEST_IDENTIFIER]
                        ]
                    ],
                    'table_aliases'  => ['' => 't1'],
                    'column_aliases' => ['userName' => 'c1',]
                ],
                'type'         => 'orm',
                'hints'        => [
                    [
                        'name'  => Query::HINT_CUSTOM_OUTPUT_WALKER,
                        'value' => 'Gedmo\Translatable\Query\TreeWalker\TranslationWalker',
                    ]
                ],
                'acl_resource' => 'oro_segment_view',
            ],
            'options' => ['export' => true],
        ];

        if (!empty($route)) {
            $definition                              = array_merge(
                $definition,
                [
                    'properties' => [
                        'id'        => null,
                        'view_link' => [
                            'type'   => 'url',
                            'route'  => 'route',
                            'params' => ['id']
                        ]
                    ],
                    'actions'    => [
                        'view' => [
                            'type'         => 'navigate',
                            'acl_resource' => 'VIEW;entity:AcmeBundle:UserEntity',
                            'label'        => 'View',
                            'icon'         => 'eye-open',
                            'link'         => 'view_link',
                            'rowAction'    => true,
                        ],
                    ],
                ]
            );
            $definition['source']['query']['select'] = ['t1.userName as c1', 't1.' . self::TEST_IDENTIFIER_NAME];
        }

        return $definition;
    }
}

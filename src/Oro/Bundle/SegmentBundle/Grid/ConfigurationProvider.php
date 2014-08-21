<?php

namespace Oro\Bundle\SegmentBundle\Grid;

use Symfony\Bridge\Doctrine\ManagerRegistry;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Provider\ConfigurationProviderInterface;
use Oro\Bundle\QueryDesignerBundle\Exception\InvalidConfigurationException;
use Oro\Bundle\QueryDesignerBundle\Grid\BuilderAwareInterface;
use Oro\Bundle\SegmentBundle\Entity\Segment;

class ConfigurationProvider implements ConfigurationProviderInterface, BuilderAwareInterface
{
    /** @var SegmentDatagridConfigurationBuilder */
    protected $builder;

    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var DatagridConfiguration */
    private $configuration = null;

    /**
     * Constructor
     *
     * @param SegmentDatagridConfigurationBuilder $builder
     * @param ManagerRegistry                     $doctrine
     */
    public function __construct(
        SegmentDatagridConfigurationBuilder $builder,
        ManagerRegistry $doctrine
    ) {
        $this->builder  = $builder;
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function isApplicable($gridName)
    {
        return $this->builder->isApplicable($gridName);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration($gridName)
    {
        if (null === $this->configuration) {
            $id                = intval(substr($gridName, strlen(Segment::GRID_PREFIX)));
            $segmentRepository = $this->doctrine->getRepository('OroSegmentBundle:Segment');
            $segment           = $segmentRepository->find($id);

            $this->builder->setGridName($gridName);
            $this->builder->setSource($segment);

            $this->configuration = $this->builder->getConfiguration();
        }

        return $this->configuration;
    }

    /**
     * Check whether a segment grid ready for displaying
     *
     * @param string $gridName
     *
     * @return bool
     */
    public function isConfigurationValid($gridName)
    {
        try {
            $this->getConfiguration($gridName);
        } catch (InvalidConfigurationException $invalidConfigEx) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getBuilder()
    {
        return $this->builder;
    }
}

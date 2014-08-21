<?php

namespace Oro\Bundle\QueryDesignerBundle\Grid;

use Symfony\Bridge\Doctrine\ManagerRegistry;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\EntityBundle\Provider\VirtualFieldProviderInterface;
use Oro\Bundle\QueryDesignerBundle\Exception\InvalidConfigurationException;
use Oro\Bundle\QueryDesignerBundle\Model\AbstractQueryDesigner;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\FunctionProviderInterface;

class DatagridConfigurationBuilder
{
    /**
     * @var DatagridConfigurationQueryConverter
     */
    protected $converter;

    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @var AbstractQueryDesigner
     */
    protected $source;

    /**
     * @var string
     */
    protected $gridName;

    /**
     * Constructor
     *
     * @param FunctionProviderInterface     $functionProvider
     * @param VirtualFieldProviderInterface $virtualFieldProvider
     * @param ManagerRegistry               $doctrine
     * @throws InvalidConfigurationException
     */
    public function __construct(
        FunctionProviderInterface $functionProvider,
        VirtualFieldProviderInterface $virtualFieldProvider,
        ManagerRegistry $doctrine
    ) {
        $this->doctrine = $doctrine;

        $this->converter = new DatagridConfigurationQueryConverter($functionProvider, $virtualFieldProvider, $doctrine);
    }

    /**
     * @param AbstractQueryDesigner $source
     */
    public function setSource(AbstractQueryDesigner $source)
    {
        $this->source = $source;
    }

    /**
     * @param string $gridName
     */
    public function setGridName($gridName)
    {
        $this->gridName = $gridName;
    }

    /**
     * Return a datagrid configuration
     *
     * @return DatagridConfiguration
     */
    public function getConfiguration()
    {
        if (empty($this->gridName)) {
            throw new \InvalidArgumentException('Grid name not configured');
        }

        if (!$this->source) {
            throw new \InvalidArgumentException('Source is missing');
        }

        return $this->converter->convert($this->gridName, $this->source);
    }

    /**
     * @param string $gridName
     *
     * @return bool
     */
    public function isApplicable($gridName)
    {
        return false;
    }
}

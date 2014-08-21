<?php

namespace Oro\Bundle\EntityBundle\EventListener;

use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;

class RelationEntityGridListener extends CustomEntityGridListener
{
    const GRID_NAME = 'entity-relation-grid';

    /**
     * @var ConfigInterface
     */
    protected $relationConfig;

    /** @var object */
    protected $relation;

    /** @var string */
    protected $hasAssignedExpression;

    /**
     * @param BuildAfter $event
     */
    public function onBuildAfter(BuildAfter $event)
    {
        $datasource = $event->getDatagrid()->getDatasource();
        if ($datasource instanceof OrmDatasource) {
            $queryBuilder = $datasource->getQueryBuilder();

            $added   = $this->request->get('added');
            $removed = $this->request->get('removed');

            if (!empty($added)) {
                $added = explode(',', $added);
            } else {
                $added = [0];
            }

            if (!empty($removed)) {
                $removed = explode(',', $removed);
            } else {
                $removed = [0];
            }

            $parameters = [
                'data_in'     => $added,
                'data_not_in' => $removed
            ];

            if ($this->relation->getId() != null) {
                $parameters['relation'] = $this->relation;
            }

            $queryBuilder->setParameters($parameters);
        }
    }

    /**
     * @param BuildBefore $event
     * @return bool
     */
    public function onBuildBefore(BuildBefore $event)
    {
        $datagrid = $event->getDatagrid();

        // get field config, extendEntity, $added, $removed
        $extendEntityName = $this->getParam($datagrid, 'class_name');
        $extendEntityName = str_replace('_', '\\', $extendEntityName);
        $fieldName        = $this->getParam($datagrid, 'field_name');
        $entityId         = $this->getParam($datagrid, 'id');

        /** @var ConfigProvider $extendConfigProvider */
        $extendConfigProvider = $this->configManager->getProvider('extend');
        $fieldConfig          = $extendConfigProvider->getConfig($extendEntityName, $fieldName);

        $this->entityClass    = $fieldConfig->get('target_entity');
        $this->relationConfig = $fieldConfig;

        // set extendEntity
        $extendEntity = $this->configManager
            ->getEntityManager()
            ->getRepository($extendEntityName)
            ->find($entityId);
        if (!$extendEntity) {
            $extendEntity = new $extendEntityName;
        }
        $this->relation = $extendEntity;

        parent::onBuildBefore($event);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDynamicFields($alias = null, $itemsType = null)
    {
        $result = parent::getDynamicFields($alias, $itemsType);

        $result = array_merge_recursive(
            $result,
            [
                'source' => [
                    'query' => ['select' => [$this->getHasAssignedExpression() . ' as assigned']],
                ]
            ]
        );

        return $result;
    }

    /**
     * Get dynamic field or empty array if field is not visible
     *
     * @param                 $alias
     * @param ConfigInterface $extendConfig
     * @return array
     */
    public function getDynamicFieldItem($alias, ConfigInterface $extendConfig)
    {
        /** @var FieldConfigId $fieldConfig */
        $fieldConfig = $extendConfig->getId();
        $fieldName   = $fieldConfig->getFieldName();

        $select = ''; // no need to add to select anything here
        $field  = [];

        $isGridFieldName  = in_array($fieldName, $this->relationConfig->get('target_grid'));
        $isTitleFieldName = in_array($fieldName, $this->relationConfig->get('target_title'));

        if ($isGridFieldName || $isTitleFieldName) {
            /** @var ConfigProvider $entityConfigProvider */
            $entityConfigProvider = $this->configManager->getProvider('entity');
            $entityConfig         = $entityConfigProvider->getConfig($this->entityClass, $fieldName);

            $label  = $entityConfig->get('label') ?: $fieldName;
            $field  = $this->createFieldArrayDefinition($fieldName, $label, $fieldConfig, $isGridFieldName);
            $select = $alias . '.' . $fieldName;
        }

        return [$field, $select];
    }

    /**
     * @return string
     */
    protected function getHasAssignedExpression()
    {
        $entityConfig = $this->configManager->getProvider('extend')->getConfig(
            $this->relationConfig->getId()->getClassName()
        );
        $relations    = $entityConfig->get('relation');
        $relation     = $relations[$this->relationConfig->get('relation_key')];

        $fieldName = $relation['target_field_id']->getFieldName();

        if (null === $this->hasAssignedExpression) {
            $entityAlias = 'ce';

            // TODO: getting a field type from a model here is a temporary solution.
            // We need to use $this->relationConfig->getId()->getFieldType()
            $fieldType = $this->configManager->getConfigFieldModel(
                $this->relationConfig->getId()->getClassName(),
                $this->relationConfig->getId()->getFieldName()
            )->getType();

            $compOperator = $fieldType == 'oneToMany' ? '=' : 'MEMBER OF';

            if ($this->getRelation()->getId()) {
                $this->hasAssignedExpression =
                    "CASE WHEN " .
                    "(:relation $compOperator $entityAlias.$fieldName OR $entityAlias.id IN (:data_in)) AND " .
                    "$entityAlias.id NOT IN (:data_not_in) " .
                    "THEN true ELSE false END";
            } else {
                $this->hasAssignedExpression =
                    "CASE WHEN " .
                    "$entityAlias.id IN (:data_in) AND $entityAlias.id NOT IN (:data_not_in) " .
                    "THEN true ELSE false END";
            }
        }

        return $this->hasAssignedExpression;
    }

    /**
     * @return mixed
     * @throws \LogicException
     */
    public function getRelation()
    {
        if (!$this->relation) {
            throw new \LogicException('Datagrid manager has no configured relation entity');
        }

        return $this->relation;
    }
}

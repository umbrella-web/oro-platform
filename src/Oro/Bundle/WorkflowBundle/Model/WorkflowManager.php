<?php

namespace Oro\Bundle\WorkflowBundle\Model;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Entity\Repository\WorkflowItemRepository;
use Oro\Bundle\WorkflowBundle\Exception\WorkflowException;

class WorkflowManager
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var WorkflowRegistry
     */
    protected $workflowRegistry;

    /**
     * @var DoctrineHelper
     */
    protected $doctrineHelper;

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @param ManagerRegistry $registry
     * @param WorkflowRegistry $workflowRegistry
     * @param DoctrineHelper $doctrineHelper
     * @param ConfigManager $configManager
     */
    public function __construct(
        ManagerRegistry $registry,
        WorkflowRegistry $workflowRegistry,
        DoctrineHelper $doctrineHelper,
        ConfigManager $configManager
    ) {
        $this->registry = $registry;
        $this->workflowRegistry = $workflowRegistry;
        $this->doctrineHelper = $doctrineHelper;
        $this->configManager = $configManager;
    }

    /**
     * @param string|Workflow $workflow
     * @return Collection
     */
    public function getStartTransitions($workflow)
    {
        $workflow = $this->getWorkflow($workflow);

        return $workflow->getTransitionManager()->getStartTransitions();
    }

    /**
     * @param WorkflowItem $workflowItem
     * @return Collection
     */
    public function getTransitionsByWorkflowItem(WorkflowItem $workflowItem)
    {
        $workflow = $this->getWorkflow($workflowItem);

        return $workflow->getTransitionsByWorkflowItem($workflowItem);
    }

    /**
     * @param string|Transition $transition
     * @param WorkflowItem $workflowItem
     * @param Collection $errors
     * @return bool
     */
    public function isTransitionAvailable(WorkflowItem $workflowItem, $transition, Collection $errors = null)
    {
        $workflow = $this->getWorkflow($workflowItem);

        return $workflow->isTransitionAvailable($workflowItem, $transition, $errors);
    }

    /**
     * @param string|Transition $transition
     * @param string|Workflow $workflow
     * @param object $entity
     * @param array $data
     * @param Collection $errors
     * @return bool
     */
    public function isStartTransitionAvailable(
        $workflow,
        $transition,
        $entity,
        array $data = array(),
        Collection $errors = null
    ) {
        $workflow = $this->getWorkflow($workflow);

        return $workflow->isStartTransitionAvailable($transition, $entity, $data, $errors);
    }

    /**
     * Perform reset of workflow item data - set $workflowItem and $workflowStep references into null
     * and remove workflow item. If active workflow definition has a start step,
     * then active workflow will be started automatically.
     *
     * @param WorkflowItem $workflowItem
     * @return WorkflowItem|null workflowItem for workflow definition with a start step, null otherwise
     * @throws \Exception
     */
    public function resetWorkflowItem(WorkflowItem $workflowItem)
    {
        $activeWorkflowItem = null;
        $entity = $workflowItem->getEntity();

        /** @var EntityManager $em */
        $em = $this->registry->getManagerForClass('OroWorkflowBundle:WorkflowItem');
        $em->beginTransaction();

        try {
            $this->getWorkflow($workflowItem)->resetWorkflowData($entity);
            $em->remove($workflowItem);
            $em->flush();

            $activeWorkflow = $this->getApplicableWorkflow($entity);
            if ($activeWorkflow->getStepManager()->hasStartStep()) {
                $activeWorkflowItem = $this->startWorkflow($activeWorkflow->getName(), $entity);
            }

            $em->commit();
        } catch (\Exception $e) {
            $em->rollback();
            throw $e;
        }

        return $activeWorkflowItem;
    }

    /**
     * @param string $workflow
     * @param object $entity
     * @param string|Transition|null $transition
     * @param array $data
     * @return WorkflowItem
     * @throws \Exception
     */
    public function startWorkflow($workflow, $entity, $transition = null, array $data = array())
    {
        $workflow = $this->getWorkflow($workflow);

        /** @var EntityManager $em */
        $em = $this->registry->getManager();
        $em->beginTransaction();
        try {
            $workflowItem = $workflow->start($entity, $data, $transition);
            $em->persist($workflowItem);
            $em->flush();
            $em->commit();
        } catch (\Exception $e) {
            $em->rollback();
            throw $e;
        }

        return $workflowItem;
    }

    /**
     * Start several workflows in one transaction
     *
     * Input data format:
     * array(
     *      array(
     *          'workflow'   => <workflow identifier: string|Workflow>,
     *          'entity'     => <entity used in workflow: object>,
     *          'transition' => <start transition name: string>,     // optional
     *          'data'       => <additional workflow data : array>,  // optional
     *      ),
     *      ...
     * )
     *
     * @param array $data
     * @throws \Exception
     */
    public function massStartWorkflow(array $data)
    {
        /** @var EntityManager $em */
        $em = $this->registry->getManager();
        $em->beginTransaction();
        try {
            foreach ($data as $row) {
                if (empty($row['workflow']) || empty($row['entity'])) {
                    continue;
                }

                $workflow = $this->getWorkflow($row['workflow']);
                $entity = $row['entity'];
                $transition = !empty($row['transition']) ? $row['transition'] : null;
                $data = !empty($row['data']) ? $row['data'] : array();

                $workflowItem = $workflow->start($entity, $data, $transition);
                $em->persist($workflowItem);
            }

            $em->flush();
            $em->commit();
        } catch (\Exception $e) {
            $em->rollback();
            throw $e;
        }
    }

    /**
     * Perform workflow item transition.
     *
     * @param WorkflowItem $workflowItem
     * @param string|Transition $transition
     * @throws \Exception
     */
    public function transit(WorkflowItem $workflowItem, $transition)
    {
        $workflow = $this->getWorkflow($workflowItem);
        /** @var EntityManager $em */
        $em = $this->registry->getManager();
        $em->beginTransaction();
        try {
            $workflow->transit($workflowItem, $transition);
            $workflowItem->setUpdated();
            $em->flush();
            $em->commit();
        } catch (\Exception $e) {
            $em->rollback();
            throw $e;
        }
    }

    /**
     * @param object $entity
     * @return Workflow
     */
    public function getApplicableWorkflow($entity)
    {
        return $this->getApplicableWorkflowByEntityClass(
            $this->doctrineHelper->getEntityClass($entity)
        );
    }

    /**
     * @param string $entityClass
     * @return null|Workflow
     */
    public function getApplicableWorkflowByEntityClass($entityClass)
    {
        return $this->workflowRegistry->getActiveWorkflowByEntityClass($entityClass);
    }

    /**
     * @param string $entityClass
     * @return bool
     */
    public function hasApplicableWorkflowByEntityClass($entityClass)
    {
        return $this->workflowRegistry->hasActiveWorkflowByEntityClass($entityClass);
    }

    /**
     * @param object $entity
     * @return WorkflowItem|null
     */
    public function getWorkflowItemByEntity($entity)
    {
        $entityClass = $this->doctrineHelper->getEntityClass($entity);
        $entityIdentifier = $this->doctrineHelper->getSingleEntityIdentifier($entity);

        if (false === filter_var($entityIdentifier, FILTER_VALIDATE_INT)) {
            return null;
        }

        return $this->getWorkflowItemRepository()->findByEntityMetadata($entityClass, $entityIdentifier);
    }

    /**
     * Get workflow instance.
     *
     * string - workflow name
     * WorkflowItem - getWorkflowName() method will be used to get workflow
     * Workflow - will be returned by itself
     *
     * @param string|Workflow|WorkflowItem $workflowIdentifier
     * @throws WorkflowException
     * @return Workflow
     */
    public function getWorkflow($workflowIdentifier)
    {
        if (is_string($workflowIdentifier)) {
            return $this->workflowRegistry->getWorkflow($workflowIdentifier);
        } elseif ($workflowIdentifier instanceof WorkflowItem) {
            return $this->workflowRegistry->getWorkflow($workflowIdentifier->getWorkflowName());
        } elseif ($workflowIdentifier instanceof Workflow) {
            return $workflowIdentifier;
        }

        throw new WorkflowException('Can\'t find workflow by given identifier.');
    }

    /**
     * @param string|Workflow|WorkflowItem|WorkflowDefinition $workflowIdentifier
     */
    public function activateWorkflow($workflowIdentifier)
    {
        if ($workflowIdentifier instanceof WorkflowDefinition) {
            $entityClass = $workflowIdentifier->getRelatedEntity();
            $workflowName = $workflowIdentifier->getName();
        } else {
            $workflow = $this->getWorkflow($workflowIdentifier);
            $entityClass = $workflow->getDefinition()->getRelatedEntity();
            $workflowName = $workflow->getName();
        }

        $this->setActiveWorkflow($entityClass, $workflowName);
    }

    /**
     * @param string $entityClass
     */
    public function deactivateWorkflow($entityClass)
    {
        $this->setActiveWorkflow($entityClass, null);
    }

    /**
     * @param WorkflowDefinition $workflowDefinition
     */
    public function resetWorkflowData(WorkflowDefinition $workflowDefinition)
    {
        $this->getWorkflowItemRepository()->resetWorkflowData(
            $workflowDefinition->getRelatedEntity(),
            array($workflowDefinition->getName())
        );
    }

    /**
     * @param string $entityClass
     * @param string|null $workflowName
     */
    protected function setActiveWorkflow($entityClass, $workflowName)
    {
        $entityConfig = $this->getEntityConfig($entityClass);
        $entityConfig->set('active_workflow', $workflowName);
        $this->persistEntityConfig($entityConfig);
    }

    /**
     * @param $entityClass
     * @return ConfigInterface
     * @throws WorkflowException
     */
    protected function getEntityConfig($entityClass)
    {
        $workflowConfigProvider = $this->configManager->getProvider('workflow');
        if (!$workflowConfigProvider->hasConfig($entityClass)) {
            throw new WorkflowException(sprintf('Entity %s is not configurable', $entityClass));
        }

        return $workflowConfigProvider->getConfig($entityClass);
    }

    /**
     * Check that entity workflow item is equal to the active workflow item.
     *
     * @param object $entity
     * @return bool
     */
    public function isResetAllowed($entity)
    {
        $currentWorkflowItem = $this->getWorkflowItemByEntity($entity);
        $activeWorkflow      = $this->getApplicableWorkflow($entity);

        return $activeWorkflow && $currentWorkflowItem &&
               $currentWorkflowItem->getWorkflowName() !== $activeWorkflow->getName();
    }

    /**
     * @param ConfigInterface $entityConfig
     */
    protected function persistEntityConfig(ConfigInterface $entityConfig)
    {
        $this->configManager->persist($entityConfig);
        $this->configManager->flush();
    }

    /**
     * @return WorkflowItemRepository
     */
    protected function getWorkflowItemRepository()
    {
        return $this->registry->getRepository('OroWorkflowBundle:WorkflowItem');
    }
}

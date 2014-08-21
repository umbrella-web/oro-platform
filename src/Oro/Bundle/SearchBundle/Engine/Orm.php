<?php

namespace Oro\Bundle\SearchBundle\Engine;

use Doctrine\Common\Util\ClassUtils as DoctrineClassUtils;
use Doctrine\ORM\EntityManager;

use JMS\JobQueueBundle\Entity\Job;

use Oro\Bundle\BatchBundle\ORM\Query\BufferedQueryResultIterator;
use Oro\Bundle\SearchBundle\Entity\Repository\SearchIndexRepository;
use Oro\Bundle\SearchBundle\Query\Query;
use Oro\Bundle\SearchBundle\Query\Result\Item as ResultItem;
use Oro\Bundle\SearchBundle\Entity\Item;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Util\ClassUtils;

class Orm extends AbstractEngine
{
    const BATCH_SIZE = 1000;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var SearchIndexRepository
     */
    protected $searchRepo;

    /**
     * @var ObjectMapper
     */
    protected $mapper;

    public function __construct(
        EntityManager $em,
        EventDispatcher $dispatcher,
        ContainerInterface $container,
        ObjectMapper $mapper,
        $logQueries
    ) {
        parent::__construct($em, $dispatcher, $logQueries);

        $this->container = $container;
        $this->mapper    = $mapper;
    }

    /**
     * Reload search index
     *
     * @return int Count of index records
     */
    public function reindex()
    {
        //clear old index
        $this->clearSearchIndex();

        //index data by mapping config
        $recordsCount = 0;
        $entities     = $this->mapper->getEntities();

        while ($entityName = array_shift($entities)) {
            $itemsCount    = $this->reindexSingleEntity($entityName);
            $recordsCount += $itemsCount;
        }

        return $recordsCount;
    }

    /**
     * @param string $entityName
     * @return int
     */
    protected function reindexSingleEntity($entityName)
    {
        $itemsCount   = 0;
        $queryBuilder = $this->em->getRepository($entityName)->createQueryBuilder('entity');

        $iterator = new BufferedQueryResultIterator($queryBuilder);
        $iterator->setBufferSize(self::BATCH_SIZE);

        foreach ($iterator as $entity) {
            if (null !== $this->save($entity, true)) {
                $itemsCount++;
            }

            if (0 == $itemsCount % self::BATCH_SIZE) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        if ($itemsCount % self::BATCH_SIZE > 0) {
            $this->em->flush();
            $this->em->clear();
        }

        return $itemsCount;
    }

    /**
     * Delete record from index
     *
     * @param object $entity   Entity to be removed from index
     * @param bool   $realtime [optional] Perform immediate insert/update to
     *                              search attributes table(s). True by default.
     * @return bool|int Index item id on success, false otherwise
     */
    public function delete($entity, $realtime = true)
    {
        $item = $this->getIndexRepo()->findOneBy(
            array(
                'entity'   => ClassUtils::getRealClass($entity),
                'recordId' => $entity->getId()
            )
        );

        if ($item) {
            $id = $item->getId();

            if ($realtime) {
                $this->em->remove($item);
            } else {
                $item->setChanged(!$realtime);
                $this->reindexJob();
                $this->em->persist($item);
            }

            return $id;
        }

        return false;
    }

    /**
     * Insert or update record
     *
     * @param object $entity   New/updated entity
     * @param bool   $realtime [optional] Perform immediate insert/update to
     *                              search attributes table(s). True by default.
     * @param bool   $needToCompute
     * @return Item Index item on success, null otherwise
     */
    public function save($entity, $realtime = true, $needToCompute = false)
    {
        $data = $this->mapper->mapObject($entity);
        if (empty($data)) {
            return null;
        }

        $name = ClassUtils::getRealClass($entity);
        $entityMeta = $this->em->getClassMetadata($name);
        $identifierField = $entityMeta->getSingleIdentifierFieldName($entityMeta);
        $id = $entityMeta->getReflectionProperty($identifierField)->getValue($entity);

        $item = null;
        if ($id) {
            $item = $this->getIndexRepo()->findOneBy(
                array(
                    'entity'   => $name,
                    'recordId' => $id
                )
            );
        }

        if (!$item) {
            $item   = new Item();
            $config = $this->mapper->getEntityConfig($name);
            $alias  = $config ? $config['alias'] : $name;

            $item->setEntity($name)
                 ->setRecordId($id)
                 ->setAlias($alias);
        }

        $item->setChanged(!$realtime);

        if ($realtime) {
            $item->setTitle($this->getEntityTitle($entity))
                ->saveItemData($data);
        } else {
            $this->reindexJob();
        }

        $this->em->persist($item);

        if ($needToCompute) {
            $this->computeSet($item);
        }

        return $item;
    }

    /**
     * @return \Oro\Bundle\SearchBundle\Engine\ObjectMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * Get entity string
     *
     * @param object $entity
     *
     * @return string
     */
    public function getEntityTitle($entity)
    {
        $entityClass = ClassUtils::getRealClass($entity);
        if ($this->mapper->getEntityMapParameter($entityClass, 'title_fields')) {
            $fields = $this->mapper->getEntityMapParameter($entityClass, 'title_fields');
            $title = array();
            foreach ($fields as $field) {
                $title[] = $this->mapper->getFieldValue($entity, $field);
            }
        } else {
            $title = array((string) $entity);
        }

        return implode(' ', $title);
    }

    /**
     * Search query with query builder
     *
     * @param \Oro\Bundle\SearchBundle\Query\Query $query
     *
     * @return array
     */
    protected function doSearch(Query $query)
    {
        $results = array();
        $searchResults = $this->getIndexRepo()->search($query);
        if (($query->getMaxResults() > 0 || $query->getFirstResult() > 0)) {
            $recordsCount = $this->getIndexRepo()->getRecordsCount($query);
        } else {
            $recordsCount = count($searchResults);
        }
        if ($searchResults) {
            foreach ($searchResults as $item) {
                if (is_array($item)) {
                    $item = $item['item'];
                }
                /** @var $item \Oro\Bundle\SearchBundle\Entity\Item  */
                $results[] = new ResultItem(
                    $this->em,
                    $item->getEntity(),
                    $item->getRecordId(),
                    $item->getTitle(),
                    null,
                    $item->getRecordText(),
                    $this->mapper->getEntityConfig($item->getEntity())
                );
            }
        }

        return array(
            'results' => $results,
            'records_count' => $recordsCount
        );
    }

    /**
     * Add reindex task to job queue if it has not been added earlier
     */
    protected function reindexJob()
    {
        // check if reindex task has not been added earlier
        $command = 'oro:search:index';
        $currJob = $this->em
            ->createQuery("SELECT j FROM JMSJobQueueBundle:Job j WHERE j.command = :command AND j.state <> :state")
            ->setParameter('command', $command)
            ->setParameter('state', Job::STATE_FINISHED)
            ->setMaxResults(1)
            ->getOneOrNullResult();

        if (!$currJob) {
            $job = new Job($command);

            $this->em->persist($job);
        }
    }

    /**
     * Get search index repository
     *
     * @return \Oro\Bundle\SearchBundle\Entity\Repository\SearchIndexRepository
     */
    protected function getIndexRepo()
    {
        if (!is_object($this->searchRepo)) {
            $this->searchRepo = $this->em->getRepository('OroSearchBundle:Item');
            $this->searchRepo->setDriversClasses($this->container->getParameter('oro_search.engine_orm'));
        }

        return $this->searchRepo;
    }

    /**
     * Truncate search tables
     */
    protected function clearSearchIndex()
    {
        $connection = $this->em->getConnection();
        $dbPlatform = $connection->getDatabasePlatform();
        $connection->beginTransaction();
        try {
            $connection->query('SET FOREIGN_KEY_CHECKS=0');
            $this->truncate($dbPlatform, $connection, 'OroSearchBundle:Item');
            $this->truncate($dbPlatform, $connection, 'OroSearchBundle:IndexDecimal');
            $this->truncate($dbPlatform, $connection, 'OroSearchBundle:IndexText');
            $this->truncate($dbPlatform, $connection, 'OroSearchBundle:IndexInteger');
            $this->truncate($dbPlatform, $connection, 'OroSearchBundle:IndexDatetime');
            $connection->query('SET FOREIGN_KEY_CHECKS=1');
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
        }

    }

    /**
     * Truncate query for table
     *
     * @param $dbPlatform
     * @param $connection
     * @param $table
     */
    protected function truncate($dbPlatform, $connection, $table)
    {
        $cmd = $this->em->getClassMetadata($table);
        $q = $dbPlatform->getTruncateTableSql($cmd->getTableName());
        $connection->executeUpdate($q);
    }

    /**
     * @param Item $item
     */
    protected function computeSet(Item $item)
    {
        $this->em->getUnitOfWork()->computeChangeSet(
            $this->em->getClassMetadata(DoctrineClassUtils::getClass($item)),
            $item
        );
        $this->computeFields($item->getTextFields());
        $this->computeFields($item->getIntegerFields());
        $this->computeFields($item->getDatetimeFields());
        $this->computeFields($item->getDecimalFields());
    }

    /**
     * @param array $fields
     */
    protected function computeFields($fields)
    {
        if (count($fields)) {
            foreach ($fields as $field) {
                $this->em->getUnitOfWork()->computeChangeSet(
                    $this->em->getClassMetadata(DoctrineClassUtils::getClass($field)),
                    $field
                );
            }
        }
    }
}

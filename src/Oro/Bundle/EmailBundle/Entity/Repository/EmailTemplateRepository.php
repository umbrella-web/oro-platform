<?php

namespace Oro\Bundle\EmailBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\EmailBundle\Entity\EmailTemplate;

class EmailTemplateRepository extends EntityRepository
{
    /**
     * Gets a template by its name
     * This method can return null if the requested template does not exist
     *
     * @param string $templateName
     * @return EmailTemplate|null
     */
    public function findByName($templateName)
    {
        return $this->findOneBy(array('name' => $templateName));
    }

    /**
     * Load templates by entity name
     *
     * @param $entityName
     * @return EmailTemplate[]
     */
    public function getTemplateByEntityName($entityName)
    {
        return $this->findBy(array('entityName' => $entityName));
    }

    /**
     * Return templates query builder filtered by entity name
     *
     * @param string $entityName    entity class
     * @param bool   $includeSystem if true - system templates will be included in result set
     *
     * @return QueryBuilder
     */
    public function getEntityTemplatesQueryBuilder($entityName, $includeSystem = false)
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.entityName = :entityName')
            ->orderBy('e.name', 'ASC')
            ->setParameter('entityName', $entityName);

        if ($includeSystem) {
            $qb->orWhere('e.entityName IS NULL');
        }

        return $qb;
    }

    /**
     * Return a query builder which can be used to get names of entities
     * which have at least one email template
     *
     * @return QueryBuilder
     */
    public function getDistinctByEntityNameQueryBuilder()
    {
        return $this->createQueryBuilder('e')
            ->select('e.entityName')
            ->distinct();
    }
}

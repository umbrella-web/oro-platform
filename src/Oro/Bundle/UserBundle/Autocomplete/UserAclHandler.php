<?php

namespace Oro\Bundle\UserBundle\Autocomplete;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\AttachmentBundle\Manager\AttachmentManager;
use Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink;
use Oro\Bundle\FormBundle\Autocomplete\SearchHandlerInterface;
use Oro\Bundle\LocaleBundle\Formatter\NameFormatter;

use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Acl\Domain\OneShotIsGrantedObserver;
use Oro\Bundle\SecurityBundle\Acl\Voter\AclVoter;
use Oro\Bundle\SecurityBundle\ORM\Walker\OwnershipConditionDataBuilder;
use Oro\Bundle\SecurityBundle\Owner\OwnerTreeProvider;

/**
 * Autocomplete search handler for users with ACL access level protection
 *
 * Class UserAclHandler
 * @package Oro\Bundle\UserBundle\Autocomplete
 */
class UserAclHandler implements SearchHandlerInterface
{
    /** @var EntityManager */
    protected $em;

    /** @var AttachmentManager */
    protected $attachmentManager;

    /** @var string */
    protected $className;

    /** @var array */
    protected $fields;

    /** @var NameFormatter */
    protected $nameFormatter;

    /** @var AclVoter */
    protected $aclVoter;

    /** @var OwnershipConditionDataBuilder */
    protected $builder;

    /** @var ServiceLink */
    protected $securityContextLink;

    /** @var OwnerTreeProvider */
    protected $treeProvider;

    /**
     * @param EntityManager     $em
     * @param AttachmentManager $attachmentManager
     * @param string            $className
     * @param array             $fields
     * @param ServiceLink       $securityContextLink
     * @param OwnerTreeProvider $treeProvider
     * @param AclVoter          $aclVoter
     */
    public function __construct(
        EntityManager $em,
        AttachmentManager $attachmentManager,
        $className,
        $fields,
        ServiceLink $securityContextLink,
        OwnerTreeProvider $treeProvider,
        AclVoter $aclVoter = null
    ) {
        $this->em                  = $em;
        $this->attachmentManager   = $attachmentManager;
        $this->className           = $className;
        $this->fields              = $fields;
        $this->aclVoter            = $aclVoter;
        $this->securityContextLink = $securityContextLink;
        $this->treeProvider        = $treeProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function search($query, $page, $perPage, $searchById = false)
    {
        list ($search, $entityClass, $permission, $entityId, $excludeCurrentUser) = explode(';', $query);
        $entityClass = str_replace('_', '\\', $entityClass);

        if ($entityId) {
            $object = $this->em->getRepository($entityClass)->find((int)$entityId);
        } else {
            $object = 'entity:' . $entityClass;
        }

        $observer = new OneShotIsGrantedObserver();
        $this->aclVoter->addOneShotIsGrantedObserver($observer);
        $isGranted = $this->getSecurityContext()->isGranted($permission, $object);

        if ($isGranted) {
            $results = [];
            if ($searchById) {
                $results[] = $this->em->getRepository('OroUserBundle:User')->find((int)$query);
            } else {
                $user         = $this->getSecurityContext()->getToken()->getUser();
                $queryBuilder = $this->getSearchQueryBuilder($search);
                $this->addAcl($queryBuilder, $observer->getAccessLevel(), $user);
                if ((boolean) $excludeCurrentUser) {
                    $this->excludeUser($queryBuilder, $user);
                }
                $results = $queryBuilder->getQuery()->getResult();
            }

            $resultsData = [];
            foreach ($results as $user) {
                $resultsData[] = $this->convertItem($user);
            }
        } else {
            $resultsData = [];
        }

        return [
            'results' => $resultsData,
            'more'    => false
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties()
    {
        return $this->fields;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityName()
    {
        return $this->className;
    }

    /**
     * @param NameFormatter $nameFormatter
     */
    public function setNameFormatter(NameFormatter $nameFormatter)
    {
        $this->nameFormatter = $nameFormatter;
    }

    /**
     * {@inheritdoc}
     */
    public function convertItem($user)
    {
        $result = [];
        foreach ($this->fields as $field) {
            $result[$field] = $this->getPropertyValue($field, $user);
        }
        $result['avatar'] = null;

        $avatar = $this->getPropertyValue('avatar', $user);
        if ($avatar) {
            $result['avatar'] = $this->attachmentManager->getFilteredImageUrl(
                $avatar,
                UserSearchHandler::IMAGINE_AVATAR_FILTER
            );
        }

        if (!$this->nameFormatter) {
            throw new \RuntimeException('Name formatter must be configured');
        }
        $result['fullName'] = $this->nameFormatter->format($user);

        return $result;
    }

    /**
     * @param string       $name
     * @param object|array $item
     * @return mixed
     */
    protected function getPropertyValue($name, $item)
    {
        $result = null;

        if (is_object($item)) {
            $method = 'get' . str_replace(' ', '', str_replace('_', ' ', ucwords($name)));
            if (method_exists($item, $method)) {
                $result = $item->$method();
            } elseif (isset($item->$name)) {
                $result = $item->$name;
            }
        } elseif (is_array($item) && array_key_exists($name, $item)) {
            $result = $item[$name];
        }

        return $result;
    }

    /**
     * Get search users query builder
     *
     * @param $search
     * @return QueryBuilder
     */
    protected function getSearchQueryBuilder($search)
    {
        /** @var \Doctrine\ORM\QueryBuilder $queryBuilder */
        $queryBuilder = $this->em->createQueryBuilder();
        $queryBuilder
            ->select(['users'])
            ->from('Oro\Bundle\UserBundle\Entity\User', 'users')
            ->add(
                'where',
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like(
                        $queryBuilder->expr()->concat(
                            'users.firstName',
                            $queryBuilder->expr()->concat(
                                $queryBuilder->expr()->literal(' '),
                                'users.lastName'
                            )
                        ),
                        '?1'
                    ),
                    $queryBuilder->expr()->like(
                        $queryBuilder->expr()->concat(
                            'users.lastName',
                            $queryBuilder->expr()->concat(
                                $queryBuilder->expr()->literal(' '),
                                'users.firstName'
                            )
                        ),
                        '?1'
                    ),
                    $queryBuilder->expr()->like('users.username', '?1')
                )
            )
            ->setParameter(1, '%' . str_replace(' ', '%', $search) . '%');
        return $queryBuilder;
    }

    /**
     * Add ACL Check condition to the Query Builder
     *
     * @param QueryBuilder  $queryBuilder
     * @param               $accessLevel
     * @param UserInterface $user
     */
    protected function addAcl(QueryBuilder $queryBuilder, $accessLevel, UserInterface $user)
    {
        if ($accessLevel == AccessLevel::BASIC_LEVEL) {
            $queryBuilder->andWhere($queryBuilder->expr()->in('users.id', [$user->getId()]));
        } elseif ($accessLevel !== AccessLevel::SYSTEM_LEVEL) {
            if ($accessLevel == AccessLevel::LOCAL_LEVEL) {
                $resultBuIds = $this->treeProvider->getTree()->getUserBusinessUnitIds($user->getId());
            } elseif ($accessLevel == AccessLevel::DEEP_LEVEL) {
                $resultBuIds = $this->treeProvider->getTree()->getUserSubordinateBusinessUnitIds($user->getId());
            } elseif ($accessLevel == AccessLevel::GLOBAL_LEVEL) {
                $resultBuIds = $this->treeProvider->getTree()->getBusinessUnitsIdByUserOrganizations($user->getId());
            }
            $queryBuilder->join('users.owner', 'bu')
                ->andWhere($queryBuilder->expr()->in('bu.id', $resultBuIds));
        }
    }

    /**
     * @return SecurityContextInterface
     */
    protected function getSecurityContext()
    {
        return $this->securityContextLink->getService();
    }

    /**
     * Adds a condition excluding user from the list
     *
     * @param QueryBuilder $queryBuilder
     * @param UserInterface $user
     */
    protected function excludeUser(QueryBuilder $queryBuilder, UserInterface $user)
    {
        $queryBuilder->andWhere('users.id != :userId');
        $queryBuilder->setParameter('userId', $user->getId());
    }
}

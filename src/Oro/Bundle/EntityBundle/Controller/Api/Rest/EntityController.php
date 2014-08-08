<?php

namespace Oro\Bundle\EntityBundle\Controller\Api\Rest;

use Symfony\Component\HttpFoundation\Response;

use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\Rest\Util\Codes;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Oro\Bundle\SoapBundle\Form\Handler\ApiFormHandler;

use Oro\Bundle\EntityBundle\Provider\EntityProvider;
use Oro\Bundle\EntityBundle\Exception\InvalidEntityException;
use Oro\Bundle\EntityBundle\Provider\EntityWithFieldsProvider;
use Oro\Bundle\SoapBundle\Controller\Api\Rest\RestController;

/**
 * @RouteResource("entity")
 * @NamePrefix("oro_api_")
 */
class EntityController extends RestController implements ClassResourceInterface
{
    /**
     * The property is needed to access entity class name in all controller methods
     * @var string
     */
    protected $className;
    
    /**
     * @var \Oro\Bundle\SoapBundle\Entity\Manager\ApiEntityManager 
     */
    protected $entityManager;
    
    /**
     * @var \Symfony\Component\Form\FormInterface 
     */
    protected $form;
    
    /**
     * @var \Oro\Bundle\EntityBundle\Form\Handler\CustomEntityApiHandler 
     */
    protected $formHandler;
    
    /**
     * Get entities.
     *
     * @QueryParam(
     *      name="apply-exclusions", requirements="(1)|(0)", nullable=true, strict=true, default="1",
     *      description="Indicates whether exclusion logic should be applied.")
     *
     * @ApiDoc(
     *      description="Get entities",
     *      resource=true
     * )
     *
     * @return Response
     */
    public function cgetAction()
    {
        $applyExclusions     = ('1' == $this->getRequest()->query->get('apply-exclusions'));

        /** @var EntityProvider $provider */
        $provider = $this->get('oro_entity.entity_provider');
        $result = $provider->getEntities(false, $applyExclusions);

        return $this->handleView($this->view($result, Codes::HTTP_OK));
    }

    /**
     * Get entities with fields
     *
     * @QueryParam(
     *      name="with-virtual-fields", requirements="(1)|(0)", nullable=true, strict=true, default="0",
     *      description="Indicates whether virtual fields should be returned as well.")
     * @QueryParam(
     *      name="with-relations", requirements="(1)|(0)", nullable=true, strict=true, default="0",
     *      description="Indicates whether association fields should be returned as well.")
     * @QueryParam(
     *      name="with-unidirectional", requirements="(1)|(0)", nullable=true, strict=true, default="0",
     *      description="Indicates whether Unidirectional association fields should be returned.")
     * @QueryParam(
     *      name="apply-exclusions", requirements="(1)|(0)", nullable=true, strict=true, default="1",
     *      description="Indicates whether exclusion logic should be applied.")
     * @ApiDoc(
     *      description="Get entities with fields",
     *      resource=true
     * )
     * @Get(name="oro_api_fields_entity")
     *
     * @return Response
     */
    public function fieldsAction()
    {
        $withRelations      = ('1' == $this->getRequest()->query->get('with-relations'));
        $withUnidirectional = ('1' == $this->getRequest()->query->get('with-unidirectional'));
        $withVirtualFields  = ('1' == $this->getRequest()->query->get('with-virtual-fields'));
        $applyExclusions    = ('1' == $this->getRequest()->query->get('apply-exclusions'));

        /** @var EntityWithFieldsProvider $provider */
        $provider = $this->get('oro_entity.entity_field_list_provider');

        $statusCode = Codes::HTTP_OK;
        try {
            $result = $provider->getFields(
                $withVirtualFields,
                $withUnidirectional,
                $withRelations,
                $applyExclusions
            );
        } catch (InvalidEntityException $ex) {
            $statusCode = Codes::HTTP_NOT_FOUND;
            $result     = array('message' => $ex->getMessage());
        }

        return $this->handleView($this->view($result, $statusCode));
    }
    
    /**
     * REST GET list of custom entity records
     *
     * @param string $entityName Entity full class name; backslashes (\) should be replaced with underscore (_).
     *
     * @QueryParam(
     *      name="page",
     *      requirements="\d+",
     *      nullable=true,
     *      description="Page number, starting from 1. Defaults to 1."
     * )
     * @QueryParam(
     *      name="limit",
     *      requirements="\d+",
     *      nullable=true,
     *      description="Number of items per page. defaults to 10."
     * )
     * @ApiDoc(
     *      description="Get list of custom entity records",
     *      resource=true
     * )
     * @return Response
     */
    public function cgetRecordsAction($entityName)
    {
        $this->className = str_replace('_', '\\', $entityName);

        $page = (int) $this->getRequest()->get('page', 1);
        $limit = (int) $this->getRequest()->get('limit', self::ITEMS_PER_PAGE);

        try
        {
            return $this->handleGetListRequest($page, $limit);
        }
        catch (InvalidEntityException $ex)
        {
            return $this->handleView($this->view(array('message' => $ex->getMessage()), Codes::HTTP_NOT_FOUND));
        }
    }
    
    /**
     * REST GET entity data by entity class name & id
     *
     * @param string $entityName Entity full class name; backslashes (\) should be replaced with underscore (_).
     * @param int $id Entity id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @ApiDoc(
     *      description="Get entity data",
     *      resource=true,
     *      requirements={
     *          {"name"="id", "dataType"="integer"},
     *      }
     * )
     */
    public function getRecordAction($entityName, $id)
    {
        $this->className = str_replace('_', '\\', $entityName);
        
        try {
            return $this->handleGetRequest($id);
        } catch (InvalidEntityException $ex) {
            return $this->handleView($this->view(array('message' => $ex->getMessage()), Codes::HTTP_NOT_FOUND));
        }
    }
    
    /**
     * REST POST Create new entity record
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @ApiDoc(
     *      description="Create new entity record",
     *      resource=true
     * )
     */
    public function postRecordAction($entityName)
    {
        $this->className = str_replace('_', '\\', $entityName);
        try
        {
            return $this->handleCreateRequest();
        }
        catch (InvalidEntityException $ex)
        {
            return $this->handleView($this->view(array('message' => $ex->getMessage()), Codes::HTTP_NOT_FOUND));
        }
    }

    /**
     * REST DELETE entity record
     *
     * @param string $entityName Custom entity full class name; backslashes (\) should be replaced with underscore (_).
     * @param int $id Custom entity record id
     *
     * @ApiDoc(
     *      description="Delete custom entity record",
     *      resource=true
     * )
     * @return Response
     */
    public function deleteRecordAction($entityName, $id)
    {
        $this->className = str_replace('_', '\\', $entityName);
        try
        {
            return $this->handleDeleteRequest($id);
        }
        catch (InvalidEntityException $ex)
        {
            return $this->handleView($this->view(array('message' => $ex->getMessage()), Codes::HTTP_NOT_FOUND));
        }
    }

    /**
     * @return ApiFormHandler
     */
    public function getFormHandler()
    {
        if(!$this->formHandler)
            $this->formHandler = new \Oro\Bundle\EntityBundle\Form\Handler\CustomEntityApiHandler($this->getForm(), $this->getRequest(), $this->getDoctrine()->getManager()); 
        
        return $this->formHandler;
    }
    
    /**
     * @return \Symfony\Component\Form\FormInterface 
     */
    public function getForm()
    {
        if(!$this->form)
            $this->form = $this->createForm(new \Oro\Bundle\EntityBundle\Form\Type\CustomEntityApiType(), null, array(
                'data_class' => $this->className,
                'config_manager' => $this->get('oro_entity_config.config_manager'),
                ));

        return $this->form;
    }
    
    /**
     * Get entity Manager
     *
     * @return \Oro\Bundle\SoapBundle\Entity\Manager\ApiEntityManager
     */
    public function getManager()
    {
        if(!class_exists($this->className))
            throw new InvalidEntityException(sprintf('The "%s" entity was not found.', $this->className));
        
        if(!$this->entityManager)
            $this->entityManager = new \Oro\Bundle\SoapBundle\Entity\Manager\ApiEntityManager($this->className, $this->getDoctrine()->getManager());
        
        return $this->entityManager;
    }
}

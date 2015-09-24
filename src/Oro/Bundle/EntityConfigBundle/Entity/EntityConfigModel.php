<?php

namespace Oro\Bundle\EntityConfigBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

use Oro\Bundle\EntityConfigBundle\Config\ConfigModelManager;
use Oro\Bundle\EntityConfigBundle\Tools\ConfigHelper;

/**
 * @ORM\Entity(repositoryClass="Oro\Bundle\EntityConfigBundle\Entity\Repository\EntityConfigRepository")
 * @ORM\Table(name="oro_entity_config",
 *      uniqueConstraints={@ORM\UniqueConstraint(name="oro_entity_config_uq", columns={"class_name"})})
 * @ORM\HasLifecycleCallbacks()
 */
class EntityConfigModel extends AbstractConfigModel
{
    const ENTITY_NAME = 'OroEntityConfigBundle:EntityConfigModel';

    /**
     * @var integer
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * IMPORTANT: do not modify this collection manually. addToIndex and removeFromIndex should be used
     *
     * @var ArrayCollection|ConfigModelIndexValue[]
     * @ORM\OneToMany(targetEntity="ConfigModelIndexValue", mappedBy="entity", cascade={"all"})
     */
    protected $indexedValues;

    /**
     * @var ArrayCollection|FieldConfigModel[]
     * @ORM\OneToMany(targetEntity="FieldConfigModel", mappedBy="entity", cascade={"all"})
     */
    protected $fields;

    /**
     * @var string
     * @ORM\Column(name="class_name", type="string", length=255)
     */
    protected $className;

    /**
     * @param string|null $className
     */
    public function __construct($className = null)
    {
        $this->mode          = ConfigModelManager::MODE_DEFAULT;
        $this->fields        = new ArrayCollection();
        $this->indexedValues = new ArrayCollection();

        $this->setClassName($className);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $className
     * @return $this
     */
    public function setClassName($className)
    {
        $this->className = $className;

        list($moduleName, $entityName) = ConfigHelper::getModuleAndEntityNames($className);
        $this->addToIndex('entity_config', 'module_name', $moduleName);
        $this->addToIndex('entity_config', 'entity_name', $entityName);

        return $this;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param ArrayCollection $fields
     * @return $this
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @param FieldConfigModel $field
     * @return $this
     */
    public function addField($field)
    {
        $field->setEntity($this);
        $this->fields->add($field);

        return $this;
    }

    public function removeField($field)
    {
        $this->fields->removeElement($field);

        return $this;
    }

    /**
     * @param  callable $filter function (FieldConfigModel $model)
     * @return ArrayCollection|FieldConfigModel[]
     */
    public function getFields(\Closure $filter = null)
    {
        return $filter ? $this->fields->filter($filter) : $this->fields;
    }

    /**
     * @param $fieldName
     * @return FieldConfigModel
     */
    public function getField($fieldName)
    {
        $fields = $this->getFields(
            function (FieldConfigModel $field) use ($fieldName) {
                return $field->getFieldName() == $fieldName;
            }
        );

        return $fields->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexedValues()
    {
        return $this->indexedValues;
    }

    /**
     * {@inheritdoc}
     */
    protected function createIndexedValue($scope, $code, $value)
    {
        $result = new ConfigModelIndexValue($scope, $code, $value);
        $result->setEntity($this);

        return $result;
    }
}

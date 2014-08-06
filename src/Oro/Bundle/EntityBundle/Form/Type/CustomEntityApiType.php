<?php

namespace Oro\Bundle\EntityBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

class CustomEntityApiType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $extendConfigProvider = $options['config_manager']->getProvider('extend');
        
        $extendConfigs = $extendConfigProvider->getConfigs($options['data_class']);
        foreach ($extendConfigs as $config) {
            if (!$config->is('is_deleted'))
            {
                $builder->add($config->getId()->getFieldName());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        parent::setDefaultOptions($resolver);
        
        $resolver->setRequired(array(
            'config_manager', 
            'data_class'
        ));
        
        $resolver->setAllowedTypes(array(
            'config_manager' => '\Oro\Bundle\EntityConfigBundle\Config\ConfigManager',
        ));

        $resolver->setDefaults(
            array(
                'csrf_protection' => false,
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return null;
    }
}

<?php

namespace Drupal\ice_cream\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\ice_cream\Services\IceFormBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigurationController extends ControllerBase{

    private $formbuilder;

    /**
     * ConfigurationController constructor.
     * @param FormBuilder $formBuilder
     */
    public function __construct(FormBuilder $formBuilder) {
        $this->formbuilder = $formBuilder;
    }

    /**
     * @return array
     *  returns the builded form that was requested
     */
    public function setThreshold() {
        $form = $this->formbuilder->getForm('Drupal\ice_cream\Form\ConfigurationForm');
        return $form;
    }

    /**
     * @param ContainerInterface $container
     * @return static
     *  returns the new instance from formbuilder 
     */
    public static function create(ContainerInterface $container) {
        $formBuilder = $container->get('form_builder');
        // Create a new instance from formbuilder and return it.
        return new static($formBuilder);
    }


}

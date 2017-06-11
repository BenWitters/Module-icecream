<?php


/**
 * Provides a 'Ice cream' block
 *
 * id: machine naam, admin_label: title
 * @Block(
 *  id = "ice_cream",
 *  admin_label = @Translation("Ice cream order form")
 * )
 *
 */

namespace Drupal\ice_cream\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ice_cream\Services\IceFormBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrderBlock extends BlockBase implements ContainerFactoryPluginInterface{

    private $formBuilder;

    /**
     * OrderBlock constructor.
     * @param FormBuilder $formBuilder
     */
    public function __construct(FormBuilder $formBuilder) {

        $this->formBuilder = $formBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
        $form = $this->formBuilder->getForm('Drupal\ice_cream\Form\OrderForm');

        return $form;
    }


    /**
     * Creates an instance of the plugin.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *   The container to pull out services used in the plugin.
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     *
     * @return static
     *   Returns an instance of this plugin.
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        $formBuilder = $container->get('form_builder');
        return new static($formBuilder);
    }
}

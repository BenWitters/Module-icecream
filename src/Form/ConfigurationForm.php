<?php

namespace Drupal\ice_cream\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;


class ConfigurationForm extends ConfigFormBase{
    
    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     *   An array of configuration object names that are editable if called in
     *   conjunction with the trait's config() method.
     */
    protected function getEditableConfigNames() {
        return [
            'ice_cream.settings',
        ];
    }

    /**
     * Returns a unique string identifying the form.
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId() {
        return 'ice_cream_admin_settings';
    }

    /**
     * Returns the form and the form state
     *
     * @param array $form , FormStateInterface $form_state
     *  An array of the form and the state of the form
     *
     * @param FormStateInterface $form_state
     * @return parent ::buildForm
     *   The form builder and the state from the form
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        // Get the settings file.
        $config = $this->config('ice_cream.settings');

        // Define configuration form.
        $form['threshold_ice_cream'] = [
            '#type' => 'number',
            '#title' => $this->t('Threshold ijsjes'),
            // set default value to current saved value in config
            '#default_value' => $config->get('icecream')
        ];

        $form['threshold_waffle'] = [
            '#type' => 'number',
            '#title' => $this->t('Threshold wafels'),
            '#default_value' => $config->get('waffle')
        ];

        $form['current_zip'] = [
            '#type' => 'number',
            '#title' => $this->t('Postcode huidige locatie'),
            '#default_value' => $config->get('zip_code')
        ];


        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Wijzig de threshold'),
            '#button_type' => 'primary',
        ];
        return parent::buildForm($form, $form_state);
    }

    /**
     * Returns the submitted form
     *
     * @param $form, FormStateInterface $form_state
     *  An array of the form and the state of the form
     *
     * @return parent::submitForm
     *   The submitted form and state of the form
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        // Retrieve the configuration of the threshold.
        $this->config('ice_cream.settings')

            // set icecream and waffle equal to the value of the input field
            ->set('icecream', $form_state->getValue('threshold_ice_cream'))
            ->set('waffle', $form_state->getValue('threshold_waffle'))
            ->set('zip_code', $form_state->getValue('current_zip'))

            ->save();

        parent::submitForm($form, $form_state);
    }
}
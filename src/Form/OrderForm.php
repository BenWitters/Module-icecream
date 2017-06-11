<?php

namespace Drupal\ice_cream\Form;

use Drupal;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManager;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class OrderForm extends FormBase{

    private $entityTypeManager;
    private $connection;
    private $httpClient;
    private $json;
    private $mailManager;
    private $request;
    const API_KEY = 'fa9452823b4558b0288433f05f326c69';


    /**
     * OrderForm constructor.
     * @param Connection $connection
     * @param Client $httpClient
     * @param EntityTypeManager $entityTypeManager
     * @param Json $json
     * @param MailManager $mailManager
     * @param Request $request
     * @internal param MailManager $mailmanager
     * @internal param IceEntityTaxonomy $entityTaxonomy
     */
    public function __construct(Connection $connection, Client $httpClient, EntityTypeManager $entityTypeManager, Json $json, MailManager $mailManager, Request $request) {
        $this->connection = $connection;
        $this->httpClient = $httpClient;
        $this->entityTypeManager = $entityTypeManager;
        $this->json = $json;
        $this->mailManager = $mailManager;
        $this->request = $request;
    }

    /**
     * Returns a unique string identifying the form.
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId() {
        return 'order_form';
    }

    /**
     * Form constructor.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {


        // Get vocabulary tree terms for flavors & loop the terms.
        $flavors = $this->entityTypeManager->getStorage("taxonomy_term")->loadTree('flavor_id');
        $iceFlavors = $this->loopTerms($flavors);

        // Get vocabulary tree terms for toppings & loop the terms.
        $toppings = $this->entityTypeManager->getStorage("taxonomy_term")->loadTree('topping_id');
        $waffleToppings = $this->loopTerms($toppings);

        $form['food_choice'] = [
            '#type' => 'select',
            '#title' => $this->t('Maak een keuze tussen een ijsje of een wafel'),
            '#options' => [
                'ijsje' => $this->t('ijsje'),
                'wafel' => $this->t('wafel')
            ],
            '#required' => true
        ];

        $form['ice_flavor'] = [
            '#type' => 'select',
            '#title' => $this->t('Kies een smaak'),
            '#options' => $iceFlavors,
            // Only show field if ijsje is selected.
            '#states' => [
                'visible' => ['select[name="food_choice"]' => ['value' => 'ijsje']],
            ],
        ];

        $form['waffle_toppings'] = [
            '#type' => 'checkboxes',
            '#options' => $waffleToppings,
            '#title' => $this->t('Selecteer toppings naar keuze'),
            // Only show field if wafel is selected.
            '#states' => [
                'visible' => ['select[name="food_choice"]' => ['value' => 'wafel']],
            ],
        ];

        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Plaats uw bestelling'),
            '#button_type' => 'primary',
        ];
        return $form;
    }

    /**
     * Form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        // Get config settings file.
        $config = $this->config('ice_cream.settings');

        // Get zip code from the config settings file.
        $zipCode = $config->get('zip_code');

        // Get the language of the user.
        $languages = Drupal::request()->server->get('HTTP_ACCEPT_LANGUAGE');
        $languages = explode(';', $languages)[0];
        $languages = explode(',', $languages);
        $language = explode('-', $languages[0])[0];

        // Get the ip address of the user.
        $ip = Drupal::request()->getClientIp();


        // Get the current temperature
        $weatherData = $this->getCurrentTemperature($zipCode);
        $temperature = $weatherData['main']['temp'];

        // Check if the current temperature is below or above 20 degrees Celcius.
        if ($temperature < 20) {
            drupal_set_message($this->t('Het is momenteel te koud om de ijskar te laten komen, het moet mintens 20 graden zijn.'), 'warning');

        } else {

            // Get threshold values from icecream and waffles from config settings file.
            $icecreamThreshold = $config->get('icecream');
            $waffleThreshold = $config->get('waffle');

            // Slack webhook url.
            $url = "https://hooks.slack.com/services/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";

            // Get food choice that was selected.
            $foodChoice = $form_state->getValue('food_choice');
            $flavor = $form_state->getValue('ice_flavor');
            $topping = $form_state->getValue('waffle_toppings');
            $toppings = implode(', ', $topping);


            // Insert food into database
            $this->insertFoodOrder($foodChoice, $flavor, $toppings, $ip, $language);

            $foodCounter = $this->countFoodOrders($foodChoice);

            // Check if the orders reached the icecream threshold.
            if ($foodChoice == 'ijsje' && $foodCounter < $icecreamThreshold) {
                drupal_set_message($this->t('@food aangevraagd, er zijn momenteel nog niet voldoende @foods aangevraagd! (@foodCounter/@icecreamThreshold)',
                    ['@food' => $foodChoice, '@foodCounter' => $foodCounter, '@icecreamThreshold' => $icecreamThreshold]));

                // Check if the order reached the waffle threshold.
            } elseif ($foodChoice == 'wafel' && $foodCounter < $waffleThreshold) {
                drupal_set_message($this->t("@food aangevraagd, er zijn momenteel nog niet voldoende @foods aangevraagd! (@foodCounter/@waffleThreshold)",
                    ['@food' => $foodChoice, '@foodCounter' => $foodCounter, '@waffleThreshold' => $waffleThreshold]));

                // If the icecream or waffle threshold was reached.
            } elseif ($foodCounter >= $icecreamThreshold || $foodCounter >= $waffleThreshold) {

                // Post message in slack if icecream or waffle threshold was reached.
                $this->sendSlackMessage($url, $foodCounter, $foodChoice);

                // Send mail if the threshold from waffles or ice cream was reached
                $this->sendMail($foodChoice);

                // Show message on site.
                drupal_set_message($this->t('Er zijn voldoende @foods aangevraagd!', ['@food' => $foodChoice]));

                // Reset counter when threshold was reached.
                $this->resetFoodOrders($foodChoice);
            }
        }
    }

    /**
     * @param ContainerInterface $container
     * @return static
     *  Return new instance of connection, httpClient and entityTypeManager.
     */
    public static function create(ContainerInterface $container) {
        $connection = $container->get('database');
        $httpClient = $container->get('http_client');
        $entityTypeManager = $container->get('entitytype_manager');
        $json = $container->get('serialisation_json');
        $mailManager = $container->get('mail_manager');
        $request = $container->get('request');
        return new static($connection, $httpClient, $entityTypeManager, $json, $mailManager, $request);
    }

    /**
     * @param $terms
     *   The taxonomy terms.
     * @return array
     *  Array with all the taxonomy terms.
     */
    public function loopTerms($terms) {

        foreach ($terms as $term) {
            $termArray[$term->name] = $term->name;
        }
        return $termArray;
    }

    /**
     * @param $foodChoice
     *   the food that was selected
     * @throws \Exception
     * 
     * Inserts ordered foodchoice into the database.
     */
    public function insertFoodOrder($foodChoice, $flavor, $toppings, $ip, $language) {
        $conn = $this->connection;

        // Count rows with the food choice that was selected.
        $rowCount = $this->countFoodRows($foodChoice);

        $this->saveFoodData($foodChoice, $flavor, $toppings, $ip, $language);

        // It there are 0 rows, make new row with the foodchoice name.
        if ($rowCount == 0) {
            // Insert row into database.
            $conn->insert('food')->fields([
                    'name' => $foodChoice,
                    'counter' => 1,
                ])->execute();

            // Else update row counter.
        } else {
            // Count the ordered foods.
            $foodCounter = $this->countFoodOrders($foodChoice);

            // Increment the counter by 1 per order submit.
            $foodCounter++;

            // Update database with new counter value.
           $this->updateFoodOrders($foodChoice, $foodCounter);
        }
    }

    /**
     * @param $url
     *  the url from slack web hook
     * @param $foodCounter
     *   the value of the counter
     * @param $foodChoice
     *   the food choice that was selected
     */
    public function sendSlackMessage($url, $foodCounter, $foodChoice) {
        // The Json data that needs to be send.
        $jsonData = $this->json->encode([
            "text" => 'Er zijn ' . $foodCounter . ' '  . $foodChoice .'s aangevraagd!',
            "attachments" => [],
            "channel" => '#'. 'project-icecream',
            "username" => "benwitters",
            "icon_emoji" => ":icecream:",
        ]);
        
        // Post message in slack channel.
        $this->httpClient->post($url, ['body' => $jsonData, 'headers' => ['Content-Type' => 'application/json']]);
    }

    /**
     * @param $foodChoice
     *  The food choice that was selected.
     */
    public function resetFoodOrders($foodChoice) {
        $this->connection->update('food')
            ->fields([
                'counter' => 0,
            ])
            ->condition('name', $foodChoice)
            ->execute();
    }

    /**
     * @param $foodChoice
     *  The food that was select.
     *
     * @return $counter
     *  return the value of the counter
     */
    public function countFoodOrders($foodChoice) {
        $selectCounter = $this->connection->select('food', 'f')
            ->fields('f', ['counter'])
            ->condition('f.name', $foodChoice);

        // Execute the statement & get the results from the counter field.
        $foodCounter = $selectCounter->execute()->fetchField();

        return $foodCounter;
    }

    /**
     * @param $foodChoice
     *  The selected food choice.
     * @param $foodCounter
     *  The new value of the counter.
     */
    public function updateFoodOrders($foodChoice, $foodCounter) {
        $this->connection->update('food')
            ->fields([
                'counter' => $foodCounter,
            ])
            ->condition('name', $foodChoice)
            ->execute();
    }

    /**
     * @param $foodChoice
     *  The selected food choice.
     * @return mixed
     *  The number of rows that was found.
     */
    public function countFoodRows($foodChoice){
        $countRows = $this->connection->select('food', 'f')
            ->fields('f')
            ->condition('f.name', $foodChoice);

        // Execute the statement & get the results that counts the rows.
        $rowCount = $countRows->countQuery()->execute()->fetchField();
        return $rowCount;
    }

    /**
     * @param $foodChoice
     *  The selected food choice.
     * @param $flavor
     *  The selected flavor.
     * @param $toppings
     *  The selected topping(s).
     * @throws \Exception
     */
    public function saveFoodData($foodChoice, $flavor, $toppings, $ip, $language) {

        if($foodChoice == 'ijsje'){
            $this->connection->insert('food_data')->fields(
                [
                    'name' => $foodChoice,
                    'flavor' => $flavor,
                    'language' => $language,
                    'ip_adres' => $ip
                ]
            )->execute();
        } elseif ($foodChoice == 'wafel') {
            $this->connection->insert('food_data')->fields(
                [
                    'name' => $foodChoice,
                    'toppings' => $toppings,
                    'language' => $language,
                    'ip_adres' => $ip
                ]
            )->execute();
        }
    }

    /*
    public function sendMail($foodChoice){
        $module = 'ice_cream';
        $key = 'nouveau contact ';
        $params = [
            'body' => 'test',
            'subject' => 'Website Information Request',
        ];
        $send = true;
        $message['subject'] = $this->t($foodChoice);
        $message['body'][] = t($foodChoice);
        $to = 'ben.witters@hotmail.com';
        $this->mailManager->mail($module, $key,  $to, $params, NULL, $send);
    }
    */

    /**
     * @param $zipCode
     *  The zip code number.
     * @return mixed
     *  Returns the weather data.
     */
    public function getCurrentTemperature($zipCode) {
        $request = $this->httpClient->get('http://api.openweathermap.org/data/2.5/weather',
            ['query' =>
                [
                    'appid' => self::API_KEY,
                    'q' => $zipCode . ',BELGIUM',
                    'units' => 'metric',
                    'cnt' => 1
                ]
            ]
        );

        // If data is returned, decode this data and get the body.
        if ($request->getStatusCode() == 200) {
            return $this->json->decode($request->getBody());
        }
    }




}
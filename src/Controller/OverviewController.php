<?php

namespace Drupal\ice_cream\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use PDO;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OverviewController extends ControllerBase{

    private $connection;

    /**
     * OverviewController constructor.
     * @param Connection $connection
     */
    public function __construct(Connection $connection) {

        $this->connection = $connection;
    }

    /**
     * @return array
     */
    public function content() {
        
        $flavors = $this->getFoodOptions('ijsje', 'flavor');
        $icecreamCount = $this->foodCount('ijsje');
        $waffleCount = $this->foodCount('wafel');
        $iceFlavors = [];


        foreach ($flavors as $flavor) {
            $value = $this->calculateFlavorPercentage($flavor, $icecreamCount);
            array_push($iceFlavors, array_fill_keys($flavor, $value ));

        }

        // Get the languages from users
        $languages = $this->getLanguages();

        // Get the last ip adresses that ordered
        $ips = $this->getLastIps();

        return [
            '#theme' => 'overview',
            '#flavors' => $iceFlavors,
            '#icecreamCount' => $icecreamCount,
            '#waffleCount' => $waffleCount,
            '#languages' => $languages,
            '#ips' => $ips,
            '#attached' => [
                'library' => [
                    'ice_cream/icecream-overview-style',
                ],
            ]
        ];
    }

    /**
     * @param ContainerInterface $container
     * @return static
     */
    public static function create(ContainerInterface $container) {
        $connection = $container->get('database');
        return new static($connection);
    }

    /**
     * @return mixed
     * returns the existing ice cream flavors
     */
    public function getFoodOptions($foodName, $optionName){

        $selectCounter = $this->connection->select('food_data', 'f')
            ->distinct()
            ->fields('f', [$optionName])
            ->condition('name', $foodName);

        // Execute the statement & get the results from the counter field.
        $flavors = $selectCounter->execute()->fetchAll(PDO::FETCH_ASSOC);
        return $flavors;
    }

    /**
     * @param $foodName
     *  The name of the food that was selected.
     * @return mixed
     */
    public function foodCount($foodName){
        $selectCounter = $this->connection->select('food_data', 'f')
            ->fields('f')
            ->condition('name', $foodName);

        // Execute the statement & get the results from the counter field.
        $icecreamCount = $selectCounter->countQuery()->execute()->fetchField();
        return $icecreamCount;
    }

    /**
     * @param $flavor
     *  The selected flavor.
     * @param $icecreamCount
     *  The value of the total icecream count.
     * @return float
     *  Returns the percentage per flavor that was ordered.
     */
    public function calculateFlavorPercentage($flavor, $icecreamCount){
        $selectCounter = $this->connection->select('food_data', 'f')
            ->fields('f')
            ->condition('flavor', $flavor);

        // Execute the statement & get the results from the counter field.
        $result = $selectCounter->countQuery()->execute()->fetchField();

        $icecreamCount = round($result / $icecreamCount * 100, 2);

        return $icecreamCount;
    }

    /**
     * @return mixed
     * Returns all the languages with no duplicates.
     */
    public function getLanguages() {
        $languageQuery = $this->connection->select('food_data', 'f')
            ->distinct()
            ->fields('f', ['language']);

        // Execute the statement & get the results from the counter field.
        $languages = $languageQuery->execute()->fetchAll(PDO::FETCH_ASSOC);
        return $languages;
    }

    public function getLastIps(){
        $languageQuery = $this->connection->select('food_data', 'f')
            ->fields('f', ['ip_adres'])
            ->orderBy('food_data_id', 'DESC')//ORDER BY created
            ->range(0, 3);


        // Execute the statement & get the results from the counter field.
        $languages = $languageQuery->execute()->fetchAll(PDO::FETCH_ASSOC);
        return $languages;
    }


}
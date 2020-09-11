<?php

namespace Drupal\sw\Services;

use GuzzleHttp\Client;

/**
* Class StarWarsAPI.
*
* @package Drupal\sw\Services
*/
class StarWarsAPI {
  
  /**
   * Guzzle\Client instance.
   *
   * @var \Guzzle\Client
   */
  protected $http;

  const BASE_URL = 'https://swapi.dev/api/';

  /**
   * {@inheritdoc}
   */
  public function __construct(Client $httpClient) {
    $this->http = $httpClient;
  }

  public function getAllExemplars() {
    
    $entities = $this->getEntities();

    foreach ($entities as $entityName => $url) { 
      $exemplars[] = $this->getExemplarsFromPage($url, $entityName);
    }

    return $exemplars;

  }

  protected function getData($url) {

    $response = $this->http->request("GET", $url);

    $data = json_decode($response->getBody());
    return $data;
  }

  //Возвращает объект из названий сущностей и соответствующих им URL-ов
  protected function getEntities() {

    $entities = $this->getData(self::BASE_URL);
    
    return $entities;
  }


  //Возвращает экземпляры со страницы 
  protected function getExemplarsFromPage ($url, $entityName) {
    $page = $this->getData($url);

    foreach ($page->results as $exemplar) {
        $exemplar->entityName = $entityName;
        $exemplars[] = $exemplar;
    }
    //Проверяем что поле с номером следующей страницы не пустое
    if($page->next != null) {
      $exemplars = array_merge($exemplars, $this->getExemplarsFromPage($page->next, $entityName));
    }

    return $exemplars;
  }
  
}
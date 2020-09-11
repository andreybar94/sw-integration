<?php

namespace Drupal\sw\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use \Drupal\node\Entity\Node;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Processes Node Tasks.
 *
 * @QueueWorker(
 *   id = "node_queue_worker",
 *   title = @Translation("Node Queue Worker"),
 *   cron = {"time" = 10}
 * )
 */
class NodeQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * @var EntityTypeManager $entityTypeManager
   */
  protected $entityTypeManager;

  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /**
     * @var EntityTypeManager $entityTypeManager
     */
    $entityTypeManager = $container->get('entity_type.manager');

    return new static(
      $configuration, 
      $plugin_id, 
      $plugin_definition, 
      $entityTypeManager
    );
  }

  /**
   * {@inheritdoc}
   */
  
  public function processItem($exemplar) {
    //Пробуем получить ноду по URL
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['field_url' => $exemplar->url]);

    $fields = $this->getFields($exemplar);

    if(!$node) {
      $this->createNode($fields);
    } 
    else {
      $this->updateNode($fields, reset($node));
    }
    return;
  }

  protected function getFields($exemplar){
    
    $data['type'] = $exemplar->entityName;
    $data['title'] = $this->getNodeTitle($exemplar);
    
    foreach($exemplar as $field => $value) {

      if($field == 'entityName' || $value === null){
        continue;
      }

      if ($field == 'homeworld') {
        $referenceNode = $this->getNode($value, 'planets');
        $data[mb_strtolower("field_{$field}")] = ['target_id' => $referenceNode->id()];
        continue;
      }

      if(is_array($value)) {
        $data[mb_strtolower("field_{$field}")] = $this->getReferenceNodeIds($field, $value);
        continue;
      }


      $data[mb_strtolower("field_{$field}")] = $value;
    }

    return $data;
  }

  protected function createNode($fields) {

    $node = Node::create($fields);

    $node->save();

    return $node;

  }

  protected function updateNode ($fields, $node) {
    
    foreach ($fields as $fieldName => $fieldValue) {
      $node->set($fieldName, $fieldValue);  
    }

    $node->save();

    return $node;
  }

  //Пробует найти ноду по URL, если не находит создает новую на основе URL
  protected function getNode($url, $entityName) {

    $fields['type'] = $entityName;
    $fields['title'] = $url;
    $fields['field_url'] = $url;

    $node = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['field_url' => $url]);

    if(!$node) {
      return $this->createNode($fields);
    } else {
      return reset($node);
    }
  }

  //Принимает на вход массив URL-ов и возвращает массив id найденых/созданых по URL-ам нод
  protected function getReferenceNodeIds($field, $value) {
    $target_ids = [];

    if($field == 'pilots' || $field == 'residents' || $field == 'characters') {
      $field = 'people';
    }

    foreach ($value as $url) {
      $referenceNode = $this->getNode($url, $field);
      $target_ids[] = ['target_id' => $referenceNode->id()];
    }

    return $target_ids;
  }

  protected function getNodeTitle($exemplar) {

    if(property_exists($exemplar,'title')) {
      $title = $exemplar->title;
    }
    elseif (property_exists($exemplar,'name')) {
      $title = $exemplar->name;
    } 
    else {
      $title = $exemplar->url;
    }

    return $title;
  }

}
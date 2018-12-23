<?php

namespace Drupal\google_analytics_counter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Process project google_analytics_counter information.
 */
class GoogleAnalyticsCounterProcessor implements GoogleAnalyticsCounterProcessorInterface {

  /**
   * The google_analytics_counter settings
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $googleAnalyticsCounterSettings;

  /**
   * The google_analytics_counter fetch queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $fetchQueue;

  /**
   * GoogleAnalyticsCounter key/value store
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $tempStore;

  /**
   * GoogleAnalyticsCounter Fetch Task Store
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $fetchTaskStore;

  /**
   * Array of release history URLs that we have failed to fetch
   *
   * @var array
   */
  protected $failed;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $stateStore;

  /**
   * Constructs a GoogleAnalyticsCounterProcessor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory
   * @param \Drupal\Core\State\StateInterface $state_store
   *   The state service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key/value factory.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_expirable_factory
   *   The expirable key/value factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, QueueFactory $queue_factory, StateInterface $state_store, KeyValueFactoryInterface $key_value_factory, KeyValueFactoryInterface $key_value_expirable_factory) {
    $this->googleAnalyticsCounterSettings = $config_factory->get('google_analytics_counter.settings');
    $this->fetchQueue = $queue_factory->get('google_analytics_counter_worker');
    $this->tempStore = $key_value_expirable_factory->get('google_analytics_counter');
    $this->fetchTaskStore = $key_value_factory->get('google_analytics_counter');
    $this->stateStore = $state_store;
    $this->fetchTasks = [];
    $this->failed = [];
  }

  /**
   * {@inheritdoc}
   */
  public function createFetchTask($project) {
    // Too slow
    //    $serialized = serialize($project);
    //
    //    $query = db_merge('queue')
    //      ->key(array('data' => $serialized))
    //      ->fields(array(
    //        'name' => 'google_analytics_counter_worker',
    //        'data' => $serialized,
    //        'created' => time(),
    //      ));
    //    return (bool) $query->execute();

    //    drush_print_r($this->fetchTaskStore->getAll());

//        drush_print_r($project);
    if (empty($this->fetchTasks)) {
      $this->fetchTasks = $this->fetchTaskStore->getAll();
    }
    if (empty($this->fetchTasks['google_analytics_counter'])) {
      $this->fetchQueue->createItem($project);
      $this->fetchTaskStore->set('google_analytics_counter', $project);
      $this->fetchTasks['google_analytics_counter'] = REQUEST_TIME;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfQueueItems() {
    return $this->fetchQueue->numberOfItems();
  }

  /**
   * {@inheritdoc}
   */
  public function claimQueueItem() {
    return $this->fetchQueue->claimItem();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueueItem($item) {
    return $this->fetchQueue->deleteItem($item);
  }

}

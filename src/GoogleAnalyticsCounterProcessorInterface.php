<?php

namespace Drupal\google_analytics_counter;


/**
 * Process project google_analytics_counter information.
 */
interface GoogleAnalyticsCounterProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function createFetchTask($project);

  /**
   * {@inheritdoc}
   */
  public function numberOfQueueItems();

  /**
   * {@inheritdoc}
   */
  public function claimQueueItem();

  /**
   * {@inheritdoc}
   */
  public function deleteQueueItem($item);
}
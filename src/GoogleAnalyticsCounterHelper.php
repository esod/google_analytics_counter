<?php

namespace Drupal\google_analytics_counter;

use Drupal\Core\Entity\EditorialContentEntityBase;

/**
 * Provides Google Analytics Counter helper functions.
 */
class GoogleAnalyticsCounterHelper extends EditorialContentEntityBase {

  /**
   * Remove queued items from the database.
   */
  public static function gacRemoveQueuedItems() {
    $quantity = 200000;

    $connection = \Drupal::database();

    $query = $connection->select('queue', 'q');
    $query->addExpression('COUNT(*)');
    $query->condition('name', 'google_analytics_counter_worker');
    $queued_workers = $query->execute()->fetchField();
    $chunks = $queued_workers / $quantity;

    // Todo: get $t_arg working.
    $t_arg = ['@quantity' => $quantity];
    for ($x = 0; $x <= $chunks; $x++) {
      \Drupal::database()
        ->query("DELETE FROM {queue} WHERE name = 'google_analytics_counter_worker' LIMIT 200000");
    }
  }

  /**
   * Creates the gac_type_{content_type} configuration on installation or update.
   */
  public static function gacSaveGacTypeConfig() {
    $config_factory = \Drupal::configFactory();
    $content_types = \Drupal::service('entity.manager')
      ->getStorage('node_type')
      ->loadMultiple();

    foreach ($content_types as $machine_name => $content_type) {
      // For updates, don't overwrite existing configuration.
      $gac_type = $config_factory->getEditable('google_analytics_counter.settings')
        ->get("general_settings.gac_type_$machine_name");
      if (empty($gac_type)) {
        $config_factory->getEditable('google_analytics_counter.settings')
          ->set("general_settings.gac_type_$machine_name", NULL)
          ->save();
      }
    }
  }

}

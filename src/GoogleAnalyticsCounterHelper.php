<?php

namespace Drupal\google_analytics_counter;

use Drupal\Core\Entity\EditorialContentEntityBase;

/**
 * Provides Google Analytics Counter helper functions.
 */
class GoogleAnalyticsCounterHelper extends EditorialContentEntityBase {

  /****************************************************************************/
  // Query functions.
  /****************************************************************************/

  /**
   * Remove queued items from the database.
   */
  public static function removeQueuedItems() {
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
}

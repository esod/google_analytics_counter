<?php

namespace Drupal\google_analytics_counter;

/**
 * Provides Google Analytics Counter helper functions.
 */
class GoogleAnalyticsCounterHelper {

  /**
   * Makes certain there is a $profile_ids array. Helpful for before cron runs.
   *
   * @return array
   */
  public static function checkForProfileIds() {
    $config = \Drupal::config('google_analytics_counter.settings');

    // There may not yet be profile ids, depending on whether cron has been run.
    if (!empty(\Drupal::state()->get('google_analytics_counter.profile_ids'))) {
      $profile_ids = \Drupal::state()
        ->get('google_analytics_counter.profile_ids');
    }
    else {
      // Convert profile_id to an array.
      // Todo: It's possible that profile_id configuration doesn't exist either. ;-|
      $profile_id = $config->get('general_settings.profile_id');

      $profile_ids = [
        $profile_id => $profile_id,
      ];
    }
    return $profile_ids;
  }

}

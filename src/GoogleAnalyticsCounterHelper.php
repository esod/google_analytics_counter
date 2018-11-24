<?php

namespace Drupal\google_analytics_counter;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;

use Drupal\Core\Entity\EditorialContentEntityBase;


/**
 * Provides Google Analytics Counter helper functions.
 */
class GoogleAnalyticsCounterHelper extends EditorialContentEntityBase {

  /**
   * Makes certain there is a $profile_ids array. Helpful for before cron runs.
   *
   * @return array
   */
  public static function checkProfileIds() {
    $config = \Drupal::config('google_analytics_counter.settings');

    // There may not yet be profile ids, depending on whether cron has been run.
    if (!empty(\Drupal::state()->get('google_analytics_counter.profile_ids'))) {
      $profile_ids = \Drupal::state()
        ->get('google_analytics_counter.profile_ids');
    }
    else {
      // Convert profile_id to an array.
      // Todo: It's possible that general_settings.profile_id doesn't exist either. ;-|
      $profile_id = $config->get('general_settings.profile_id');

      $profile_ids = [
        $profile_id => $profile_id,
      ];
    }
    return $profile_ids;
  }

  /**
   * Remove queued items from the database.
   */
  public static function removeQueuedItems() {
    $quantity = 200000;
    $queued_workers = \Drupal::database()
      ->query("SELECT COUNT(*) FROM {queue} WHERE name = 'google_analytics_counter_worker'")
      ->fetchField();
    $chunks = $queued_workers / $quantity;

    // Todo: get $t_arg working.
    $t_arg = ['@quantity' => $quantity];
    for ($x = 0; $x <= $chunks; $x++) {
      \Drupal::database()
        ->query("DELETE FROM {queue} WHERE name = 'google_analytics_counter_worker' LIMIT 200000");
    }
  }

  /****************************************************************************/
  // Message functions.
  /****************************************************************************/

  /**
   * Prints a warning message when not authenticated.
   *
   * @param $build
   *
   */
  public static function notAuthenticatedMessage($build = []) {
    $t_arg = [
      ':href' => Url::fromRoute('google_analytics_counter.admin_auth_form', [], ['absolute' => TRUE])
        ->toString(),
      '@href' => 'Authentication',
    ];
    \Drupal::messenger()->addWarning(t('Google Analytics have not been authenticated! Google Analytics Counter cannot fetch any new data. Please authenticate with Google from the <a href=:href>@href</a> page.', $t_arg));

    // Revoke Google authentication.
    self::revokeAuthenticationMessage($build);
  }

  /**
   * Revoke Google Authentication Message.
   *
   * @param $build
   * @return mixed
   */
  public static function revokeAuthenticationMessage($build) {
    $t_args = [
      ':href' => Url::fromRoute('google_analytics_counter.admin_auth_revoke', [], ['absolute' => TRUE])
        ->toString(),
      '@href' => 'revoking Google authentication',
    ];
    $build['drupal_info']['revoke_authentication'] = [
      '#markup' => t("If there's a problem with OAUTH authentication, try <a href=:href>@href</a>.", $t_args),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];
    return $build;
  }

  /**
   * Returns the link with the Google project name if it is available.
   *
   * @return string
   *   Project name.
   */
  public static function googleProjectName() {
    $config = \Drupal::config('google_analytics_counter.settings');
    $project_name = !empty($config->get('general_settings.project_name')) ?
      Url::fromUri('https://console.developers.google.com/apis/api/analytics.googleapis.com/quotas?project=' . $config->get('general_settings.project_name'))
        ->toString() :
      Url::fromUri('https://console.developers.google.com/apis/api/analytics.googleapis.com/quotas')
        ->toString();

    return $project_name;
  }



  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['google_analytics_counter'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }


}

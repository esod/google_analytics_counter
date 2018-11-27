<?php

namespace Drupal\google_analytics_counter;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeTypeInterface;

/**
 * Provides Google Analytics Counter helper functions.
 */
class GoogleAnalyticsCounterHelper extends EditorialContentEntityBase {

//  /**
//   * Makes certain there is a $profile_ids array. Helpful for before cron runs.
//   *
//   * @return array
//   */
//  public static function checkProfileIds() {
//    $config = \Drupal::config('google_analytics_counter.settings');
//    $profile_ids = $config->get('general_settings.profile_ids');
//
//    return $profile_ids;
//  }
//
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
    $build['cron_info']['revoke_authentication'] = [
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

  /****************************************************************************/
  // Configuration functions.
  /****************************************************************************/

  /**
   * Adds the checked the fields.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   A node type entity.
   * @param string $label
   *   The formatter label display setting.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\field\Entity\FieldConfig|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function gacAddField(NodeTypeInterface $type, $label = 'Google Analytics Counter') {

    // Check if field storage exists.
    $config = FieldStorageConfig::loadByName('node', 'field_google_analytics_counter');
    if (!isset($config)) {
      // Obtain configuration from yaml files
      $config_path = 'modules/contrib/google_analytics_counter/config/optional';
      $source = new FileStorage($config_path);

      // Obtain the storage manager for field storage bases.
      // Create the new field configuration from the yaml configuration and save.
      \Drupal::entityTypeManager()->getStorage('field_storage_config')
        ->create($source->read('field.storage.node.field_google_analytics_counter'))
        ->save();
    }

    // Add the checked fields.
    $field_storage = FieldStorageConfig::loadByName('node', 'field_google_analytics_counter');
    $field = FieldConfig::loadByName('node', $type->id(), 'field_google_analytics_counter');
    if (empty($field)) {
      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $type->id(),
        'label' => $label,
        'description' => t('This field stores Google Analytics pageviews.'),
        'field_name' => 'field_google_analytics_counter',
        'entity_type' => 'node',
        'settings' => array('display_summary' => TRUE),
      ]);
      $field->save();

      // Assign widget settings for the 'default' form mode.
      entity_get_form_display('node', $type->id(), 'default')
        ->setComponent('google_analytics_counter', array(
          'type' => 'textfield',
          '#maxlength' => 255,
          '#default_value' => 0,
          '#description' => t('blah, blah, blah'),
        ))
        ->save();

      // Assign display settings for the 'default' and 'teaser' view modes.
      entity_get_display('node', $type->id(), 'default')
        ->setComponent('google_analytics_counter', array(
          'label' => 'hidden',
          'type' => 'textfield',
        ))
        ->save();

      // The teaser view mode is created by the Standard profile and therefore
      // might not exist.
      $view_modes = \Drupal::entityManager()->getViewModes('node');
      if (isset($view_modes['teaser'])) {
        entity_get_display('node', $type->id(), 'teaser')
          ->setComponent('google_analytics_counter', array(
            'label' => 'hidden',
            'type' => 'textfield',
          ))
          ->save();
      }
    }

    return $field;
  }

  /**
   * Deletes the unchecked field configurations.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   A node type entity.
   *
   * @return null|void
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @see GoogleAnalyticsCounterConfigureContentTypesForm
   */
  public static function gacDeleteField(NodeTypeInterface $type) {
    // Check if field storage exists.
    $config = FieldConfig::loadByName('node', $type->id(), 'field_google_analytics_counter');
    if (!isset($config)) {
      return NULL;
    }
    FieldConfig::loadByName('node', $type->id(), 'field_google_analytics_counter')->delete();
  }

}

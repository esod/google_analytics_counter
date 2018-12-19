<?php

namespace Drupal\google_analytics_counter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class GoogleAnalyticsCounterManager.
 *
 * @package Drupal\google_analytics_counter
 */
class GoogleAnalyticsCounterManager implements GoogleAnalyticsCounterManagerInterface {

  use StringTranslationTrait;

  /**
   * The table for the node__field_google_analytics_counter storage.
   */
  const TABLE = 'node__field_google_analytics_counter';

  /**
   * The google_analytics_counter.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The state where all the tokens are saved.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The language manager to get all languages for to get all aliases.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Prefixes.
   *
   * @var array
   */
  protected $prefixes;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs an Importer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state of the drupal site.
   * @param \Drupal\Core\Database\Connection $connection
   *   A database connection.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager to find aliased resources.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language
   *   The language manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
    Connection $connection,
    AliasManagerInterface $alias_manager,
    PathMatcherInterface $path_matcher,
    LanguageManagerInterface $language,
    LoggerInterface $logger,
    MessengerInterface $messenger
  ) {
    $this->config = $config_factory->get('google_analytics_counter.settings');
    $this->state = $state;
    $this->connection = $connection;
    $this->aliasManager = $alias_manager;
    $this->pathMatcher = $path_matcher;
    $this->languageManager = $language;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->time = \Drupal::service('datetime.time');

    $this->prefixes = [];
    // The 'url' will return NULL when it is not a multilingual site.
    $language_url = $config_factory->get('language.negotiation')->get('url');
    if ($language_url) {
      $this->prefixes = $language_url['prefixes'];
    }
  }

  /**
   * Begin authentication to Google authentication page with the client_id.
   */
  public function beginGacAuthentication() {
    global $base_url;
    $current_path = \Drupal::service('path.current')->getPath();
    $uri = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);
    $redirect_uri = $base_url . $uri;

    $gafeed = new GoogleAnalyticsCounterFeed();
    $gafeed->beginAuthentication($this->config->get('general_settings.client_id'), $redirect_uri);
  }

  /**
   * Check to make sure we are authenticated with google.
   *
   * @return bool
   *   True if there is a refresh token set.
   */
  public function isAuthenticated() {
    return $this->state->get('google_analytics_counter.access_token') != NULL ? TRUE : FALSE;
  }

  /**
   * Instantiate a new GoogleAnalyticsCounterFeed object.
   *
   * @return object
   *   GoogleAnalyticsCounterFeed object to authorize access and request data
   *   from the Google Analytics Core Reporting API.
   */
  public function newGaFeed() {
    global $base_url;
    $config = $this->config;

    // If the access token is still valid, return an authenticated GAFeed.
    if ($this->state->get('google_analytics_counter.access_token') && time() < $this->state->get('google_analytics_counter.expires_at')) {
      return new GoogleAnalyticsCounterFeed($this->state->get('google_analytics_counter.access_token'));
    }
    // If the site has an access token and refresh token, but the access
    // token has expired, authenticate the user with the refresh token.
    elseif ($this->state->get('google_analytics_counter.refresh_token')) {
      $client_id = $config->get('general_settings.client_id');
      $client_secret = $config->get('general_settings.client_secret');
      $refresh_token = $this->state->get('google_analytics_counter.refresh_token');
      try {
        $gac_feed = new GoogleAnalyticsCounterFeed();
        $gac_feed->refreshToken($client_id, $client_secret, $refresh_token);
        $this->state->setMultiple([
          'google_analytics_counter.access_token' => $gac_feed->accessToken,
          'google_analytics_counter.expires_at' => $gac_feed->expiresAt,
        ]);
        return $gac_feed;
      }
      catch (Exception $e) {
        $this->messenger->addError($this->t('There was an authentication error. Message: %message', ['%message' => $e->getMessage()]));
        return NULL;
      }
    }
    // If there is no access token or refresh token and client is returned
    // to the config page with an access code, complete the authentication.
    elseif (isset($_GET['code'])) {
      try {
        $current_path = \Drupal::service('path.current')->getPath();
        $uri = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);
        $redirect_uri = $base_url . $uri;

        $gac_feed = new GoogleAnalyticsCounterFeed();
        $gac_feed->finishAuthentication($config->get('general_settings.client_id'), $config->get('general_settings.client_secret'), $redirect_uri);

        $this->state->setMultiple([
          'google_analytics_counter.access_token' => $gac_feed->accessToken,
          'google_analytics_counter.expires_at' => $gac_feed->expiresAt,
          'google_analytics_counter.refresh_token' => $gac_feed->refreshToken,
        ]);

        $this->messenger->addStatus($this->t('You have been successfully authenticated.'), FALSE);

      }
      catch (Exception $e) {
        $this->messenger->addError($this->t('There was an authentication error. Message: %message', ['%message' => $e->getMessage()]));
        return NULL;
      }
    }

    return NULL;
  }

  /**
   * Get the list of available web properties.
   *
   * @return array
   *   Array of options.
   */
  public function getWebPropertiesOptions() {
    // When not authenticated, the only option is 'Unauthenticated'.
    $feed = $this->newGaFeed();
    if (!$feed) {
      $options = ['unauthenticated' => 'Unauthenticated'];
      return $options;
    }

    // Get the profiles information from Google.
    $web_properties = $feed->queryWebProperties()->results->items;
    $profiles = $feed->queryProfiles()->results->items;

    $options = [];
    // Add options for each web property.
    if (!empty($profiles)) {
      foreach ($profiles as $profile) {
        $webprop = NULL;
        foreach ($web_properties as $web_property) {
          if ($web_property->id == $profile->webPropertyId) {
            $webprop = $web_property;
            break;
          }
        }

        $options[$webprop->name][$profile->id] = $profile->name . ' (' . $profile->id . ')';
      }
    }
    return $options;
  }

  /**
   * Sets the expiry timestamp for cached queries. Default is 1 day.
   *
   * @return int
   *   The UNIX timestamp to expire the query at.
   */
  public static function cacheTime() {
    $config = \Drupal::config('google_analytics_counter.settings');
    return time() + $config->get('general_settings.cache_length');
  }

  /**
   * Get the total results from Google.
   *
   * @param int $index
   *   The index of the chunk to fetch for the queue.
   *
   * @return \Drupal\google_analytics_counter\GoogleAnalyticsCounterFeed
   *   The returned feed after the request has been made.
   */
  public function getChunkedResults($index = 0) {
    $config = $this->config;

    $step = $this->state->get('google_analytics_counter.data_step');
    $chunk = $config->get('general_settings.chunk_to_fetch');

    // Set the pointer.
    $pointer = $step * $chunk + 1;

    $parameters = [
      'profile_id' => 'ga:' . $config->get('general_settings.profile_id'),
      'metrics' => ['ga:pageviews'],
      'dimensions' => ['ga:pagePath'],
      'start_date' => !empty($config->get('general_settings.fixed_start_date')) ? strtotime($config->get('general_settings.fixed_start_date')) : strtotime($config->get('general_settings.start_date')),
      // If fixed dates are not in use, use 'tomorrow' to offset any timezone
      // shift between the hosting and Google servers.
      'end_date' => !empty($config->get('general_settings.fixed_end_date')) ? strtotime($config->get('general_settings.fixed_end_date')) : strtotime('tomorrow'),
      'start_index' => $pointer,
      'max_results' => $config->get('general_settings.chunk_to_fetch'),
    ];

    $cache_options = [
      'cid' => 'google_analytics_counter_' . md5(serialize($parameters)),
      'expire' => self::cacheTime(),
      'refresh' => FALSE,
    ];

    return $this->reportData($parameters, $cache_options);
  }

  /**
   * Request report data.
   *
   * @param array $parameters
   *   An associative array containing:
   *   - profile_id: required [default='ga:profile_id']
   *   - dimensions: optional [ga:pagePath]
   *   - metrics: required [ga:pageviews]
   *   - sort: optional [ga:pageviews]
   *   - start-date: [default=-1 week]
   *   - end_date: optional [default=tomorrow]
   *   - start_index: [default=1]
   *   - max_results: optional [default=10,000].
   *   - filters: optional [default=none]
   *   - segment: optional [default=none]
   * @param array $cache_options
   *   An optional associative array containing:
   *   - cid: optional [default=md5 hash]
   *   - expire: optional [default=CACHE_TEMPORARY]
   *   - refresh: optional [default=FALSE].
   *
   * @return \Drupal\google_analytics_counter\GoogleAnalyticsCounterFeed|object
   *   A new GoogleAnalyticsCounterFeed object
   */
  public function reportData($parameters = [], $cache_options = []) {
    $config = $this->config;

    // The total number of published nodes.
    $query = \Drupal::entityQuery('node');
    $query->condition('status', NodeInterface::PUBLISHED);
    $total_nodes = $query->count()->execute();
    $this->state->set('google_analytics_counter.total_nodes', $total_nodes);

    $ga_feed = $this->newGaFeed();
    if (!$ga_feed) {
      throw new \RuntimeException($this->t('The GoogleAnalyticsCounterFeed could not be initialized, is it authenticated?'));
    }

    $ga_feed->queryReportFeed($parameters, $cache_options);

    // DEBUG:
    // drush_print($ga_feed->results->selfLink);
    // drush_print($ga_feed->error);

    // Handle errors here too.
    if (!empty($ga_feed->error)) {
      throw new \RuntimeException($ga_feed->error);
    }

    // Don't write anything to google_analytics_counter if this Google Analytics
    // data comes from cache (would be writing the same again).
    if (!$ga_feed->fromCache) {
      // If NULL then there is no error.
      if (!empty($ga_feed->error)) {
        $t_args = [
          ':href' => Url::fromRoute('google_analytics_counter.authentication', [], ['absolute' => TRUE])
            ->toString(),
          '@href' => 'here',
          '%new_data_error' => $ga_feed->error,
        ];
        $this->logger->error('Problem fetching data from Google Analytics: %new_data_error. Did you authenticate any Google Analytics profile? See <a href=:href>@href</a>.', $t_args);
      }
    }

    // The last time the Data was refreshed by Google. Not always available from Google.
    if (!empty($ga_feed->results->dataLastRefreshed)) {
      $this->state->set('google_analytics_counter.data_last_refreshed', $ga_feed->results->dataLastRefreshed);
    }
    // The first selfLink query to Google. Helpful for debugging in the dashboard.
    $this->state->set('google_analytics_counter.most_recent_query', $ga_feed->results->selfLink);

    // The total number of pageViews for this profile from start_date to end_date.
    $this->state->set('google_analytics_counter.total_pageviews', $ga_feed->results->totalsForAllResults['pageviews']);

    // The total number of pagePaths for this profile from start_date to end_date.
    $this->state->set('google_analytics_counter.total_paths', $ga_feed->results->totalResults);

    // The number of results from Google Analytics in one request.
    $chunk = $config->get('general_settings.chunk_to_fetch');

    // Do one chunk at a time and register the data step.
    $step = $this->state->get('google_analytics_counter.data_step');

    // Which node to look for first. Must be between 1 - infinity.
    $pointer = $step * $chunk + 1;

    // Set the pointer equal to the pointer plus the chunk.
    $pointer += $chunk;

    $t_args = [
      '@size_of' => sizeof($ga_feed->results->rows),
      '@first' => ($pointer - $chunk),
      '@second' => ($pointer - $chunk - 1 + sizeof($ga_feed->results->rows)),
    ];
    $this->logger->info('Retrieved @size_of items from Google Analytics data for paths @first - @second.', $t_args);

    // Increase the step or set the step to 0 depending on whether
    // the pointer is less than or equal to the total results.
    if ($pointer <= $ga_feed->results->totalResults) {
      $new_step = $step + 1;
    }
    else {
      $new_step = 0;
    }

    $this->state->set('google_analytics_counter.data_step', $new_step);

    return $ga_feed;
  }

  /**
   * Get the count of pageviews for a path.
   *
   * @param string $path
   *   The path to look up.
   *
   * @return string
   *   Count of page views.
   */
  public function displayGacCount($path) {
    // Make sure the path starts with a slash.
    $path = '/' . trim($path, ' /');

    // It's the front page.
    if ($this->pathMatcher->isFrontPage()) {
      $aliases = ['/'];
      $sum_of_pageviews = $this->sumPageviews($aliases);
    }
    else {
      // Look up the alias, with, and without trailing slash.
      $aliases = [
        $this->aliasManager->getAliasByPath($path),
        $path,
        $path . '/',
      ];

      $sum_of_pageviews = $this->sumPageviews($aliases);
    }

    return number_format($sum_of_pageviews);
  }

  /****************************************************************************/
  // Query functions.
  /****************************************************************************/

  /**
   * Look up the count via the hash of the paths.
   *
   * @param $aliases
   *   The pagepaths.
   * @param $profile_id
   *   The current profile_id.
   *
   * @return string
   *   Count of views.
   */
  protected function sumPageviews($aliases) {
    // $aliases can make pageview_total greater than pageviews
    // because $aliases can include page aliases, node/id, and node/id/ URIs.
    $hashes = array_map('md5', $aliases);
    $path_counts = $this->connection->select('google_analytics_counter', 'gac')
      ->fields('gac', ['pageviews'])
      ->condition('pagepath_hash', $hashes, 'IN')
      ->execute();
    $sum_of_pageviews = 0;
    foreach ($path_counts as $path_count) {
      $sum_of_pageviews += $path_count->pageviews;
    }

    return $sum_of_pageviews;
  }

  /**
   * Merge the sum of pageviews into google_analytics_counter_storage.
   *
   * @param int $nid
   *   Node id value.
   * @param int $sum_of_pageviews
   *   Count of page views.
   * @param string $bundle
   *   The content type of the node.
   * @param int $vid
   *   Revision id value.
   *
   * @throws \Exception
   */
  protected function mergeCounterStorage($nid, $sum_of_pageviews, $bundle, $vid) {
    $this->connection->merge('google_analytics_counter_storage')
      ->key('nid', $nid)
      ->fields(['pageview_total' => $sum_of_pageviews])
      ->execute();

    // This is where the module gets expensive.
    // Update the Google Analytics Counter field if it exists.
    if (!$this->connection->schema()->tableExists(static::TABLE)) {
      return;
    }

    // Todo: This can be more performant by adding only the bundles that have been selected.
    $query = $this->connection->select('node__field_google_analytics_counter', 'gac');
    $query->fields('gac');
    $query->condition('entity_id', $nid);
    $entity_id = $query->execute()->fetchField();

    if ($entity_id) {
      $this->connection->update('node__field_google_analytics_counter')
        ->fields([
          'bundle' => $bundle,
          'deleted' => 0,
          'entity_id' => $nid,
          'revision_id' => $vid,
          'langcode' => 'en',
          'delta' => 0,
          'field_google_analytics_counter_value' => $sum_of_pageviews,
        ])
        ->condition('entity_id', $entity_id)
        ->execute();
    }
    else {
      $this->connection->insert('node__field_google_analytics_counter')
        ->fields([
          'bundle' => $bundle,
          'deleted' => 0,
          'entity_id' => $nid,
          'revision_id' => $vid,
          'langcode' => 'en',
          'delta' => 0,
          'field_google_analytics_counter_value' => $sum_of_pageviews,
        ])
        ->execute();
    }
  }

  /**
   * Get the row count of a table, sometimes with conditions.
   *
   * @param string $table
   * @return mixed
   */
  public function getCount($table) {
    switch ($table) {
      case 'google_analytics_counter_storage':
        $query = $this->connection->select($table, 't');
        $query->addField('t', 'field_pageview_total');
        $query->condition('pageview_total', 0, '>');
        break;
      case 'node_counter':
        $query = $this->connection->select($table, 't');
        $query->addField('t', 'field_totalcount');
        $query->condition('totalcount', 0, '>');
        break;
      case 'google_analytics_counter_storage_all_nodes':
        $query = $this->connection->select('google_analytics_counter_storage', 't');
        break;
      case 'node_field_data':
        $query = $this->connection->select('node_field_data', 'nfd');
        $query->fields('nfd');
        $query->condition('status', NodeInterface::PUBLISHED);
        break;
      case 'queue':
        $query = $this->connection->select('queue', 'q');
        $query->condition('name', 'google_analytics_counter_worker', '=');
        break;
      default:
        $query = $this->connection->select($table, 't');
        break;
    }
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Get the the top twenty results for pageviews and pageview_totals.
   *
   * @param string $table
   *   The table from which the results are selected.
   *
   * @return mixed
   */
  public function getTopTwentyResults($table) {
    $query = $this->connection->select($table, 't');
    $query->range(0, 20);
    $rows = [];
    switch ($table) {
      case 'google_analytics_counter':
        $query->fields('t', ['pagepath', 'pageviews']);
        $query->orderBy('pageviews', 'DESC');
        $result = $query->execute()->fetchAll();
        $rows = [];
        foreach ($result as $value) {
          $rows[] = [
            $value->pagepath,
            $value->pageviews,
          ];
        }
        break;
      case 'google_analytics_counter_storage':
        $query->fields('t', ['nid', 'pageview_total']);
        $query->orderBy('pageview_total', 'DESC');
        $result = $query->execute()->fetchAll();
        foreach ($result as $value) {
          $rows[] = [
            $value->nid,
            $value->pageview_total,
          ];
        }
        break;
      default:
        break;
    }

    return $rows;
  }

  /**
   * Save the pageview count for a given node.
   *
   * @param integer $nid
   *   The node id.
   * @param string $bundle
   *   The content type of the node.
   * @param int $vid
   *   Revision id value.
   *
   * @throws \Exception
   */
  public function updateStorage($nid, $bundle, $vid) {
    // Get all the aliases for a given node id.
    $aliases = [];
    $path = '/node/' . $nid;
    $aliases[] = $path;
    foreach ($this->languageManager->getLanguages() as $language) {
      $alias = $this->aliasManager->getAliasByPath($path, $language->getId());
      $aliases[] = $alias;
      if (array_key_exists($language->getId(), $this->prefixes) && $this->prefixes[$language->getId()]) {
        $aliases[] = '/' . $this->prefixes[$language->getId()] . $path;
        $aliases[] = '/' . $this->prefixes[$language->getId()] . $alias;
      }
    }

    // Add also all versions with a trailing slash.
    $aliases = array_merge($aliases, array_map(function ($path) {
      return $path . '/';
    }, $aliases));

    // It's the front page
    // Todo: Could be brittle
    if ($nid == substr(\Drupal::configFactory()->get('system.site')->get('page.front'), 6)) {
      $sum_of_pageviews = $this->sumPageviews(['/']);
      $this->mergeCounterStorage($nid, $sum_of_pageviews, $bundle, $vid);
    }
    else {
      $sum_of_pageviews = $this->sumPageviews(array_unique($aliases));
      $this->mergeCounterStorage($nid, $sum_of_pageviews, $bundle, $vid);
    }
  }

  /**
   * Update the path counts.
   *
   * @param int $index
   *   The index of the chunk to fetch and update.
   *
   * This function is triggered by hook_cron().
   *
   * @throws \Exception
   */
  public function updatePathCounts($index = 0) {
    $feed = $this->getChunkedResults($index);

    foreach ($feed->results->rows as $value) {
      // Use only the first 2047 characters of the pagepath. This is extremely long
      // but Google does store everything and Drupal can make URIs (like in views) that long.
      $page_path = substr(htmlspecialchars($value['pagePath'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 0, 2047);

      // Update the Google Analytics Counter.
      $this->connection->merge('google_analytics_counter')
        ->key('pagepath_hash', md5($page_path))
        ->fields([
          // Escape the path see https://www.drupal.org/node/2381703
          'pagepath' => $page_path,
          'pageviews' => $value['pageviews'],
        ])
        ->execute();
      }

    // Log the results.
    $this->logger->info($this->t('Saved @count paths from Google Analytics into the database.', ['@count' => count($feed->results->rows)]));
  }

  /****************************************************************************/
  // Field functions.
  /****************************************************************************/

  /**
   * Prepares to add the custom field and saves the configuration.
   *
   * @param $type
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param $key
   * @param $value
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function gacPreAddField($type, $config_factory, $key, $value) {
    $this->gacAddField($type);

    // Update the gac_type_{content_type} configuration.
    $config_factory->getEditable('google_analytics_counter.settings')
      ->set("general_settings.$key", $value)
      ->save();
  }

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
  public function gacAddField(NodeTypeInterface $type, $label = 'Google Analytics Counter') {

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
          '#description' => t('This field stores Google Analytics pageviews.'),
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
   * Prepares to delete the custom field and saves the configuration.
   *
   * @param $type
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param $key
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function gacPreDeleteField($type, $config_factory, $key) {
    $this->gacDeleteField($type);

    // Update the gac_type_{content_type} configuration.
    $config_factory->getEditable('google_analytics_counter.settings')
      ->set("general_settings.$key", NULL)
      ->save();
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
   * @see GoogleAnalyticsCounterConfigureTypesForm
   */
  public function gacDeleteField(NodeTypeInterface $type) {
    // Check if field exists on the content type.
    $content_type = $type->id();
    $config = FieldConfig::loadByName('node', $content_type, 'field_google_analytics_counter');
    if (!isset($config)) {
      return NULL;
    }
    // Delete the field from the content type.
    FieldConfig::loadByName('node', $content_type, 'field_google_analytics_counter')->delete();
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
  public function notAuthenticatedMessage($build = []) {
    $t_arg = [
      ':href' => Url::fromRoute('google_analytics_counter.admin_auth_form', [], ['absolute' => TRUE])
        ->toString(),
      '@href' => 'Authentication',
    ];
    \Drupal::messenger()->addWarning(t('Google Analytics have not been authenticated! Google Analytics Counter cannot fetch any new data. Please authenticate with Google from the <a href=:href>@href</a> page.', $t_arg));

    // Revoke Google authentication.
    $this->revokeAuthenticationMessage($build);
  }

  /**
   * Revoke Google Authentication Message.
   *
   * @param $build
   * @return mixed
   */
  public function revokeAuthenticationMessage($build) {
    $t_args = [
      ':href' => Url::fromRoute('google_analytics_counter.admin_auth_revoke', [], ['absolute' => TRUE])
        ->toString(),
      '@href' => 'revoking Google authentication',
    ];
    $build['cron_information']['revoke_authentication'] = [
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
  public function googleProjectName() {
    $config = $this->config;
    $project_name = !empty($config->get('general_settings.project_name')) ?
      Url::fromUri('https://console.developers.google.com/apis/api/analytics.googleapis.com/quotas?project=' . $config->get('general_settings.project_name'))
        ->toString() :
      Url::fromUri('https://console.developers.google.com/apis/api/analytics.googleapis.com/quotas')
        ->toString();

    return $project_name;
  }

  /**
   * Get the Profile name of the Google view from Drupal.
   *
   * @param string $profile_id
   *   The profile id used in the google query.
   *
   * @return string mixed
   */
  public function getProfileName($profile_id) {

    $profile_id = $this->state->get('google_analytics_counter.total_pageviews_' . $profile_id);
    if (!empty($profile_id)) {
      $profile_name = '<strong>' . $profile_id[key($profile_id)] . '</strong>';
    }
    else {
      $profile_name = '<strong>' . $this->t('Profile name is available after cron runs.') . '</strong>';
    }
    return $profile_name;
  }


  /****************************************************************************/
  // Uninstall functions.
  /****************************************************************************/

  /**
   * Delete states.
   */
  public function gacDeleteState() {
    $this->state->deleteMultiple([
      'google_analytics_counter.access_token',
      'google_analytics_counter.cron_next_execution',
      'google_analytics_counter.data_last_refreshed',
      'google_analytics_counter.data_step',
      'google_analytics_counter.expires_at',
      'google_analytics_counter.most_recent_query',
      'google_analytics_counter.refresh_token',
      'google_analytics_counter.total_nodes',
      'google_analytics_counter.total_pageviews',
      'google_analytics_counter.total_paths',
    ]);
  }


}

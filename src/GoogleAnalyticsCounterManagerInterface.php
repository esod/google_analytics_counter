<?php

namespace Drupal\google_analytics_counter;


/**
 * Class GoogleAnalyticsCounterManager.
 *
 * @package Drupal\google_analytics_counter
 */
interface GoogleAnalyticsCounterManagerInterface {

  /**
   * Begin authentication to Google authentication page with the client_id.
   */
  public function beginGacAuthentication();

  /**
   * Check to make sure we are authenticated with google.
   *
   * @return bool
   *   True if there is a refresh token set.
   */
  public function isAuthenticated();

  /**
   * Instantiate a new GoogleAnalyticsCounterFeed object.
   *
   * @return object
   *   GoogleAnalyticsCounterFeed object to authorize access and request data
   *   from the Google Analytics Core Reporting API.
   */
  public function newGaFeed();

  /**
   * Get the list of available web properties.
   *
   * @return array
   *   Array of options.
   */
  public function getWebPropertiesOptions();

  /**
   * Get the results from google in user specified amounts (chunks).
   *
   * @param string $profile_id
   *   The profile id used in the google query.
   * @param int $index
   *   The index of the chunk to fetch for the queue.
   *
   * @return \Drupal\google_analytics_counter\GoogleAnalyticsCounterFeed
   *   The returned feed after the request has been made.
   */
  public function getChunkedResults($profile_id, $index = 0);

  /**
   * Request report data.
   *
   * @param array $parameters
   *   An associative array containing:
   *   - profile_id: required
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
  public function reportData(array $parameters, array $cache_options);

  /**
   * Get the count of pageviews for a path.
   *
   * @param string $path
   *   The path to look up.
   *
   * @return string
   *   Count of page views.
   */
  public function displayGaCount($path);

  /**
   * Update the path counts.
   *
   * @param int $index
   *   The index of the chunk to fetch and update.
   * @param string $profile_id
   *   The profile id used in the google query.
   *
   * This function is triggered by hook_cron().
   *
   * @throws \Exception
   */
  public function updatePathCounts($index = 0, $profile_id = '');

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
  public function updateStorage($nid, $bundle, $vid);

  /**
   * Get the row count of a table, sometimes with conditions.
   *
   * @param string $table
   *
   * @return mixed
   */
  public function getCount($table);

  /**
   * Get the the top twenty results for pageviews and pageview_totals.
   *
   * @param string $table
   *
   * @return mixed
   */
  public function getTopTwentyResults($table);

  /**
   * Prints a warning message when not authenticated.
   */
  public function notAuthenticatedMessage();

  /**
   * Revoke Google Authentication Message.
   *
   * @param $build
   *
   * @return mixed
   */
  public function revokeAuthenticationMessage($build);

  /**
   * Returns the link with the Google project name if it is available.
   *
   * @return string
   *   Project name.
   */
  public function googleProjectName();

  /**
   * Programmatically revoke stored state values.
   */
  public function revoke();

  /**
   * Programmatically revoke stored profile state values.
   *
   * @param string $profile_id
   *   The profile id used in the google query.
   */
  public function revokeProfiles($profile_id);
}
<?php

namespace Drupal\google_analytics_counter;

/**
 * Class GoogleAnalyticsCounterManagerInterface.
 *
 * @package Drupal\google_analytics_counter
 */
interface GoogleAnalyticsCounterManagerInterface {

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
   *   List of web properties.
   */
  public function getWebPropertiesOptions();

  /**
   * Begin authentication to Google authentication page with the client_id.
   */
  public function beginGacAuthentication();

  /**
   * Get the results from google.
   *
   * @param string $profile_id
   *   The profile id used in the google query.
   * @param int $index
   *   The index of the chunk to fetch so that it can be queued.
   *
   * @return \Drupal\google_analytics_counter\GoogleAnalyticsCounterFeed
   *   The returned feed after the request has been made.
   */
  public function getChunkedResults($profile_id, $index = 0);

  /**
   * Update the path counts.
   *
   * This function is triggered by hook_cron().
   *
   * @param string $profile_id
   *   The profile id used in the google query.
   * @param int $index
   *   The index of the chunk to fetch and update.
   */
  public function updatePathCounts($profile_id, $index = 0);

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
   * Request report data.
   *
   * @param array $parameters
   *   An associative array containing:
   *   - profile_id: required [default='ga:profile_id']
   *   - metrics: required [ga:pageviews]
   *   - dimensions: optional [default=none]
   *   - sort_metric: optional [default=none]
   *   - filters: optional [default=none]
   *   - segment: optional [default=none]
   *   - start_date: [default=-1 week]
   *   - end_date: optional [default=tomorrow]
   *   - start_index: [default=1]
   *   - max_results: optional [default=10,000].
   * @param array $cache_options
   *   An optional associative array containing:
   *   - cid: optional [default=md5 hash]
   *   - expire: optional [default=CACHE_TEMPORARY]
   *   - refresh: optional [default=FALSE].
   *
   * @return \Drupal\google_analytics_counter\GoogleAnalyticsCounterFeed
   *   A new GoogleAnalyticsCounterFeed object
   */
  public function reportData($parameters = [], $cache_options = []);

  /**
   * Get the count of pageviews for a path.
   *
   * @param string $path
   *   The path to look up.
   *
   * @return string
   *   Count of pageviews.
   */
  public function displayGaCount($path);

  /**
   * Programatically revoke token.
   */
  public function revoke();

  /**
   * Get the row count of a table, sometimes with conditions.
   *
   * @param string $table
   *   Table data.
   *
   * @return mixed
   *   Row count.
   */
  public function getCount($table);

  /**
   * Get the the top twenty results for pageviews and pageview_totals.
   *
   * @param string $table
   *   Table data.
   *
   * @return mixed
   *   Results for pageviews.
   */
  public function getTopTwentyResults($table);

  /**
   * Prints a warning message when not authenticated.
   */
  public function notAuthenticatedMessage();

  /**
   * Revoke Google Authentication Message.
   *
   * @param array $build
   *   Build data.
   *
   * @return mixed
   *   Build array.
   */
  public function revokeAuthenticationMessage($build);

  /**
   * Sets the Google project name which is used in multiple places.
   *
   * @return string
   *   Project name
   */
  public function googleProjectName();

}

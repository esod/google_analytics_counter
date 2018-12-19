<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterFeed;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GoogleAnalyticsCounterAuthForm.
 *
 * @package Drupal\google_analytics_counter\Form
 */
class GoogleAnalyticsCounterAuthForm extends ConfigFormBase {

  /**
   * The google_analytics_counter.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface
   */
  protected $manager;

  /**
   * Constructs a new SiteMaintenanceModeForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface $manager
   *   Google Analytics Counter Manager object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, GoogleAnalyticsCounterManagerInterface $manager) {
    parent::__construct($config_factory);
    $this->config = $config_factory->get('google_analytics_counter.settings');
    $this->state = $state;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('google_analytics_counter.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_analytics_counter_admin_auth';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['google_analytics_counter.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $base_url;
    $config = $this->config('google_analytics_counter.settings');

    $form['#tree'] = TRUE;

    // Initialize the feed to trigger the fetching of the tokens.
    $this->manager->newGaFeed();

    if ($this->manager->isAuthenticated() === TRUE) {
      $form['revoke'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Revoke authentication'),
        '#description' => $this->t('This action will revoke authentication from Google Analytics.'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#weight' => 5,
      ];
      $form['revoke']['revoke_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Revoke authentication'),
      ];
    }
    else {
      if ($config->get('general_settings.client_id') !== '') {
        $form['authenticate'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Authenticate with Google Analytics'),
          '#description' => $this->t("This action will redirect you to Google. Login with the account you'd like to use."),
          '#collapsible' => TRUE,
          '#collapsed' => FALSE,
        ];
        $form['authenticate']['authenticate_submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Authenticate'),
        ];
      }
    }

    $t_arg = [
      ':href' => Url::fromRoute('google_analytics_counter.admin_dashboard_form', [], ['absolute' => TRUE])
        ->toString(),
      '@href' => 'Dashboard',
    ];
    $markup_description = ($this->manager->isAuthenticated() === TRUE) ? '<p>' . $this->t('Client ID, Client Secret, and Authorized redirect URI can only be changed when not authenticated.') .
      '<ol><li>' . $this->t('Now that you are authenticated with Google Analytics, select the') .  '<strong>' . $this->t(' Google Views ') . '</strong>' . $this->t('to collect analytics from and click Save configuration.') .
      '</li><li>' . $this->t('Save configuration.') .
      '</li><li>' . $this->t('On the next cron job, analytics from the Google View field and the Additional Google Views field will be saved to Drupal.') .
      '</li><ul><li>' . $this->t('Information on the <a href=:href>@href</a> page is derived from the Google View field, not the Additional Google Views field.', $t_arg) .
      '</li><li>' . $this->t('After cron runs, check pageviews for all selected Google Views on the <a href=:href>@href</a>  page in the Top Twenty Results section.', $t_arg) .
      '</li></ul></ol></p>' :
      '<ol><li>' . $this->t('Fill in your Client ID, Client Secret, Authorized Redirect URI, and Google Project Name.') .
      '</li><li>' . $this->t('Save configuration.') .
      '</li><li>' . $this->t('Click Authenticate in Authenticate with Google Analytics above.') .
      '</li><ul><li>' .  $this->t('If you don\'t already have Google Analytics set up in Google, follow the instructions in the README.md included with this module.') .
      '</li><li>' .  $this->t('After setting up Google Analytics, come back to this page and click the Authenticate button above.') .
      '</li></ul><li>' . $this->t('After authenticating with Google Analytics, select the') . '<strong>' . $this->t(' Google View ') . '</strong>' . $this->t('to collect analytics from and click Save configuration.') .
      '</li><ul><li>' .  $this->t('If you are not authenticated,') . '<strong>' . $this->t(' Unauthenticated ') . '</strong>' . $this->t('is the only available option for ') .  '<strong>' . $this->t('Google View') . '</strong>.</li></ul></ol>';

    $form['setup'] = [
      '#type' => 'markup',
      '#markup' => '<h4>' . $this->t('Google Analytics Setup') . '</h4>' . $markup_description,
      '#weight' => 10,
    ];

    $t_args = [
      ':href' => Url::fromUri('http://code.google.com/apis/console')->toString(),
      '@href' => 'Google API Console',
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('general_settings.client_id'),
      '#size' => 90,
      '#description' => $this->t('Create the Client ID in the access tab of the <a href=:href target="_blank">@href</a>.', $t_args),
      '#disabled' => $this->manager->isAuthenticated() === TRUE,
      '#weight' => 11,
    ];

    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('general_settings.client_secret'),
      '#size' => 90,
      '#description' => $this->t('Create the Client secret in the <a href=:href target="_blank">@href</a>.', $t_args),
      '#disabled' => $this->manager->isAuthenticated() === TRUE,
      '#weight' => 12,
    ];

    $current_path = \Drupal::service('path.current')->getPath();
    $uri = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);
    $description = ($this->manager->isAuthenticated() === TRUE) ? $this->t('The path that users are redirected to after they have authenticated with Google.') : $this->t('The path that users are redirected to after they have authenticated with Google.<br /> Default: <strong>@default_uri</strong>', ['@default_uri' => $base_url . $uri]);
    $form['redirect_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authorized Redirect URI'),
      '#default_value' => $config->get('general_settings.redirect_uri'),
      '#size' => 90,
      '#description' => $description,
      '#disabled' => $this->manager->isAuthenticated() === TRUE,
      '#weight' => 13,
    ];

    $t_args = [
      ':href' => $this->manager->googleProjectName(),
      '@href' => 'Analytics API',
    ];

    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Project Name'),
      '#default_value' => $config->get('general_settings.project_name'),
      '#description' => $this->t("Optionally add your Google Project's machine name. Machine names are written like <em>project-name</em>. This field helps to take you directly to your <a href=:href>@href</a> page to view quotas.", $t_args),
      '#weight' => 14,
    ];

    $options = !empty($this->manager->getWebPropertiesOptions()) ? $this->manager->getWebPropertiesOptions() : ['unauthenticated' => 'Unauthenticated'];
    $t_arg = [
      ':href' => Url::fromRoute('google_analytics_counter.admin_dashboard_form', [], ['absolute' => TRUE])
        ->toString(),
      '@href' => 'dashboard',
    ];
    $form['profile_id'] = [
      '#type' => 'select',
      '#title' => $this->t("First Google View"),
      '#options' => $options,
      '#default_value' => $config->get('general_settings.profile_id'),
      '#description' => $this->t("Choose the first Google Analytics view to collect pageviews from. This is the view whose data will be shown on the <a href=:href>@href</a>. If you are not authenticated, 'Unauthenticated' is the only available option.", $t_arg),
      '#weight' => 15,
    ];

    $form['multiple_ids'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t("Other Google Views"),
      '#options' => $options,
      '#default_value' => $config->get('general_settings.multiple_ids'),
      '#description' => $this->t("Choose other Google Analytics views. The road map for this module includes combining the First Google View with Other Google Views. If you are not authenticated, 'Unauthenticated' is the only available option."),
      '#weight' => 16,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Authenticates tokens and saves configuration.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');

    switch ($form_state->getValue('op')) {
      case (string) $this->t('Authenticate'):
        $this->manager->beginGacAuthentication();
        break;

      case (string) $this->t('Revoke authentication'):
        $form_state->setRedirectUrl(Url::fromRoute('google_analytics_counter.admin_auth_revoke'));
        break;

      default:
        $options = !empty($this->manager->getWebPropertiesOptions()) ? $this->manager->getWebPropertiesOptions() : ['unauthenticated' => 'Unauthenticated'];
        $profile_id = $form_state->getValue('profile_id');
        $profile_name = $this->searchArrayValueByKey($options, (int) $profile_id);

        $multiple_ids = $form_state->getValue('multiple_ids');
        $config
          ->set('general_settings.client_id', $form_state->getValue('client_id'))
          ->set('general_settings.client_secret', $form_state->getValue('client_secret'))
          ->set('general_settings.redirect_uri', $form_state->getValue('redirect_uri'))
          ->set('general_settings.project_name', $form_state->getValue('project_name'))
          ->set('general_settings.profile_id', $form_state->getValue('profile_id'))
          ->set('general_settings.profile_name', $profile_name)
          ->set('general_settings.multiple_ids', array_combine($multiple_ids, $multiple_ids))
          ->save();

        parent::submitForm($form, $form_state);
        break;
    }
  }

  /**
   * Search value by key in multidimensional array
   * @param array $array
   * @param $search
   *
   * @return bool|mixed
   *
   * @see https://snipplr.com/view/55684/
   */
  protected function searchArrayValueByKey(array $array, $search) {
    foreach (new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array)) as $key => $value) {
      if ($search === $key)
        return $value;
    }
    return false;
  }

}

<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterHelper;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilder;

use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeTypeInterface;



/**
 * The form for editing content types with the custom google analytics counter field.
 *
 * @internal
 */
class GoogleAnalyticsCounterConfigureContentTypesForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle information service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The entity type definition object.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('messenger'),
      $container->get('google_analytics_counter.manager')

    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $bundle_info,
    MessengerInterface $messenger,
    GoogleAnalyticsCounterManagerInterface $manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfo = $bundle_info;
    $this->messenger = $messenger;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_analytics_counter_type_edit_form';
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
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $config = $this->config('google_analytics_counter.settings');
    $form['#prefix'] = '<div id="gac_modal_form">';
    $form['#suffix'] = '</div>';

    // The status messages that will contain any form errors.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    // Add a checkbox field for each content type.
    $content_types = \Drupal::service('entity.manager')->getStorage('node_type')->loadMultiple();
    foreach ($content_types as $machine_name => $content_type) {
      $form["gac_type_$machine_name"] = [
        '#type' => 'checkbox',
        '#title' => $content_type->label(),
        '#default_value' => $config->get("general_settings.gac_type_$machine_name"),
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Save'),
      '#ajax' => [
        'callback' => [$this, 'gacModalFormAjax'],
        'event' => 'click',
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#ajax' => [
        'callback' => [$this, 'gacModalFormAjax'],
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function gacModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#gac_modal_form', $form));
    }
    else {
      $response->addCommand(new CloseDialogCommand());
      $response->addCommand(new OpenModalDialogCommand("The content types you selected now have the custom google analytics counter field:", 'Please go to the Manage form display and the Manage display tabs of the content type (e.g. admin/structure/types/manage/article/display) and enable the custom field as you wish.', ['width' => 800]));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    $config_factory = \Drupal::configFactory();
    foreach ($values as $key => $value) {
      $config_factory->getEditable('google_analytics_counter.settings')
        ->set("general_settings.$key", $value)
        ->save();
    }

    // Add the field if it has been checked.
    foreach ($values as $key => $value) {
      $type = \Drupal::service('entity.manager')->getStorage('node_type')->load(substr($key, 9));
      if ($value == 1) {
        GoogleAnalyticsCounterHelper::gacAddField($type);
      }
      else {
        GoogleAnalyticsCounterHelper::gacDeleteField($type);
      }
    }
  }

  /**
   * Route title callback.
   */
  public function getTitle() {
    return $this->t('Check the content types you wish to add the custom field to.');
  }

}
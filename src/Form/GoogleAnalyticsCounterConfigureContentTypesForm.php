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
use Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilder;

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
    return 'google_analytics_counter_content_type_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
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
      $form["gac_content_type_$machine_name"] = [
        '#type' => 'checkbox',
        '#title' => $content_type->label(),
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
      $response->addCommand(new OpenModalDialogCommand("Success!", 'The modal form has been submitted.', ['width' => 800]));
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
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['config.gac_modal_form'];
  }

  /**
   * Route title callback.
   */
  public function getTitle() {
    return $this->t('Select the content types to add the custom field to.');
  }

}
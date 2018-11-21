<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterManagerInterface;

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
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (\Drupal::moduleHandler()->moduleExists('field_ui')) {
      $form['amp_content_amp_status'] = [
        '#title' => $this->t('AMP Status by Content Type'),
        '#theme' => 'item_list',
        '#items' => $this->manager->getContentTypes(),
      ];
    }
    else {
      $form['amp_content_amp_status'] = [
        '#type' => 'item',
        '#title' => $this->t('AMP Status by Content Type'),
        '#markup' => $this->t('(In order to enable and disable AMP content types in the UI, the Field UI module must be enabled.)'),
      ];
    }



    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Save'),
      '#ajax' => [
        'callback' => [$this, 'ajaxcallback'],
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#ajax' => [
        'callback' => [$this, 'ajaxcallback'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('bundles') as $bundle_id => $checked) {
      if ($checked) {
        $this->workflow->getTypePlugin()->addEntityTypeAndBundle($this->entityType->id(), $bundle_id);
      }
      else {
        $this->workflow->getTypePlugin()->removeEntityTypeAndBundle($this->entityType->id(), $bundle_id);
      }
    }
    $this->workflow->save();
  }

  /**
   * Ajax callback to close the modal and update the selected text.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response object.
   */
  public function ajaxCallback() {
    $selected_bundles = [];
    foreach ($this->bundleInfo->getBundleInfo($this->entityType->id()) as $bundle_id => $bundle) {
      if ($this->workflow->getTypePlugin()->appliesToEntityTypeAndBundle($this->entityType->id(), $bundle_id)) {
        $selected_bundles[$bundle_id] = $bundle['label'];
      }
    }
    $selected_bundles_list = [
      '#theme' => 'item_list',
      '#items' => $selected_bundles,
      '#context' => ['list_style' => 'comma-list'],
      '#empty' => $this->t('none'),
    ];
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand());
    $response->addCommand(new HtmlCommand('#selected-' . $this->entityType->id(), $selected_bundles_list));
    return $response;
  }

  /**
   * Route title callback.
   */
  public function getTitle($content_type_id) {
    $this->entityType = $this->entityTypeManager->getDefinition($content_type_id);
    dsm($this->entityType);

    $title = $this->t('Select the @entity_type types for the @workflow workflow', ['@entity_type' => $this->entityType->getLabel()]);
    if ($bundle_entity_type_id = $this->entityType->getBundleEntityType()) {
      $title = $this->t('Select the @entity_type_plural_label for the @workflow workflow', ['@entity_type_plural_label' => $this->entityTypeManager->getDefinition($bundle_entity_type_id)->getPluralLabel()]);
    }

    return $title;
  }

}

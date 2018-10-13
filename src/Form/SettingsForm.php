<?php

namespace Drupal\message_private\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure file system settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity manager object.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_private_system_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['message_private.settings'];
  }

  /**
   * Holds the name of the keys we holds in the variable.
   */
  public function defaultKeys() {
    return [
      'message_limit',
      'email_notify',
      'message_add_local_action',
      'default_limit',
      'default_interval',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.manager')
    );
  }

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityManagerInterface $entity_manager) {
    parent::__construct($config_factory);
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('message_private.settings');

    // Global email notifications on/off checkbox.
    $form['email_notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Message Private Email Notifications'),
      '#default_value' => $config->get('email_notify'),
      '#description' => $this->t('Global On / Off checkbox for emails notifying users of a new private message'),
    ];

    // Local action links on/off checkbox.
    $form['message_add_local_action'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Create a New Message local action links'),
      '#default_value' => $config->get('message_add_local_action'),
      '#description' => $this->t('Disable local action links to create new message on user pages.'),
    ];

    // Role based message create limit on/off checkbox.
    $form['message_limit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit Message Create By Role'),
      '#default_value' => $config->get('message_limit'),
      '#description' => $this->t('Impose a message creation limit per interval. Users with multiple roles, get the highest limit from these roles'),
    ];

    // Conditional fieldset for all message limitation settings.
    $form['interval_limit'] = [
      '#title' => $this->t('Message interval limits'),
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#states' => [
        'invisible' => [
          ':input[name="message_limit"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // Add a default fieldset.
    $form['interval_limit'][MESSAGE_PRIVATE_DEFAULT_INDEX] = [
      '#type' => 'fieldset',
      '#title' => 'Default limit',
      '#description' => $this->t('Applies to all roles with blank entries below'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['interval_limit'][MESSAGE_PRIVATE_DEFAULT_INDEX]['default_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#default_value' => $config->get('default_limit'),
      '#description' => $this->t('Enter a message limit') . ' ' . MESSAGE_PRIVATE_MESSAGE_LIMIT_MIN . ' - ' . MESSAGE_PRIVATE_MESSAGE_LIMIT_MAX,
    ];

    $form['interval_limit'][MESSAGE_PRIVATE_DEFAULT_INDEX]['default_interval'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Interval'),
      '#default_value' => $config->get('default_interval'),
      '#description' => $this->t('Enter an interval in minutes') . ' ' . MESSAGE_PRIVATE_MESSAGE_INTERVAL_MIN . ' - ' . MESSAGE_PRIVATE_MESSAGE_INTERVAL_MAX,
    ];

    // Generate variable names for all roles used with get/set in admin form.
    foreach (user_roles() as $id => $role) {
      $role_name = $role->id();
      $limit_name = 'message_private_' . $role_name . '_limit';
      $interval_name = 'message_private_' . $role_name . '_interval';

      $form['interval_limit'][$id] = [
        '#type' => 'fieldset',
        '#title' => $role->label(),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];

      $form['interval_limit'][$id][$limit_name] = [
        '#type' => 'textfield',
        '#title' => $this->t('Limit'),
        '#default_value' => $config->get($limit_name),
        '#description' => $this->t('Enter a message limit') . ' ' . MESSAGE_PRIVATE_MESSAGE_LIMIT_MIN . ' - ' . MESSAGE_PRIVATE_MESSAGE_LIMIT_MAX,
      ];

      $form['interval_limit'][$id][$interval_name] = [
        '#type' => 'textfield',
        '#title' => $this->t('Interval'),
        '#default_value' => $config->get($interval_name),
        '#description' => $this->t('Enter an interval in minutes') . ' ' . MESSAGE_PRIVATE_MESSAGE_INTERVAL_MIN . ' - ' . MESSAGE_PRIVATE_MESSAGE_INTERVAL_MAX,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* @var $config \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig */
    $config = \Drupal::service('config.factory')->getEditable('message_private.settings');

    // Init limit and interval keys.
    $limit_names = [];
    $interval_names = [];

    // Cycle through each role and set up valid keys.
    foreach (user_roles() as $id => $role) {
      $role_name = $role->id();
      $limit_names[] = 'message_private_' . $role_name . '_limit';
      $interval_names[] = 'message_private_' . $role_name . '_interval';
    }

    foreach ($form_state->getValues() as $key => $value) {
      // Store only default setting or dynamic valid key values.
      if (in_array($key, $this->defaultKeys())
        || in_array($key, $limit_names)
        || in_array($key, $interval_names)) {
        $config->set($key, $form_state->getValue($key));
      }
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate the default fieldset values.
    $this->validateFieldset($form_state,
      $form['interval_limit'][MESSAGE_PRIVATE_DEFAULT_INDEX]['default_limit'],
      $form['interval_limit'][MESSAGE_PRIVATE_DEFAULT_INDEX]['default_interval'],
      $form_state->getValue('default_limit'),
      $form_state->getValue('default_interval')
    );

    // Cycle through the settings for each role and validate.
    foreach (user_roles() as $id => $role) {
      $role_name = $role->id();
      $limit_name = 'message_private_' . $role_name . '_limit';
      $interval_name = 'message_private_' . $role_name . '_interval';

      $this->validateFieldset(
        $form_state,
        $form['interval_limit'][$id][$limit_name],
        $form['interval_limit'][$id][$interval_name],
        $form_state->getValue($limit_name),
        $form_state->getValue($interval_name)
      );
    }
  }

  /**
   * Validate limit and interval values and show any errors on form elements.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param mixed $limit_element
   *   Limit form element reference.
   * @param mixed $interval_element
   *   Interval form element reference.
   * @param string|int $limit
   *   Limit value to validate.
   * @param string|int $interval
   *   Interval value to validate.
   */
  private function validateFieldset(FormStateInterface $form_state, &$limit_element, &$interval_element, $limit, $interval) {
    // Validate role settings, check both textfields per fieldset are set.
    if (!empty($limit) && !empty($interval)) {
      // Check is numeric and between the boundaries.
      if (!ctype_digit($limit) || $limit > MESSAGE_PRIVATE_MESSAGE_LIMIT_MAX || $limit < MESSAGE_PRIVATE_MESSAGE_LIMIT_MIN) {
        $form_state->setErrorByName($limit_element,
          $this->t('Enter a numerical message limit between') . ' '
          . MESSAGE_PRIVATE_MESSAGE_LIMIT_MIN . ' - ' . MESSAGE_PRIVATE_MESSAGE_LIMIT_MAX . '.');
      }
      // Check is numeric and between the boundaries.
      if (!ctype_digit($interval) || $interval > MESSAGE_PRIVATE_MESSAGE_INTERVAL_MAX || $interval < MESSAGE_PRIVATE_MESSAGE_INTERVAL_MIN) {
        $form_state->setErrorByName($interval_element,
           $this->t('Enter a numerical interval in minutes between') . ' '
          . MESSAGE_PRIVATE_MESSAGE_INTERVAL_MIN . ' - ' . MESSAGE_PRIVATE_MESSAGE_INTERVAL_MAX . '.');
      }
    }
    elseif (!empty($limit) || !empty($interval)) {
      // Show error if only 1 textfield is set in each fieldset.
      $form_state->setErrorByName($limit_element, $this->t('Both a limit and interval value are required.'));
      $form_state->setErrorByName($interval_element);
    }
  }

}

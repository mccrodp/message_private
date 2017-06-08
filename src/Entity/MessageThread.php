<?php

namespace Drupal\message_private\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\Language;
use Drupal\Core\Render\Markup;
use Drupal\message\MessageException;
use Drupal\message\MessageInterface;
use Drupal\message\MessageThreadInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Message Thread entity class.
 *
 * @ContentEntityType(
 *   id = "message_thread",
 *   label = @Translation("Message Thread"),
 *   bundle_label = @Translation("Message thread"),
 *   module = "message_private",
 *   config_prefix = "thread",
 *   base_table = "message_thread",
 *   data_table = "message_thread_field_data",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "thread_id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "uid" = "uid"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\message_private\MessageThreadListBuilder",
 *     "views_data" = "Drupal\message_private\MessageThreadViewsData",
 *   },
 * )
 */
class MessageThread extends ContentEntityBase {

  /**
   * Holds the arguments of the message instance.
   *
   * @var array
   */
  protected $arguments;

  /**
   * The language to use when fetching text from the message thread.
   *
   * @var string
   */
  protected $language = Language::LANGCODE_NOT_SPECIFIED;

  /**
   * {@inheritdoc}
   */
  public function setThread(MessageThreadInterface $thread) {
    $this->set('thread', $thread);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getThread() {
    return MessageThread::load($this->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->getEntityKey('uid');
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->get('uuid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getArguments() {
    $arguments = $this->get('arguments')->getValue();

    // @todo: See if there is a easier way to get only the 0 key.
    return $arguments ? $arguments[0] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setArguments(array $values) {
    $this->set('arguments', $values);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['thread_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Thread ID'))
      ->setDescription(t('The thread ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The message UUID'))
      ->setReadOnly(TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The message language code.'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The user that created the thread.'))
      ->setSettings([
        'target_type' => 'user',
        'default_value' => 0,
      ])
      ->setDefaultValueCallback('Drupal\message\Entity\Message::getCurrentUserId')
      ->setTranslatable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDescription(t('The time that the thread was created.'))
      ->setTranslatable(TRUE);

    $fields['arguments'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Arguments'))
      ->setDescription(t('Holds the arguments of the thread in serialise format.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getText($langcode = Language::LANGCODE_NOT_SPECIFIED, $delta = NULL) {
    if (!$message_thread = $this->getThread()) {
      // Message thread does not exist any more.
      // We don't throw an exception, to make sure we don't break sites that
      // removed the message thread, so we silently ignore.
      return [];
    }

    $message_arguments = $this->getArguments();
    $message_thread_text = $message_thread->getText($langcode, $delta);

    $output = $this->processArguments($message_arguments, $message_thread_text);

    $token_options = $message_thread->getSetting('token options', []);
    if (!empty($token_options['token replace'])) {
      // Token should be processed.
      $output = $this->processTokens($output, !empty($token_options['clear']));
    }

    return $output;
  }

  /**
   * Process the message given the arguments saved with it.
   *
   * @param array $arguments
   *   Array with the arguments.
   * @param array $output
   *   Array with the threadd text saved in the message thread.
   *
   * @return array
   *   The threaded text, with the placeholders replaced with the actual value,
   *   if there are indeed arguments.
   */
  protected function processArguments(array $arguments, array $output) {
    // Check if we have arguments saved along with the message.
    if (empty($arguments)) {
      return $output;
    }

    foreach ($arguments as $key => $value) {
      if (is_array($value) && !empty($value['callback']) && is_callable($value['callback'])) {

        // A replacement via callback function.
        $value += ['pass message' => FALSE];

        if ($value['pass message']) {
          // Pass the message object as-well.
          $value['arguments']['message'] = $this;
        }

        $arguments[$key] = call_user_func_array($value['callback'], $value['arguments']);
      }
    }

    foreach ($output as $key => $value) {
      $output[$key] = new FormattableMarkup($value, $arguments);
    }

    return $output;
  }

  /**
   * Replace placeholders with tokens.
   *
   * @param array $output
   *   The threadd text to be replaced.
   * @param bool $clear
   *   Determine if unused token should be cleared.
   *
   * @return array
   *   The output with placeholders replaced with the token value,
   *   if there are indeed tokens.
   */
  protected function processTokens(array $output, $clear) {
    $options = [
      'langcode' => $this->language,
      'clear' => $clear,
    ];

    foreach ($output as $key => $value) {
      $output[$key] = \Drupal::token()
        ->replace($value, ['message' => $this], $options);
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $token_options = !empty($this->data['token options']) ? $this->data['token options'] : [];

    $tokens = [];

    // Require a valid thread when saving.
    if (!$this->getThread()) {
      throw new MessageException('No valid thread found.');
    }

    // Handle hard coded arguments.
    foreach ($this->getThread()->getText() as $text) {
      preg_match_all('/[@|%|\!]\{([a-z0-9:_\-]+?)\}/i', $text, $matches);

      foreach ($matches[1] as $delta => $token) {
        $output = \Drupal::token()->replace('[' . $token . ']', ['message' => $this], $token_options);
        if ($output != '[' . $token . ']') {
          // Token was replaced and token sanitizes.
          $argument = $matches[0][$delta];
          $tokens[$argument] = Markup::create($output);
        }
      }
    }

    $arguments = $this->getArguments();
    $this->setArguments(array_merge($tokens, $arguments));

    parent::save();
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\message\MessageInterface
   *   A message entity ready to be save.
   */
  public static function create(array $values = []) {
    return parent::create($values);
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\message\MessageInterface
   *   A requested message entity.
   */
  public static function load($id) {
    return parent::load($id);
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\message\MessageInterface[]
   *   Array of requested message entities.
   */
  public static function loadMultiple(array $ids = NULL) {
    return parent::loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public static function deleteMultiple(array $ids) {
    \Drupal::entityTypeManager()->getStorage('message')->delete($ids);
  }

  /**
   * {@inheritdoc}
   */
  public static function queryByThread($thread) {
    return \Drupal::entityQuery('message')
      ->condition('thread', $thread)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return trim(implode("\n", $this->getText()));
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguage($language) {
    $this->language = $language;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return array(\Drupal::currentUser()->id());
  }

}

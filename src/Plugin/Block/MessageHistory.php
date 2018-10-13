<?php

namespace Drupal\message_private\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandler;

/**
 * Provides a 'Message History' Block with links to all new messages.
 *
 * @Block(
 *   id = "message_private_new_message",
 *   admin_label = @Translation("New Messages"),
 * )
 */
class MessageHistory extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Constructs a MessageHistory object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Drupal module handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandler $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$this->moduleHandler->moduleExists('message_random')) {
      return $this->messageHistoryModuleRequired();
    }
    $result = $this->getUnreadMessages();
    $children = [];
    foreach ($result as $row) {
      $children[$row->mid] = [
        'New message' => [
          '#markup' => '<a href="/message/' . $row->mid . '">' . $row->name . '</a>',
          '#wrapper_attributes' => [
            'class' => ['message-history-item'],
          ],
        ],
      ];
    }

    if (empty($children)) {
      return [
        '#markup' => t('You have no new messages'),
      ];
    }
    $items[] = [
      '#theme' => 'item_list',
      '#items' => $children,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $items;
  }

  /**
   * TBD.
   */
  protected function getUnreadMessages() {
    // Find messages for the current user.
    return db_query("SELECT mfd.mid, mfd.uid, ufd.name
      FROM {message_field_data} mfd
      LEFT JOIN {message__field_message_private_to_user} pu 
      ON mfd.mid = pu.entity_id
      LEFT JOIN {users_field_data} ufd
      ON mfd.uid = ufd.uid
      WHERE NOT EXISTS (
        SELECT timestamp FROM message_history
        WHERE  mfd.mid = message_history.mid
        AND message_history.uid = :uid
      )
      AND pu.field_message_private_to_user_target_id = :uid 
      AND mfd.created > :limit
      AND mfd.uid != :uid", [
        ':uid' => \Drupal::currentUser()->id(),
        ':limit' => HISTORY_READ_LIMIT,
      ]);
  }

  /**
   * TBD.
   */
  protected function messageHistoryModuleRequired() {
    return [
      '#markup' => t('Enable Message History Module to display New Messages block'),
    ];
  }

}

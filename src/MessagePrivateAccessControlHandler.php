<?php

namespace Drupal\message_private;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for private messages
 * Access is controlled to users who are privy to the message
 *
 * @see \Drupal\message\Entity\Message
 */
class MessagePrivateAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

      // If checking whether a message of a particular template may be created.
      if ($account->hasPermission('administer message private')
        || $account->hasPermission('bypass private message access control')) {
        return AccessResult::allowed()->cachePerPermissions();
      }

      $current_id = $account->id();
      $allow = [];
      if ($operation == 'view' && $entity->get('field_message_private_to_user')->getValue() != NULL) {
        if (AccessResult::allowedIfHasPermission($account, 'view own messages')) {
          foreach ($entity->get('field_message_private_to_user')->getValue() as $value) {
            $allow[] = $value['target_id'];
          }

        }
      }
      if ($entity->get('uid')->getValue() != NULL) {

        switch ($operation) {
          case 'view':
            if (AccessResult::allowedIfHasPermission($account, 'view own messages')) {
              $allow[] = $entity->get('uid')->getValue()[0]['target_id'];
            }
            break;

          case 'edit':
            if (AccessResult::allowedIfHasPermission($account, 'edit own messages')) {
              $allow[] = $entity->get('uid')->getValue()[0]['target_id'];
            }
            break;

          case 'delete':
            if (AccessResult::allowedIfHasPermission($account, 'delete own messages')) {
              $allow[] = $entity->get('uid')->getValue()[0]['target_id'];
            }
            break;

        }

      }
      if (in_array($current_id, $allow)) {
        return AccessResult::allowed();
      }
      else return AccessResult::forbidden();


  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create a private message', 'administer message private'], 'OR');
  }

}

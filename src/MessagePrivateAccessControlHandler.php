<?php

namespace Drupal\message_private;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the message entity.
 *
 * @see \Drupal\message\Entity\Message.
 */
class MessagePrivateAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    if ($account->hasPermission('administer message private')
      || $account->hasPermission('bypass private message access control')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    // Verify that the user can apply the op.
    if ($account->hasPermission($operation . ' any private message')
      || $account->hasPermission($operation . ' own private messages', $account)) {
      if ($operation != 'create') {
        // Check if the user is message author.
        /* @var Drupal\message\Entity\message $message */
        if ($entity->getOwnerId() == $account->id()) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        // Grant view access for recipients of the private message.
        if ($operation == 'view') {
          $users = $entity->get('field_message_private_to_user')->getValue();
          if ($users && is_array($users)) {
            foreach ($users as $user_ref) {
              if ($user_ref['target_id'] == $account->id()) {
                return AccessResult::allowed()->cachePerPermissions();
              }
            }
          }
        }
        // Deny if user is not message author or viewing recipient.
        return AccessResult::forbidden()->cachePerPermissions();
      }
      else {
        return AccessResult::allowed()->cachePerPermissions();
      }
    }
    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create a private message');
  }

}

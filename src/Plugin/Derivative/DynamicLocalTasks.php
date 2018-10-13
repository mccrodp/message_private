<?php

namespace Drupal\message_private\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Defines dynamic local tasks.
 */
class DynamicLocalTasks extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Create the "Create message for___" tasks.
    // @todo set no task for 'user/%' 'user/%/messages' where variable_get('message_private_local_action_links', FALSE)
    // @todo: to be set only for users with private message permissions.
    $this->derivatives['message_private.messages'] = $base_plugin_definition;
    $this->derivatives['message_private.messages']['title'] = 'Messages';
    // @todo: this should pass a user / uid argument also.
    $this->derivatives['message_private.messages']['route_name'] = 'message_private.user.add';
    return $this->derivatives;
  }

}

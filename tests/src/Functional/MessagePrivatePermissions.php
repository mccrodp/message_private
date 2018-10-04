<?php

namespace Drupal\Tests\message_private\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\message\Functional\MessageTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\message\Entity\Message;

/**
 * Testing the private message access use case.
 *
 * @group Message Private
 */
class MessagePrivatePermissions extends MessageTestBase {

  /**
   * The message access control handler.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected $accessHandler;

  /**
   * The user account object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The user role.
   *
   * @var string
   */
  protected $rid;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'message',
    'message_notify',
    'message_ui',
    'message_private',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->accessHandler = \Drupal::entityManager()->getAccessControlHandler('message');

    $this->account = $this->drupalCreateUser();

    // Load 'authenticated' user role.
    $this->rid = Role::load(RoleInterface::AUTHENTICATED_ID)->id();
  }

  /**
   * Test private message workflow.
   */
  public function testMessageUiPermissions() {
    // User login.
    $this->drupalLogin($this->account);
    // Set our create url.
    $create_url = '/message/add/private_message';

    // Verify the user can't create the message.
    $this->drupalGet($create_url);
    // The user can't create a private message.
    $this->assertResponse(403);

    // Grant and check create permissions for a message.
    $this->grantMessagePrivatePermission('create');
    $this->drupalGet($create_url);

    // If we get a valid response.
    // Check for valid response.
    $this->assertResponse(200);

    // Create a message at current page / url.
    $this->drupalPostForm(NULL, [], t('Save'));

    // Create the message url.
    $msg_url = '/message/1';

    // Verify the user now can see the text.
    $this->grantMessagePrivatePermission('view');
    $this->drupalGet($msg_url);

    // The user can view a private message.
    $this->assertResponse(200);

    // Verify can't edit the message.
    $this->drupalGet($msg_url . '/edit');

    // The user can't edit a private message.
    $this->assertResponse(403);

    // Grant permission to the user.
    $this->grantMessagePrivatePermission('edit');
    $this->drupalGet($msg_url . '/edit');

    // The user can't edit a private message.
    $this->assertResponse(200);

    // Verify the user can't delete the message.
    $this->drupalGet($msg_url . '/delete');

    // The user can't delete the private message.
    $this->assertResponse(403);

    // Grant the permission to the user.
    $this->grantMessagePrivatePermission('delete');
    $this->drupalPostForm($msg_url . '/delete', [], t('Delete'));

    // The user can delete a private message.
    $this->assertResponse(200);

    // Set up admin url.
    $admin_url = '/admin/config/system/message-private';
    // User has no permission for the admin page - verify access denied.
    $this->drupalGet($admin_url);

    // The user cannot administer message_private.
    $this->assertResponse(403);

    user_role_grant_permissions($this->rid, ['administer message private']);
    $this->drupalGet($admin_url);

    // The user can administer message_private.
    $this->assertResponse(200);

    // Create a user with the bypass access permission and verify the bypass.
    $this->drupalLogout();
    $user = $this->drupalCreateUser(['bypass private message access control']);

    // Verify the user can by pass the message access control.
    $this->drupalLogin($user);
    $this->drupalGet($create_url);

    // The user can bypass the private message access control.
    $this->assertResponse(200);
  }

  /**
   * Grant to the user a specific permission.
   *
   * @param string $operation
   *   The type of operation - create, update, delete or view.
   */
  private function grantMessagePrivatePermission($operation) {
    user_role_grant_permissions($this->rid, [$operation . ' private_message message']);
  }

  /**
   * Checking the message_private_message_access hook.
   */
  public function testMessagePrivateAccessHook() {
    $this->drupalLogin($this->account);

    // Setting up the operation and the expected value from the access callback.
    $permissions = [
      'create' => TRUE,
      'view' => TRUE,
      'delete' => FALSE,
      'update' => FALSE,
    ];

    // Get the message template and create an instance.
    $message_template = $this->loadMessageTemplate('private_message');
    /* @var $message Message */
    $message = Message::create(['template' => $message_template->id()]);
    $message->setOwner($this->account);
    $message->save();

    foreach ($permissions as $op => $value) {
      // When the hook access of the dummy module will get in action it will
      // check which value need to return. If the access control function will
      // return the expected value then we know the hook got in action.
      $message->{$op} = $value;
      $params = [
        '@operation' => $op,
        '@value' => $value,
      ];

      $this->assertEqual($value, $this->accessHandler->access($message, $op, $this->account), new FormattableMarkup('The hook return @value for @operation', $params));
    }
  }

}

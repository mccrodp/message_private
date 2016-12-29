**D8 Status : Under Development (non functional)**

CONTENTS OF THIS FILE
---------------------
 * Introduction
 * Dependencies
 * Installation
 * Configuration
 * How to use
 * Security
 * Development road-map
 * Maintainers


INTRODUCTION
------------
A message template and entity reference fields, enabling sending and receiving 
private messages using The Message Stack. Messages of template "Private Message" can 
be sent by creating the private_message message instance with fields referencing 
user entities.

The message_private module includes the following.
+ A message template "Private Message" with entity reference field referencing users
+ A message view, message_private for "User Messages"


DEPENDENCIES
------------
The message_private module requires the following modules:
 * Message (https://drupal.org/project/message)
 * Message Notify (https://drupal.org/project/message_notify)
 * Message UI (https://drupal.org/project/message_ui)


INSTALLATION
------------
 * Install as you would normally install a contributed drupal module. See:
   https://drupal.org/documentation/install/modules-themes/modules-8
   for further information.


CONFIGURATION
-------------
Show notification boolean on user form fields: /admin/config/people/accounts/form-display
This will be addressed automatically in a hook later.


HOW TO USE
----------
To Create messages:
 * Visit /message/add/private_message and Create the message to
 send, or
 * Visit the "Messages" tab detailed below and find the "Create a new message" 
local action.

To View inbox and sent messages:
 * Visit your user page at /user and find the "Messages" tab which displays 
received messages (Inbox local task), the "Sent" local task under that tab which
displays sent messages and the "Group" local task which displays group messages.
   * /user/USER_ID/messages/inbox
   * /user/USER_ID/messages/sent


SECURITY
--------
This module does not come with any security features out-of-the-box, but you can
easily configure your own, using methods and modules of your choice.

E.G:
 * Honeypot and timestamp methods: https://www.drupal.org/project/honeypot
 * CAPTCHA method: https://www.drupal.org/project/recaptcha


DEVELOPMENT ROAD-MAP
--------------------
 * Integrate with Message FOS (FOSMessage) bridge module.
 * Flag module on user entity to block/unblock users from messaging them
 * Flag module on message entity to show/hide (delete) messages from users own 
   display
 * Allow Operations links to display correctly on views, i.e. - show 'View' for
   users with view permissions. Not showing currently due to custom permissions.


MAINTAINERS
-----------
Current maintainers:
 * Paul McCrodden - https://www.drupal.org/user/678774

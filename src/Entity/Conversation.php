<?php

/**
 * @file
 * Contains Drupal\message_private\Entity\Conversation.
 */

namespace Drupal\message_private\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use FOS\Message\Model\ConversationInterface;
use FOS\Message\Model\ConversationPersonInterface;
use FOS\Message\Model\PersonInterface;
use Webmozart\Assert\Assert;

/**
 * A conversation is an ordered group of messages with a subject.
 *
 */
class Conversation extends ContentEntityBase implements ConversationInterface
{
  /**
   * @var int
   */
  protected $id;
  /**
   * @var string
   */
  protected $subject;
  /**
   * @var MessageInterface[]|\Doctrine\Common\Collections\Collection
   */
  protected $messages;
  /**
   * @var ConversationPersonInterface[]|\Doctrine\Common\Collections\Collection
   */
  protected $persons;

  use EntityChangedTrait;

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->persons = new ArrayCollection();
    $this->messages = new ArrayCollection();
  }
  /**
   * {@inheritdoc}
   */
  public function getId()
  {
    return $this->id;
  }
  /**
   * {@inheritdoc}
   */
  public function getSubject()
  {
    return $this->subject;
  }
  /**
   * {@inheritdoc}
   */
  public function setSubject($subject)
  {
    Assert::nullOrString($subject);
    $this->subject = $subject;
  }
  /**
   * {@inheritdoc}
   */
  public function getMessages()
  {
    return $this->messages;
  }
  /**
   * {@inheritdoc}
   */
  public function getFirstUnreadMessage(PersonInterface $person)
  {
    foreach ($this->messages as $message) {
      if ($message->getReadDate($person) === null) {
        return $message;
      }
    }
    return null;
  }
  /**
   * {@inheritdoc}
   */
  public function addConversationPerson(ConversationPersonInterface $conversationPerson)
  {
    if (!$this->isPersonInConversation($conversationPerson->getPerson())) {
      $this->persons->add($conversationPerson);
    }
  }
  /**
   * {@inheritdoc}
   */
  public function removeConversationPerson(ConversationPersonInterface $conversationPerson)
  {
    $this->persons->removeElement($conversationPerson);
  }
  /**
   * {@inheritdoc}
   */
  public function getConversationPersons()
  {
    return $this->persons;
  }
  /**
   * {@inheritdoc}
   */
  public function isPersonInConversation(PersonInterface $person)
  {
    return $this->getConversationPerson($person) instanceof ConversationPersonInterface;
  }
  /**
   * {@inheritdoc}
   */
  public function getConversationPerson(PersonInterface $person)
  {
    foreach ($this->persons as $conversationPerson) {
      if ($conversationPerson->getPerson()->getId() === $person->getId()) {
        return $conversationPerson;
      }
    }
    return null;
  }
}

<?php

namespace Intercom;

use Exception;
use Psr\Log\LoggerInterface;
use VoximplantKitIM\Model\MessagingEventMessageType;
use VoximplantKitIM\Model\MessagingIncomingEventType;
use VoximplantKitIM\Model\MessagingIncomingEventTypeClientData;
use VoximplantKitIM\Model\MessagingIncomingEventTypeEventData;
use Ramsey\Uuid\Uuid;
use VoximplantKitIM\Model\MessagingOutgoingChatCloseEventType;
use VoximplantKitIM\Model\MessagingOutgoingNewMessageEventType;
use VoximplantKitIM\ObjectSerializer;
use VoximplantKitIM\VoximplantKitIMClient;

class Service
{
    /** @var VoximplantKitIMClient */
    private $kit;

    /** @var Repository */
    private $repository;

    /** @var string */
    private $channelUUID;

    /** @var IntercomClient */
    private $intercom;

    /**
     * @var int
     */
    private $intercomAdminId;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(IntercomClient $intercom, Repository $repository, VoximplantKitIMClient $kit, LoggerInterface $logger, string $channelUUID, int $intercomAdminId)
    {
        $this->kit = $kit;
        $this->repository = $repository;
        $this->intercom = $intercom;
        $this->logger = $logger;
        $this->channelUUID = $channelUUID;
        $this->intercomAdminId = $intercomAdminId;
    }

    public function login()
    {
        $jwt = $this->kit->botservice->login($this->channelUUID);

        if (!$jwt->getSuccess()) {
            throw new Exception(json_encode($jwt->getResult()));
        }

        $this->kit->getConfig()->setAccessToken($jwt->getResult()->getAccessToken());
    }

    public function handleIntercomEvent(string $event)
    {
        $incoming = json_decode($event);
        if (
            $incoming->topic !== 'conversation.user.replied' &&
            $incoming->topic !== 'conversation.user.created') {
            return;
        }

        if (count($incoming->data->item->conversation_parts->conversation_parts) > 0) {
            foreach ($incoming->data->item->conversation_parts->conversation_parts as $part) {
                $this->handleIntercomMessage($incoming, $part);
            }
            return;
        }

        if (isset($incoming->data->item->conversation_message->id)) {
            $this->handleIntercomMessage($incoming, $incoming->data->item->conversation_message);
        }
    }

    public function handleIntercomMessage($incoming, $part)
    {
        if ($part->author->type !== 'user') {
            return;
        }

        $this->repository->saveClientConversation($part->author->id, $incoming->data->item);

        $event = new MessagingIncomingEventType();

        $client = new MessagingIncomingEventTypeClientData();
        $client->setClientId($incoming->data->item->user->id);
        $client->setClientEmail($incoming->data->item->user->email);
        $client->setClientDisplayName($incoming->data->item->user->name);

        $event->setClientData($client);
        $event->setEventId(Uuid::uuid4()->toString());
        $event->setEventType(MessagingIncomingEventType::EVENT_TYPE_MESSAGE);

        $message = new MessagingEventMessageType();
        $message->setMessageId($part->id);
        $message->setText(strip_tags($part->body));

        $eventData = (new MessagingIncomingEventTypeEventData())->setMessage($message);
        $event->setEventData($eventData);

        $resp = $this->kit->botservice->sendEvent($event, $this->channelUUID);

        if (!$resp->getSuccess()) {
            throw new Exception(json_encode($resp->getResult()));
        }
    }

    public function handleKitEvent(string $event)
    {
        $eventObj = json_decode($event);
        if ($eventObj->event_type == 'send_message') {
            $kitEvent =  ObjectSerializer::deserialize($eventObj, MessagingOutgoingNewMessageEventType::class);
            $convData = $this->repository->getClientConversation($kitEvent->getClientData()->getClientId());

            if ($convData->assignee->id == null) {
                // Admin must have permission for assign conversation
                try {
                    $this->intercom->assignConversation($convData->id, $this->intercomAdminId);
                } catch (Exception $exception) {
                    $this->logger->warning('Failed assign conversation with admin', [
                        'code'=>$exception->getCode(),
                        'error'=>$exception->getMessage(),
                    ]);
                }
            }

            $this->intercom->replyToContact($convData->id, $this->intercomAdminId, $kitEvent->getEventData()->getMessage()->getText());
        } elseif ($eventObj->event_type == 'close_conversation') {
            $kitCloseEvent =  ObjectSerializer::deserialize(json_decode($event), MessagingOutgoingChatCloseEventType::class);
            $convData = $this->repository->getClientConversation($kitCloseEvent->getClientData()->getClientId());
            $this->intercom->closeConversation($convData->id, $this->intercomAdminId);
        }
    }
}
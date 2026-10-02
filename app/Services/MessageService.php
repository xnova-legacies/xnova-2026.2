<?php

namespace App\Services;

use App\Entities\Message;
use App\Repositories\MessageRepository;
use App\Repositories\UserRepository;

final class MessageService
{
    // types de messages legacy
    public const TYPE_PLAYER = 1;
    public const TYPE_ALLIANCE = 2;
    public const TYPE_ADMIN = 3;
    public const TYPE_SPY = 0;
    public const TYPE_ATTACK = 5;

    public function __construct(
        private readonly MessageRepository $messages = new MessageRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function send(int $ownerId, int $senderId, int $type, string $from, string $subject, string $text): Message
    {
        $message = Message::new($ownerId, $senderId, $type, $from, $subject, $text);
        $this->messages->insertMessage($message);

        $this->users->incrementUnread($ownerId, $type);

        return $message;
    }

    public function sendSimple(int $ownerId, string $from, string $subject, string $text, int $type = self::TYPE_ADMIN): void
    {
        $this->send($ownerId, 0, $type, $from, $subject, $text);
    }

    public function sendToAlliance(int $allyId, int $senderId, int $senderAllyRank, string $from, string $subject, string $text, ?int $rankFilter = null): array
    {
        $recipients = $this->messages->findAllyRecipients($allyId, $rankFilter);
        $sent = [];

        foreach ($recipients as $recipient) {
            $this->send((int) $recipient['id'], $senderId, self::TYPE_ALLIANCE, $from, $subject, $text);
            $sent[] = (string) $recipient['username'];
        }

        return $sent;
    }

    public function sendAdminSimple(int $ownerId, string $from, string $subject, string $text): void
    {
        $this->sendSimple($ownerId, $from, $subject, $text, self::TYPE_ADMIN);
    }
}

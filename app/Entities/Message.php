<?php

namespace App\Entities;

final class Message extends AbstractEntity
{
    public static function new(int $ownerId, int $senderId, int $type, string $from, string $subject, string $text): self
    {
        return new self(array(
            'message_owner' => $ownerId,
            'message_sender' => $senderId,
            'message_time' => time(),
            'message_type' => $type,
            'message_from' => $from,
            'message_subject' => $subject,
            'message_text' => $text,
        ));
    }

    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->raw('message_id');
    }

    public function ownerId(): int
    {
        return (int) $this->raw('message_owner');
    }

    public function senderId(): int
    {
        return (int) $this->raw('message_sender');
    }

    public function type(): int
    {
        return (int) $this->raw('message_type');
    }

    public function from(): string
    {
        return (string) ($this->raw('message_from') ?? '');
    }

    public function subject(): string
    {
        return (string) ($this->raw('message_subject') ?? '');
    }

    public function text(): string
    {
        return (string) ($this->raw('message_text') ?? '');
    }

    public function time(): int
    {
        return (int) $this->raw('message_time');
    }
}

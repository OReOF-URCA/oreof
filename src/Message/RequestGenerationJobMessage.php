<?php

namespace App\Message;

class RequestGenerationJobMessage
{
    public function __construct(
        private int    $userId,
        private string $type,
        private array  $parameters = []
    )
    {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}

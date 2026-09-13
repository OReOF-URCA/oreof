<?php

declare(strict_types=1);

namespace App\DTO;

use JsonSerializable;

final readonly class ResolvedNotificationPreference implements JsonSerializable
{
    /**
     * @param array{email: bool, inapp: bool} $channels
     */
    public function __construct(
        public array  $channels,
        public string $source,
        public bool   $email,
        public bool   $inapp,
    ) {
    }

    public function getChannels(): array
    {
        return $this->channels;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function isEmail(): bool
    {
        return $this->email;
    }

    public function isInApp(): bool
    {
        return $this->inapp;
    }

    public function channelAllowed(string $channel): bool
    {
        return (bool)($this->channels[$channel] ?? false);
    }

    public function jsonSerialize(): array
    {
        return [
            'channels' => $this->channels,
            'source' => $this->source,
            'email' => $this->email,
            'inapp' => $this->inapp,
        ];
    }
}

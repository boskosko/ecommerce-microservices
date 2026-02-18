<?php

namespace App\Events;

use App\Models\User;

readonly class UserLoggedInEvent
{
    public function __construct(
        public User   $user,
        public string $ipAddress,
        public string $userAgent,
        public string $timestamp
    ) {}

    /**
     * Convert event to array for publishing
     */
    public function toArray(): array
    {
        return [
            'event' => 'user.logged_in',
            'data' => [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'name' => $this->user->name,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'logged_in_at' => $this->timestamp,
            ],
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * Convert event to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}

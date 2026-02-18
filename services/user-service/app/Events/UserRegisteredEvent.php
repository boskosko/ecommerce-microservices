<?php

namespace App\Events;

use App\Models\User;

readonly class UserRegisteredEvent
{
    public function __construct(
        public User   $user,
        public string $timestamp
    ) {}

    /**
     * Convert event to array for publishing
     */
    public function toArray(): array
    {
        return [
            'event' => 'user.registered',
            'data' => [
                'user_id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'role' => $this->user->role,
                'registered_at' => $this->timestamp,
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

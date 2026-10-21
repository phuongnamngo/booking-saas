<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AppointmentActivity extends Notification
{
    public function __construct(public string $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{message: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message];
    }
}

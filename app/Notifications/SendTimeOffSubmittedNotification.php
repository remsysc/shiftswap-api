<?php

namespace App\Notifications;

use App\Models\TimeOffRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendTimeOffSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TimeOffRequest $timeOff) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Time-Off Request Submitted')
            ->line("Staff member {$this->timeOff->user->name} has requested time off.")
            ->line("Dates: {$this->timeOff->start_date->toDateString()} to {$this->timeOff->end_date->toDateString()}")
            ->line("Reason: {$this->timeOff->reason}")
            ->line('Please review and take action.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'time_off_submitted',
            'time_off_id' => $this->timeOff->id,
            'user_id' => $this->timeOff->user_id,
            'start_date' => $this->timeOff->start_date->toDateString(),
            'end_date' => $this->timeOff->end_date->toDateString(),
            'reason' => $this->timeOff->reason,
            'message' => "{$this->timeOff->user->name} submitted a time-off request.",
        ];
    }
}

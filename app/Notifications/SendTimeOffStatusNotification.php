<?php

namespace App\Notifications;

use App\Models\TimeOffRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendTimeOffStatusNotification extends Notification implements ShouldQueue
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
        $statusCapitalized = ucfirst($this->timeOff->status);

        $mail = (new MailMessage)
            ->subject("Time-Off Request {$statusCapitalized}")
            ->line("Your time-off request for {$this->timeOff->start_date->toDateString()} to {$this->timeOff->end_date->toDateString()} was {$this->timeOff->status}.");

        if ($this->timeOff->manager_notes !== null) {
            $mail->line("Manager notes: {$this->timeOff->manager_notes}");
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'time_off_status',
            'time_off_id' => $this->timeOff->id,
            'status' => $this->timeOff->status,
            'start_date' => $this->timeOff->start_date->toDateString(),
            'end_date' => $this->timeOff->end_date->toDateString(),
            'manager_notes' => $this->timeOff->manager_notes,
            'message' => "Your time-off request was {$this->timeOff->status}.",
        ];
    }
}

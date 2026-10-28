<?php

namespace App\Notifications;

use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendShiftAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Shift $shift) {}

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
            ->subject('New Shift Assigned')
            ->line('You have been assigned to a new shift.')
            ->line("Start: {$this->shift->start_at->toIso8601String()}")
            ->line("End: {$this->shift->end_at->toIso8601String()}")
            ->line('Thank you for using ShiftSwap!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shift_assigned',
            'shift_id' => $this->shift->id,
            'start_at' => $this->shift->start_at->toIso8601String(),
            'end_at' => $this->shift->end_at->toIso8601String(),
            'role_label' => $this->shift->role_label,
            'message' => 'You have been assigned to a new shift.',
        ];
    }
}

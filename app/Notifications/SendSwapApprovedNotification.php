<?php

namespace App\Notifications;

use App\Models\ShiftSwap;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendSwapApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ShiftSwap $swap) {}

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
            ->subject('Shift Swap Approved')
            ->line('The shift swap has been approved by management.')
            ->line("Shift start: {$this->swap->shift->start_at->toIso8601String()}")
            ->line("Shift end: {$this->swap->shift->end_at->toIso8601String()}")
            ->line("Manager notes: {$this->swap->manager_notes}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swap_approved',
            'swap_id' => $this->swap->id,
            'shift_id' => $this->swap->shift_id,
            'manager_notes' => $this->swap->manager_notes,
            'message' => 'The shift swap has been approved.',
        ];
    }
}

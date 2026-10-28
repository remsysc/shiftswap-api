<?php

namespace App\Notifications;

use App\Models\ShiftSwap;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendSwapRequestedNotification extends Notification implements ShouldQueue
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
            ->subject('Shift Swap Pending Approval')
            ->line('A staff member has requested to take an offered shift swap.')
            ->line("Offered by: {$this->swap->offerer->name}")
            ->line("Requested by: {$this->swap->requester?->name}")
            ->line('Please review and approve or reject this request.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swap_requested',
            'swap_id' => $this->swap->id,
            'shift_id' => $this->swap->shift_id,
            'offered_by' => $this->swap->offered_by,
            'requested_by' => $this->swap->requested_by,
            'message' => 'A shift swap request requires your approval.',
        ];
    }
}

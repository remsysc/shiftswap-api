<?php

namespace App\Notifications;

use App\Models\ShiftSwap;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendSwapOfferedNotification extends Notification implements ShouldQueue
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
            ->subject('Shift Swap Available')
            ->line('A coworker has offered a shift that you are eligible to take.')
            ->line("Start: {$this->swap->shift->start_at->toIso8601String()}")
            ->line("End: {$this->swap->shift->end_at->toIso8601String()}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swap_offered',
            'swap_id' => $this->swap->id,
            'shift_id' => $this->swap->shift_id,
            'offered_by' => $this->swap->offered_by,
            'start_at' => $this->swap->shift->start_at->toIso8601String(),
            'end_at' => $this->swap->shift->end_at->toIso8601String(),
            'message' => 'A shift swap offer is available for you to claim.',
        ];
    }
}

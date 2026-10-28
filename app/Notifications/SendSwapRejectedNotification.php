<?php

namespace App\Notifications;

use App\Models\ShiftSwap;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendSwapRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ShiftSwap $swap,
        public ?string $managerNote = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Shift Swap Request Rejected')
            ->line('Your request to take a shift swap was not approved.')
            ->line("Shift start: {$this->swap->shift->start_at->toIso8601String()}")
            ->line("Shift end: {$this->swap->shift->end_at->toIso8601String()}");

        if ($this->managerNote !== null) {
            $mail->line("Manager note: {$this->managerNote}");
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swap_rejected',
            'swap_id' => $this->swap->id,
            'shift_id' => $this->swap->shift_id,
            'manager_notes' => $this->managerNote,
            'message' => 'Your shift swap request was rejected.',
        ];
    }
}

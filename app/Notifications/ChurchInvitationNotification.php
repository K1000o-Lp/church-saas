<?php

namespace App\Notifications;

use App\Models\ChurchInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChurchInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ChurchInvitation $invitation,
        public readonly string $plainToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $church = $this->invitation->church;
        $url = route('invitations.show', ['token' => $this->plainToken]);

        return (new MailMessage)
            ->subject('Invitación a '.$church->name)
            ->greeting('Hola')
            ->line('Te invitaron a unirte a '.$church->name.' en '.config('app.name').'.')
            ->line('Tu rol será: '.$this->invitation->role.'.')
            ->action('Aceptar invitación', $url)
            ->line('La invitación vence el '.$this->invitation->expiresAtLabel().'.')
            ->line('Si no esperabas esta invitación, puedes ignorar este mensaje.');
    }
}

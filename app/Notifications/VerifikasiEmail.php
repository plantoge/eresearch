<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifikasiEmail extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // URL signed — INI yang memicu update email_verified_at saat diklik.
        // Jangan bikin URL manual; harus lewat temporarySignedRoute biar valid.
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject('Verifikasi Akun eProposal RSPI')
            ->markdown('emails.verifikasi', [
                'url' => $url,
                'user' => $notifiable,
            ]);
    }
}

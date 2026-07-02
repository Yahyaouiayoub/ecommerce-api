<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $frontendVerifyUrl = "{$frontendUrl}/verify-email?url=" . urlencode($verificationUrl);

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting('Welcome!')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $frontendVerifyUrl)
            ->line('If you did not create an account, no further action is required.');
    }
}

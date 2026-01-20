<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends ResetPasswordNotification
{
    protected $url;

    public function __construct($token, $url)
    {
        parent::__construct($token);
        $this->url = $url;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reset Your Password')
            ->line('Click the button below to reset your password.')
            ->action('Reset Password', $this->url)
            ->line('If you did not request a password reset, no further action is required.');
    }
}

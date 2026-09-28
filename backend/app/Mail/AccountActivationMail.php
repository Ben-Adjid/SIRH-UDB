<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $activationToken,
        public string $frontendUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activez votre compte SIRH UDB',
        );
    }

    public function content(): Content
    {
        $activationLink = $this->frontendUrl . '/activer-compte?token=' . $this->activationToken . '&email=' . urlencode($this->user->email);

        return new Content(
            view: 'emails.account-activation',
            with: [
                'user' => $this->user,
                'activationLink' => $activationLink,
            ],
        );
    }
}

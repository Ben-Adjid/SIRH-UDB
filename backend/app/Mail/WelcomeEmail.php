<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $tempPassword = null,
    ) {}

    public function build(): Mailable
    {
        return $this->subject('Bienvenue sur SIRH Dakar — Votre compte est prêt')
                    ->view('emails.auth.welcome');
    }
}

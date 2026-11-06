<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitoDashboard extends Mailable
{
    use Queueable;

    public function __construct(public string $link, public string $ruolo, public string $scadenza) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invito alla Dashboard OEE e CCP - Antichi Sapori dell\'Etna');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.invito');
    }
}

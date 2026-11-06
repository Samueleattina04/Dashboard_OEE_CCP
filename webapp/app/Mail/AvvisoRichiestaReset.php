<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AvvisoRichiestaReset extends Mailable
{
    use Queueable;

    public function __construct(public string $email, public string $linkUtenti) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Richiesta di reimpostazione password - Dashboard OEE');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.avviso_reset');
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LinkResetPassword extends Mailable
{
    use Queueable;

    public function __construct(public string $link, public string $scadenza) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reimpostazione password - Dashboard OEE e CCP');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.reset');
    }
}

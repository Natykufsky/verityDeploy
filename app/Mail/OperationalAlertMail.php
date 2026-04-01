<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OperationalAlertMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) data_get($this->payload, 'title', 'verityDeploy operational alert'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.operational-alert',
            with: [
                'payload' => $this->payload,
            ],
        );
    }
}

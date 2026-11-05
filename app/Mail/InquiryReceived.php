<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Customer confirmation. Name and message are only ever rendered through escaped Blade output. */
class InquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Inquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We received your inquiry');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.inquiry-received', with: [
            // Letters, digits, spaces and basic name punctuation only: nothing Markdown-structural.
            'name' => trim((string) preg_replace('/[^\p{L}\p{N} \'.-]/u', '', $this->inquiry->name)) ?: 'there',
            'propertyTitle' => $this->inquiry->property?->title,
            'agency' => 'Skyline Realty',
        ]);
    }
}

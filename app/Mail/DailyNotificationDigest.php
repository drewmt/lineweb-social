<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyNotificationDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{comment_reply: int, content_mention: int, space_moderation: int}  $counts
     */
    public function __construct(
        public readonly array $counts,
        public readonly int $total,
        public readonly bool $hasMore,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your daily '.config('app.name').' digest',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notifications.daily-digest',
        );
    }
}

<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Đánh giá lịch hẹn',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.review-request',
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $restaurantName,
        public readonly string $date,
        public readonly string $timeslot,
        public readonly int $partySize,
        public readonly string $shortLink
    ) {}

    public function build()
    {
        return $this->subject('訂位確認')->view('emails.reservation_created', [
            'restaurantName' => $this->restaurantName,
            'date'           => $this->date,
            'timeslot'       => $this->timeslot,
            'partySize'      => $this->partySize,
            'shortLink'      => $this->shortLink,
        ]);
    }
}


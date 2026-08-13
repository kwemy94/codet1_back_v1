<?php

namespace App\Mail;

use App\Models\CampagneCourriel;
use App\Models\Membre;
use App\Services\EnteteDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourrielComite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CampagneCourriel $campagne,
        public Membre $membre,
    ) {}

    public function envelope(): Envelope
    {
        $mentions = app(EnteteDocumentService::class)->mentions();

        return new Envelope(
            subject: $this->campagne->objet,
            // Le membre répond au secrétariat, pas à l'adresse technique d'envoi.
            replyTo: array_values(array_filter([$mentions['email'] ?: null])),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.courriel-comite',
            with: [
                'mentions' => app(EnteteDocumentService::class)->mentions(),
                'membre'   => $this->membre,
                'objet'    => $this->campagne->objet,
                'contenu'  => $this->campagne->contenu,
            ],
        );
    }
}

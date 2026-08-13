<?php

namespace App\Jobs;

use App\Mail\CourrielComite;
use App\Models\DestinataireCourriel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoi d'un courriel à un destinataire.
 *
 * Un envoi par destinataire, mis en file : un envoi collectif à trois cents
 * membres ne doit pas bloquer la requête du secrétaire, et l'échec d'une
 * adresse ne doit pas interrompre les suivantes.
 */
class EnvoyerCourrielMembre implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(public DestinataireCourriel $destinataire) {}

    public function handle(): void
    {
        $destinataire = $this->destinataire->fresh(['campagne', 'membre']);

        if (! $destinataire || $destinataire->statut === 'envoye') {
            return;
        }

        Mail::to($destinataire->adresse)
            ->send(new CourrielComite($destinataire->campagne, $destinataire->membre));

        $destinataire->update(['statut' => 'envoye', 'date_traitement' => now()]);
        $destinataire->campagne->actualiserStatut();
    }

    /** Après les tentatives, l'échec est consigné sur le destinataire concerné. */
    public function failed(Throwable $erreur): void
    {
        Log::warning('Envoi de courriel en échec', [
            'destinataire' => $this->destinataire->id,
            'adresse'      => $this->destinataire->adresse,
            'erreur'       => $erreur->getMessage(),
        ]);

        $this->destinataire->update([
            'statut'          => 'echoue',
            'date_traitement' => now(),
            'message_erreur'  => mb_substr($erreur->getMessage(), 0, 500),
        ]);

        $this->destinataire->campagne?->actualiserStatut();
    }
}

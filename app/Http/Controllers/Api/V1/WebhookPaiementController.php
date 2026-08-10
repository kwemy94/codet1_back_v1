<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Paiement;
use App\Services\PaiementService;
use App\Services\Paiement\PasserelleFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Points d'entrée appelés par les opérateurs mobile money.
 * Ces routes sont publiques (hors authentification Sanctum) mais protégées
 * par la vérification de signature propre à chaque passerelle.
 */
class WebhookPaiementController extends Controller
{
    public function __construct(
        private PasserelleFactory $passerelles,
        private PaiementService $paiements,
    ) {}

    public function orangeMoney(Request $requete)
    {
        return $this->traiter($requete, 'orange_money');
    }

    public function mtnMomo(Request $requete)
    {
        return $this->traiter($requete, 'mtn_momo');
    }

    private function traiter(Request $requete, string $cle)
    {
        $passerelle = $this->passerelles->parCle($cle);
        $payload    = $requete->all();

        if (! $passerelle->verifierSignature($payload, $requete->headers->all())) {
            Log::warning("Webhook {$cle} : signature invalide", ['ip' => $requete->ip()]);

            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $resultat = $passerelle->interpreterWebhook($payload);
        $paiement = Paiement::where('reference', $resultat->referenceInterne)->first();

        if (! $paiement) {
            Log::warning("Webhook {$cle} : paiement introuvable", ['reference' => $resultat->referenceInterne]);

            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        $paiement->transaction?->update([
            'statut'              => $resultat->statut,
            'reference_operateur' => $resultat->referenceOperateur ?? $paiement->transaction->reference_operateur,
            'message_retour'      => $resultat->message,
            'payload_retour'      => $resultat->payload,
            'date_confirmation'   => now(),
        ]);

        $resultat->estConfirme()
            ? $this->paiements->valider($paiement)
            : $this->paiements->echouer($paiement, $resultat->message);

        return response()->json(['message' => 'Notification traitée.']);
    }
}

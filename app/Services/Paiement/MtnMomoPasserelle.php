<?php

namespace App\Services\Paiement;

use App\Models\Paiement;
use App\Models\TransactionMobile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Intégration MTN Mobile Money (API Collection).
 * Voir la remarque de OrangeMoneyPasserelle : à aligner sur le contrat marchand.
 */
class MtnMomoPasserelle implements Passerelle
{
    public function initier(Paiement $paiement, string $numeroTelephone): void
    {
        $referenceExterne = (string) Str::uuid();

        $transaction = TransactionMobile::create([
            'paiement_id'         => $paiement->id,
            'operateur'           => 'MTN_MOMO',
            'reference_operateur' => $referenceExterne,
            'numero_telephone'    => $numeroTelephone,
            'date_initiation'     => now(),
            'statut'              => 'en_attente',
        ]);

        $reponse = Http::withToken($this->jeton())
            ->withHeaders([
                'X-Reference-Id'         => $referenceExterne,
                'X-Target-Environment'   => config('paiement.mtn_momo.environnement'),
                'Ocp-Apim-Subscription-Key' => config('paiement.mtn_momo.cle_abonnement'),
                'X-Callback-Url'         => route('api.v1.webhooks.mtn-momo'),
            ])
            ->timeout(30)
            ->post(config('paiement.mtn_momo.url_paiement'), [
                'amount'       => (string) $paiement->montant,
                'currency'     => 'XAF',
                'externalId'   => $paiement->reference,
                'payer'        => ['partyIdType' => 'MSISDN', 'partyId' => $numeroTelephone],
                'payerMessage' => 'Cotisation CODET I',
                'payeeNote'    => $paiement->reference,
            ]);

        if ($reponse->failed()) {
            Log::error('MTN MoMo : échec de l\'initiation', [
                'paiement' => $paiement->reference,
                'reponse'  => $reponse->body(),
            ]);

            $transaction->update(['statut' => 'echouee', 'message_retour' => $reponse->body()]);

            throw new RuntimeException("L'initiation du paiement MTN Mobile Money a échoué.");
        }
    }

    public function interpreterWebhook(array $payload): ResultatWebhook
    {
        $statut = match (strtoupper($payload['status'] ?? '')) {
            'SUCCESSFUL' => 'confirmee',
            'TIMEOUT'    => 'expiree',
            default      => 'echouee',
        };

        return new ResultatWebhook(
            referenceInterne:   $payload['externalId'] ?? '',
            statut:             $statut,
            referenceOperateur: $payload['financialTransactionId'] ?? null,
            message:            $payload['reason'] ?? null,
            payload:            $payload,
        );
    }

    public function verifierSignature(array $payload, array $entetes): bool
    {
        // MTN ne signe pas ses rappels : on restreint par liste blanche d'adresses IP
        $autorisees = (array) config('paiement.mtn_momo.ips_autorisees', []);

        return $autorisees === [] || in_array(request()->ip(), $autorisees, true);
    }

    private function jeton(): string
    {
        return cache()->remember('mtn_momo.jeton', 3000, function () {
            $reponse = Http::withBasicAuth(
                config('paiement.mtn_momo.utilisateur_api'),
                config('paiement.mtn_momo.cle_api')
            )
                ->withHeaders(['Ocp-Apim-Subscription-Key' => config('paiement.mtn_momo.cle_abonnement')])
                ->post(config('paiement.mtn_momo.url_jeton'));

            return (string) $reponse->json('access_token');
        });
    }
}

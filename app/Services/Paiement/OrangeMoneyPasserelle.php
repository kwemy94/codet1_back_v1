<?php

namespace App\Services\Paiement;

use App\Models\Paiement;
use App\Models\TransactionMobile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Intégration Orange Money.
 *
 * ATTENTION — les points d'entrée, le format des charges utiles et le mécanisme
 * de signature doivent être alignés sur la documentation du contrat marchand
 * effectivement signé avec Orange Cameroun. Le squelette ci-dessous isole tout
 * ce qui est spécifique à l'opérateur derrière l'interface Passerelle.
 */
class OrangeMoneyPasserelle implements Passerelle
{
    public function initier(Paiement $paiement, string $numeroTelephone): void
    {
        $transaction = TransactionMobile::create([
            'paiement_id'      => $paiement->id,
            'operateur'        => 'ORANGE_MONEY',
            'numero_telephone' => $numeroTelephone,
            'date_initiation'  => now(),
            'statut'           => 'en_attente',
        ]);

        $reponse = Http::withToken($this->jeton())
            ->timeout(30)
            ->post(config('paiement.orange_money.url_paiement'), [
                'merchant_key' => config('paiement.orange_money.cle_marchand'),
                'currency'     => 'XAF',
                'order_id'     => $paiement->reference,
                'amount'       => (int) $paiement->montant,
                'subscriber'   => ['msisdn' => $numeroTelephone],
                'notif_url'    => route('api.v1.webhooks.orange-money'),
                'return_url'   => config('paiement.url_retour'),
            ]);

        if ($reponse->failed()) {
            Log::error('Orange Money : échec de l\'initiation', [
                'paiement' => $paiement->reference,
                'reponse'  => $reponse->body(),
            ]);

            $transaction->update(['statut' => 'echouee', 'message_retour' => $reponse->body()]);

            throw new RuntimeException("L'initiation du paiement Orange Money a échoué.");
        }

        $transaction->update([
            'reference_operateur' => $reponse->json('pay_token') ?? $reponse->json('transaction_id'),
            'payload_retour'      => $reponse->json(),
        ]);
    }

    public function interpreterWebhook(array $payload): ResultatWebhook
    {
        $statut = match (strtolower($payload['status'] ?? '')) {
            'success', 'successful' => 'confirmee',
            'expired'               => 'expiree',
            default                 => 'echouee',
        };

        return new ResultatWebhook(
            referenceInterne:    $payload['order_id'] ?? '',
            statut:              $statut,
            referenceOperateur:  $payload['txnid'] ?? ($payload['transaction_id'] ?? null),
            message:             $payload['message'] ?? null,
            payload:             $payload,
        );
    }

    public function verifierSignature(array $payload, array $entetes): bool
    {
        $secret  = (string) config('paiement.orange_money.secret_webhook');
        $recue   = $entetes['x-signature'][0] ?? '';
        $calculee = hash_hmac('sha256', json_encode($payload), $secret);

        return $secret !== '' && hash_equals($calculee, $recue);
    }

    private function jeton(): string
    {
        return cache()->remember('orange_money.jeton', 3000, function () {
            $reponse = Http::asForm()
                ->withBasicAuth(
                    config('paiement.orange_money.client_id'),
                    config('paiement.orange_money.client_secret')
                )
                ->post(config('paiement.orange_money.url_jeton'), ['grant_type' => 'client_credentials']);

            return (string) $reponse->json('access_token');
        });
    }
}

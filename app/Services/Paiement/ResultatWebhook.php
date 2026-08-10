<?php

namespace App\Services\Paiement;

/** Résultat normalisé d'une notification opérateur, indépendant du fournisseur. */
class ResultatWebhook
{
    public function __construct(
        public readonly string $referenceInterne,
        public readonly string $statut,            // confirmee | echouee | expiree
        public readonly ?string $referenceOperateur = null,
        public readonly ?string $message = null,
        public readonly array $payload = [],
    ) {}

    public function estConfirme(): bool
    {
        return $this->statut === 'confirmee';
    }
}

<?php

namespace App\Services\Paiement;

use App\Models\Paiement;

/**
 * Contrat commun à toutes les passerelles de paiement. Ajouter un nouvel
 * opérateur (ou le paiement bancaire en ligne) se limite à écrire une classe
 * implémentant cette interface et à créer la ligne correspondante dans
 * la table moyens_paiement — aucun autre code n'est modifié.
 */
interface Passerelle
{
    /** Déclenche la demande de paiement chez l'opérateur (push USSD / STK). */
    public function initier(Paiement $paiement, string $numeroTelephone): void;

    /** Interprète la notification reçue de l'opérateur. */
    public function interpreterWebhook(array $payload): ResultatWebhook;

    /** Vérifie l'authenticité de la notification (signature, IP, jeton). */
    public function verifierSignature(array $payload, array $entetes): bool;
}

<?php

namespace App\Services\Paiement;

use App\Models\MoyenPaiement;
use InvalidArgumentException;

class PasserelleFactory
{
    /** Correspondance entre la colonne moyens_paiement.passerelle et le driver. */
    private const DRIVERS = [
        'orange_money' => OrangeMoneyPasserelle::class,
        'mtn_momo'     => MtnMomoPasserelle::class,
    ];

    public function pour(MoyenPaiement $moyen): Passerelle
    {
        return $this->parCle((string) $moyen->passerelle);
    }

    public function parCle(string $cle): Passerelle
    {
        if (! isset(self::DRIVERS[$cle])) {
            throw new InvalidArgumentException("Aucune passerelle de paiement n'est configurée pour « {$cle} ».");
        }

        return app(self::DRIVERS[$cle]);
    }
}

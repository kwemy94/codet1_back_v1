<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /** Réponse normalisée de l'API : { "donnees": ..., "message": ... } */
    protected function reponse(mixed $donnees = null, ?string $message = null, int $code = 200)
    {
        return response()->json(array_filter([
            'donnees' => $donnees,
            'message' => $message,
        ], fn ($v) => ! is_null($v)), $code);
    }

    protected function refuserSiNonAdministrateur(): void
    {
        abort_if(
            ! auth()->user()?->estAdministrateur(),
            403,
            "Vous n'êtes pas habilité à effectuer cette action."
        );
    }
}

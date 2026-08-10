<?php

/*
 * Configuration des passerelles de paiement.
 * Aucune clé n'est écrite en dur : tout provient du fichier .env.
 */
return [

    'url_retour' => env('PAIEMENT_URL_RETOUR', env('APP_URL').'/paiement/retour'),

    'orange_money' => [
        'url_jeton'      => env('OM_URL_JETON', 'https://api.orange.com/oauth/v3/token'),
        'url_paiement'   => env('OM_URL_PAIEMENT', 'https://api.orange.com/orange-money-webpay/cm/v1/webpayment'),
        'client_id'      => env('OM_CLIENT_ID'),
        'client_secret'  => env('OM_CLIENT_SECRET'),
        'cle_marchand'   => env('OM_CLE_MARCHAND'),
        'secret_webhook' => env('OM_SECRET_WEBHOOK'),
    ],

    'mtn_momo' => [
        'url_jeton'       => env('MOMO_URL_JETON', 'https://proxy.momoapi.mtn.com/collection/token/'),
        'url_paiement'    => env('MOMO_URL_PAIEMENT', 'https://proxy.momoapi.mtn.com/collection/v1_0/requesttopay'),
        'utilisateur_api' => env('MOMO_UTILISATEUR_API'),
        'cle_api'         => env('MOMO_CLE_API'),
        'cle_abonnement'  => env('MOMO_CLE_ABONNEMENT'),
        'environnement'   => env('MOMO_ENVIRONNEMENT', 'mtncameroon'),
        'ips_autorisees'  => array_filter(explode(',', (string) env('MOMO_IPS_AUTORISEES', ''))),
    ],
];

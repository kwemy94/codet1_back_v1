<?php

/*
 * Partage des ressources entre origines (CORS).
 *
 * Le frontend et l'API vivent sur deux sous-domaines distincts
 * (codet1.streetsmart.tech et codet1-api.streetsmart.tech) : toute requête du
 * navigateur est donc inter-origine. Le navigateur envoie d'abord une requête
 * OPTIONS de contrôle ; sans les en-têtes ci-dessous, elle échoue et aucune
 * requête ne part.
 *
 * Les origines autorisées viennent de .env : jamais de domaine écrit en dur,
 * et surtout pas « * » — l'API porte des données nominatives et financières.
 */

$origines = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ORIGINES_AUTORISEES', (string) env('FRONTEND_URL')))
)));

return [

    // Toutes les routes de l'API, plus le point d'entrée Sanctum.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origines,

    // Pour d'éventuels environnements de recette :
    // CORS_MOTIFS_ORIGINES="#^https://.*\.streetsmart\.tech$#"
    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_MOTIFS_ORIGINES', ''))
    ))),

    'allowed_headers' => ['*'],

    /*
     * En-têtes que le navigateur laisse lire au frontend. Content-Disposition
     * est indispensable : sans lui, les téléchargements de PDF et de pièces
     * jointes perdent leur nom de fichier.
     */
    'exposed_headers' => ['Content-Disposition'],

    // Mise en cache de la requête de contrôle, en secondes.
    'max_age' => 3600,

    /*
     * L'authentification passe par un jeton Bearer, pas par un cookie de
     * session : les identifiants d'origine ne sont pas nécessaires. Les activer
     * imposerait des contraintes supplémentaires sans rien apporter.
     */
    'supports_credentials' => false,

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * L'interface, l'application mobile et le site vitrine (appel serveur à serveur) n'ont pas besoin du CORS :
     * seuls les domaines listés ici peuvent appeler l'API depuis un navigateur. Par défaut, la plateforme elle-même.
     */
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ORIGINES_AUTORISEES', env('APP_URL', '')))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

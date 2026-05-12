<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RaptorPOS API Integration
    |--------------------------------------------------------------------------
    | Set RAPTOR_ENABLED=true in .env to activate pushing paid sales to Raptor.
    */

    'enabled'     => env('RAPTOR_ENABLED', false),
    'api_url'     => env('RAPTOR_API_URL', 'http://test-api.probussystem.net:5000/bills'),
    'jwt_key'     => env('RAPTOR_JWT_KEY', 'raptor'),

    // Outlet / property identifiers
    'code_outlet' => env('RAPTOR_CODE_OUTLET', 'RST'),
    'cdept'       => env('RAPTOR_CDEPT', 'FB'),
    'groups'      => env('RAPTOR_GROUPS', 'FB'),
    'nationality' => env('RAPTOR_NATIONALITY', 'LOCAL'),
    'valuetaxser' => env('RAPTOR_VALUETAXSER', 'Y'),
    'location'    => env('RAPTOR_LOCATION', '01'),
];

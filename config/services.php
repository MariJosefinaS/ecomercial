<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Escaneo de facturas (visión). Driver elegible: 'google' (API nativa de
    // Gemini, gratis con key propia, lee PDF e imágenes) u 'openrouter' (gateway,
    // A/B de modelos, parsea PDF). Ver App\Support\FacturaScanner.
    'vision' => [
        'provider' => env('VISION_PROVIDER', 'openrouter'),
    ],

    // OpenRouter: gateway OpenAI-compatible.
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_VISION_MODEL', 'anthropic/claude-haiku-4.5'),
    ],

    // Google AI Studio (Gemini) — API nativa. Gratis dentro del free tier.
    'google_ai' => [
        'key' => env('GOOGLE_AI_KEY'),
        'base_url' => env('GOOGLE_AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('GOOGLE_VISION_MODEL', 'gemini-2.5-flash'),
    ],

];

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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'chat_model' => env('OPENAI_MODEL', 'gpt-4o'),
        'web_search_model' => env('OPENAI_WEB_SEARCH_MODEL', 'gpt-5-search-api'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
        'embeddings_model' => env('OPENAI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
        'embeddings_dimensions' => (int) env('OPENAI_EMBEDDINGS_DIMENSIONS', 1536),
        'rag_top_k' => (int) env('OPENAI_RAG_TOP_K', 5),
        'rag_max_distance' => (float) env('OPENAI_RAG_MAX_DISTANCE', 0.6),
    ],

];

<?php

declare(strict_types=1);

/*
 * Environment variable names are kept from the application this package was extracted from,
 * so an existing `.env` keeps working.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Navigation group
    |--------------------------------------------------------------------------
    */
    'navigation_group' => env('FILAGORA_AI_NAVIGATION_GROUP', 'AI'),

    /*
    |--------------------------------------------------------------------------
    | Gate abilities
    |--------------------------------------------------------------------------
    |
    | Maps each ability this package checks to the entitlement that allows it. Point an
    | ability at a different entitlement to use your own keys.
    |
    */
    'gates' => [
        'filagora.ai.view-usage' => 'filagora.ai.view-usage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Off by default: no cost and no behaviour change until turned on. `cost_per_million_tokens`
    | is USD per million input tokens; a model with no rate here has its cost left empty.
    |
    */
    'embeddings' => [
        'enabled' => env('AI_EMBEDDINGS_ENABLED', false),
        'driver' => env('AI_EMBEDDINGS_DRIVER', 'openai'), // openai|voyage|digitalocean
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'dimensions' => env('OPENAI_EMBEDDING_DIMENSIONS', 1536),
        ],
        'voyage' => [
            'api_key' => env('VOYAGE_API_KEY'),
            'embedding_model' => env('VOYAGE_EMBEDDING_MODEL', 'voyage-3-lite'),
            'dimensions' => env('VOYAGE_EMBEDDING_DIMENSIONS', 512),
        ],
        // DigitalOcean Serverless Inference: an OpenAI-compatible response shape.
        'digitalocean' => [
            'api_key' => env('DIGITALOCEAN_INFERENCE_API_KEY'),
            'embedding_model' => env('DIGITALOCEAN_EMBEDDING_MODEL', 'bge-m3'),
            'dimensions' => env('DIGITALOCEAN_EMBEDDING_DIMENSIONS', 1024),
        ],
        'cost_per_million_tokens' => [
            'openai' => [
                'text-embedding-3-small' => env('AI_COST_OPENAI_TEXT_EMBEDDING_3_SMALL', 0.02),
            ],
            'voyage' => [
                'voyage-3-lite' => env('AI_COST_VOYAGE_VOYAGE_3_LITE', 0.02),
            ],
            'digitalocean' => [
                'bge-m3' => env('AI_COST_DIGITALOCEAN_BGE_M3', 0.02),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat
    |--------------------------------------------------------------------------
    |
    | `enabled` is an operational kill-switch, separate from the entitlement that lets a person
    | use the chat: either one alone turns the feature off. Input and output tokens are priced
    | separately, in USD per million. A model with no price below is costed at the fallback, so
    | its calls still count against a limit instead of costing nothing.
    |
    */
    'chat' => [
        'enabled' => env('AI_CHAT_ENABLED', false),
        'driver' => env('AI_CHAT_DRIVER', 'anthropic'), // anthropic|openai|digitalocean
        'retrieval_top_k' => env('AI_CHAT_RETRIEVAL_TOP_K', 8),
        // A stand-in monthly allowance in the usage log's cost unit (USD). Unset, the meter shows a count.
        'allowance_usd' => env('AI_USAGE_ALLOWANCE_USD'),
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_CHAT_MODEL', 'claude-sonnet-5'),
            'max_tokens' => env('ANTHROPIC_CHAT_MAX_TOKENS', 1024),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_CHAT_MODEL', 'gpt-5'),
        ],
        // A separate key from the embeddings one: DigitalOcean's model access keys are scoped
        // to specific models, so the embeddings key does not work for chat.
        'digitalocean' => [
            'api_key' => env('DIGITALOCEAN_CHAT_API_KEY'),
            'model' => env('DIGITALOCEAN_CHAT_MODEL'),
        ],
        'cost_per_million_tokens' => [
            'anthropic' => [
                'claude-sonnet-5' => [
                    'input' => env('AI_CHAT_COST_ANTHROPIC_CLAUDE_SONNET_5_INPUT', 3.00),
                    'output' => env('AI_CHAT_COST_ANTHROPIC_CLAUDE_SONNET_5_OUTPUT', 15.00),
                ],
            ],
            'openai' => [
                'gpt-5' => [
                    'input' => env('AI_CHAT_COST_OPENAI_GPT_5_INPUT', 1.25),
                    'output' => env('AI_CHAT_COST_OPENAI_GPT_5_OUTPUT', 10.00),
                ],
            ],
        ],
        'fallback_cost_per_million_tokens' => [
            'input' => env('AI_CHAT_FALLBACK_COST_INPUT', 15.00),
            'output' => env('AI_CHAT_FALLBACK_COST_OUTPUT', 75.00),
        ],
    ],

];

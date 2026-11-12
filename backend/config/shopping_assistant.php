<?php

return [
    'coach_enabled' => env('LUNA_COACH_ENABLED', false),
    'enabled' => env(
        'SHOPPING_ASSISTANT_ENABLED',
        in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
    ),
    'driver' => env('SHOPPING_ASSISTANT_DRIVER', 'mock'),
    'model' => env('SHOPPING_ASSISTANT_MODEL', 'gpt-6.1-terra'),
    'base_url' => env('GAPGPT_BASE_URL', 'https://api.gapgpt.app/v1'),
    'endpoint' => env('GAPGPT_ENDPOINT', 'chat_completions'),
    'api_key' => env('GAPGPT_API_KEY'),
    'timeout_seconds' => (int) env('GAPGPT_TIMEOUT_SECONDS', 15),
    'max_output_tokens' => (int) env('SHOPPING_ASSISTANT_MAX_OUTPUT_TOKENS', 900),
    'max_message_characters' => (int) env('SHOPPING_ASSISTANT_MAX_MESSAGE_CHARACTERS', 1200),
    'daily_quota_enabled' => env('SHOPPING_ASSISTANT_DAILY_QUOTA_ENABLED', false),
    'daily_quota_messages' => (int) env('SHOPPING_ASSISTANT_DAILY_QUOTA_MESSAGES', 20),
    'max_context_messages' => (int) env('SHOPPING_ASSISTANT_MAX_CONTEXT_MESSAGES', 8),
    'max_context_characters' => (int) env('SHOPPING_ASSISTANT_MAX_CONTEXT_CHARACTERS', 5000),
    'session_ttl_minutes' => (int) env('SHOPPING_ASSISTANT_SESSION_TTL_MINUTES', 60),
    'max_products' => (int) env('SHOPPING_ASSISTANT_MAX_PRODUCTS', 5),
    'input_cost_per_million_tokens' => env('GAPGPT_INPUT_COST_PER_MILLION_TOKENS', 0.10),
    'output_cost_per_million_tokens' => env('GAPGPT_OUTPUT_COST_PER_MILLION_TOKENS', 0.50),
    'daily_budget_usd' => env('SHOPPING_ASSISTANT_DAILY_BUDGET_USD'),
    'monthly_budget_usd' => env('SHOPPING_ASSISTANT_MONTHLY_BUDGET_USD'),
    'local_test_budget_usd' => env('SHOPPING_ASSISTANT_LOCAL_TEST_BUDGET_USD'),
    // Admin notification only; no API budget and no account balance assumption.
    'high_usage_alert_min_requests_per_hour' => (int) env('LUNA_HIGH_USAGE_ALERT_REQUESTS_PER_HOUR', 40),
    'high_usage_alert_min_tokens_per_hour' => (int) env('LUNA_HIGH_USAGE_ALERT_TOKENS_PER_HOUR', 150000),
    'customer_url' => env('TKB_CUSTOMER_URL'),
];

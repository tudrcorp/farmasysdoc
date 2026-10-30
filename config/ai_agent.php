<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini
    |--------------------------------------------------------------------------
    |
    | The model and API key are shared with the CV reader (config/services.php).
    | Prices are USD per million tokens and only feed the internal budget, so
    | keep them in line with https://ai.google.dev/gemini-api/docs/pricing.
    |
    */

    'model' => env('AI_AGENT_MODEL', env('GEMINI_MODEL', 'gemini-3.6-flash')),

    'timeout' => (int) env('AI_AGENT_TIMEOUT', 90),

    'prices' => [
        ['from' => '2000-01-01', 'input' => 0.75, 'output' => 3.75],
        ['from' => '2027-01-01', 'input' => 1.50, 'output' => 7.50],
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'monthly_budget_usd' => (float) env('AI_AGENT_MONTHLY_BUDGET_USD', 20),

    'budget_warning_ratio' => 0.8,

    'max_tool_rounds' => 8,

    'max_rows_for_model' => 200,

    'max_rows_for_report' => 5000,

    'query_timeout_ms' => 10000,

    'max_tool_result_chars' => 30000,

    /*
    |--------------------------------------------------------------------------
    | Data access
    |--------------------------------------------------------------------------
    */

    'blocked_tables' => [
        'ai_agent_conversations', 'ai_agent_messages', 'ai_agent_reports',
        'api_clients', 'audit_logs', 'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs',
        'migrations', 'password_reset_tokens', 'personal_access_tokens', 'sessions',
    ],

    // Matched against every SQL identifier. Credentials and personal data are never readable.
    'blocked_column_pattern' => '/(^|_)(password|token|hash|secret|otp|recovery|remember|api_key|credential|national_id|identity|document|rif|phone|mobile|email|address|latitude|longitude|signature|fingerprint|photo|cv|bank_account|account_number|ip)(_|$)/',

];

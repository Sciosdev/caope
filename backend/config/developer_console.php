<?php

return [
    'enabled' => (bool) env('DEVELOPER_CONSOLE_ENABLED', false),

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DEVELOPER_CONSOLE_ALLOWED_IPS', ''))
    ))),

    'password_timeout' => max(60, (int) env('DEVELOPER_CONSOLE_PASSWORD_TIMEOUT', 900)),

    'target_label' => (string) env('DEVELOPER_CONSOLE_TARGET_LABEL', 'producción'),

    'deploy_webhook_token' => (string) env('DEVELOPER_CONSOLE_DEPLOY_WEBHOOK_TOKEN', ''),

    'github' => [
        'api_url' => rtrim((string) env('DEVELOPER_CONSOLE_GITHUB_API_URL', 'https://api.github.com'), '/'),
        'repository' => (string) env('DEVELOPER_CONSOLE_GITHUB_REPOSITORY', 'Sciosdev/caope'),
        'workflow' => (string) env('DEVELOPER_CONSOLE_GITHUB_WORKFLOW', 'deploy.yml'),
        'ref' => (string) env('DEVELOPER_CONSOLE_GITHUB_REF', 'main'),
        'token' => (string) env('DEVELOPER_CONSOLE_GITHUB_TOKEN', ''),
    ],
];

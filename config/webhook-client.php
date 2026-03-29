<?php

return [
    'configs' => [
        [
            'name' => 'github-push',
            'signing_secret' => env('GITHUB_WEBHOOK_SECRET'),
            'signature_header_name' => 'X-Hub-Signature-256',
            'signature_validator' => \Spatie\WebhookClient\SignatureValidator\DefaultSignatureValidator::class,
            'webhook_profile' => \App\WebhookProfiles\GitHubPushWebhookProfile::class,
            'webhook_response' => \Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'store_headers' => [
                'X-GitHub-Event',
                'X-GitHub-Delivery',
                'X-Hub-Signature-256',
            ],
            'process_webhook_job' => \App\Jobs\ProcessGitHubPushWebhookJob::class,
        ],
    ],

    /*
    | The integer amount of days after which models should be deleted.
    |
    | It deletes all records after 30 days. Set to null if no models should be deleted.
    */
    'delete_after_days' => 30,

    /*
    | Should a unique token be added to the route name
     */
    'add_unique_token_to_route_name' => false,
];

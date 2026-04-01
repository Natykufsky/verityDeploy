<?php

use App\Jobs\ProcessGitHubPushWebhookJob;
use App\WebhookProfiles\GitHubPushWebhookProfile;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\SignatureValidator\DefaultSignatureValidator;
use Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo;

return [
    'configs' => [
        [
            'name' => 'github-push',
            'signing_secret' => env('GITHUB_WEBHOOK_SECRET'),
            'signature_header_name' => 'X-Hub-Signature-256',
            'signature_validator' => DefaultSignatureValidator::class,
            'webhook_profile' => GitHubPushWebhookProfile::class,
            'webhook_response' => DefaultRespondsTo::class,
            'webhook_model' => WebhookCall::class,
            'store_headers' => [
                'X-GitHub-Event',
                'X-GitHub-Delivery',
                'X-Hub-Signature-256',
            ],
            'process_webhook_job' => ProcessGitHubPushWebhookJob::class,
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

<?php

declare(strict_types=1);

namespace App\Services;

use Aws\Sns\SnsClient;
use Http;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

/**
 * Inert template extension: dev-mode replacement for Aws\Sns\SnsClient that
 * routes publish() calls to a local mock server (Twilio's WebhooksHog or
 * similar). Not wired by default — see configureSns() in AppServiceProvider
 * and install aws/aws-sdk-php + laravel-notification-channels/aws-sns to
 * enable.
 *
 * @codeCoverageIgnore
 */
class SnsHogService extends SnsClient
{
    private Factory|PendingRequest $client;

    public function __construct(array $config = [])
    {
        parent::__construct($config + ['service' => 'sns']);
        $this->client = Http::baseUrl($config['mock_server_url']);
    }

    public function publish(array $args): void
    {
        $body = $args['Message'] ?? '';
        $to   = $args['PhoneNumber'] ?? '';

        $this->client->post('/2010-04-01/Accounts/123213123/Messages.json', [
            'From' => '+15005550006',
            'To'   => $to,
            'Body' => $body,
        ]);
    }
}

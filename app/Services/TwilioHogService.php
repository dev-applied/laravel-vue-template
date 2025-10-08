<?php

declare(strict_types=1);

namespace App\Services;

use Twilio\AuthStrategy\AuthStrategy;
use Twilio\Http\CurlClient;
use Twilio\Http\Response;
use Twilio\Rest\Client as TwilioService;

/** @codeCoverageIgnore */
class TwilioHogService extends TwilioService
{
    public function __construct(
        string $accountSid,
        string $authToken,
        string $baseUrl,          // e.g. http://localhost:8000
        ?string $region = null,
        ?array $environment = null,
        array $userAgentExt = []
    ) {
        // Wrap CurlClient to rewrite the URI
        $httpClient = new class($baseUrl) extends CurlClient
        {
            private string $baseUrl;

            public function __construct(string $baseUrl)
            {
                parent::__construct();
                $this->baseUrl = mb_rtrim($baseUrl, '/');
            }

            public function request(
                string $method,
                string $url,
                array $params = [],
                array $data = [],
                array $headers = [],
                ?string $user = null,
                ?string $password = null,
                ?int $timeout = null,
                ?AuthStrategy $authStrategy = null
            ): Response {
                // Parse out the path and query from the Twilio URI
                $parts = parse_url($url);
                $path  = ($parts['path'] ?? '')
                    .(isset($parts['query']) ? "?{$parts['query']}" : '');

                // Prepend your base URL
                $newUri = $this->baseUrl.$path;

                return parent::request(
                    $method,
                    $newUri,
                    $params,
                    $data,
                    $headers,
                    $user,
                    $password,
                    $timeout,
                    $authStrategy
                );
            }
        };

        parent::__construct(
            $accountSid,    // username
            $authToken,     // password
            $accountSid,    // accountSid
            $region,        // region (nullable)
            $httpClient,    // our rewriting client
            $environment,   // use default getenv()
            $userAgentExt
        );
    }
}

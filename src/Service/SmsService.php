<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class SmsService
{
    private readonly Client $twilioClient;

    public function __construct(
        private readonly string $twilioAccountSid,
        private readonly string $twilioAuthToken,
        private readonly string $twilioPhone,
        private readonly LoggerInterface $logger,
    ) {
        $this->twilioClient = new Client($this->twilioAccountSid, $this->twilioAuthToken);
    }

    public function send(string $to, string $message): bool
    {
        try {
            $this->twilioClient->messages->create($to, [
                'from' => $this->twilioPhone,
                'body' => $message,
            ]);

            $this->logger->info('SMS sent successfully', ['to' => $to]);

            return true;
        } catch (TwilioException $e) {
            $this->logger->error('Failed to send SMS', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

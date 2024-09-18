<?php

declare(strict_types=1);

namespace HawkBundle\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Hawk\Event;
use Hawk\Options;
use Hawk\Transport\TransportInterface;

class GuzzlePromisesTransport implements TransportInterface
{
    /**
     * Guzzle Client
     *
     * @var Client
     */
    private $client;

    /**
     * CurlTransport constructor.
     */
    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): string
    {
        return (new Options())->getUrl();
    }

    /**
     * @inheritDoc
     */
    public function send(Event $event)
    {
        $promise = $this->client->postAsync($this->getUrl(), [
            'json' => $event->jsonSerialize(),
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 10
        ]);

        return $promise->then(
            function ($response) {
                return $response->getBody()->getContents();
            },
            function (RequestException $e) {
                throw new \Exception('Failed to send event: ' . $e->getMessage(), 0, $e);
            }
        )->wait();
    }
}

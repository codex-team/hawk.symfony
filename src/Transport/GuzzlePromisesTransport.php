<?php

declare(strict_types=1);

namespace HawkBundle\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Hawk\Event;
use Hawk\Transport\TransportInterface;

class GuzzlePromisesTransport implements TransportInterface
{
    /**
     * URL to send occurred event
     *
     * @var string
     */
    private $url;

    /**
     * Guzzle Client
     *
     * @var Client
     */
    private $client;

    /**
     * CurlTransport constructor.
     *
     * @param string $url
     */
    public function __construct(string $url, Client $client)
    {
        $this->url = $url;
        $this->client = $client;
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @inheritDoc
     */
    public function send(Event $event): mixed
    {
        $promise = $this->client->postAsync($this->url, [
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

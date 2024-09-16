<?php

declare(strict_types=1);

namespace HawkBundle;

use GuzzleHttp\Client;
use Hawk\Addons\Headers;
use Hawk\EventPayloadBuilder;
use Hawk\Options;
use Hawk\Serializer;
use Hawk\StacktraceFrameBuilder;
use HawkBundle\Transport\GuzzlePromisesTransport;

/**
 * Hawk PHP Catcher SDK
 *
 * @copyright CodeX
 *
 * @see https://hawk.so/docs#add-server-handler
 */
final class Catcher
{
    /**
     * SDK handler: contains methods that catchs errors and exceptions
     *
     * @var Handler
     */
    private $handler;

    /**
     * @param array  $options
     * @param Client $client
     */
    public function __construct(array $options, Client $client)
    {
        $options = new Options($options);

        /**
         * Init stacktrace frames builder and inject serializer
         */
        $serializer = new Serializer();
        $stacktraceBuilder = new StacktraceFrameBuilder($serializer);

        /**
         * Prepare Event payload builder
         */
        $builder = new EventPayloadBuilder($stacktraceBuilder);
        $builder->registerAddon(new Headers());

        $transport = new GuzzlePromisesTransport($options->getUrl(), $client);

        $this->handler = new HawkHandler($options, $transport, $builder);

        $this->handler->registerErrorHandler();
        $this->handler->registerExceptionHandler();
        $this->handler->registerFatalHandler();
    }

    /**
     * @param array $user
     *
     * @return $this
     */
    public function setUser(array $user): self
    {
        $this->handler->withUser($user);

        return $this;
    }

    /**
     * @param array $context
     *
     * @return $this
     */
    public function setContext(array $context): self
    {
        $this->handler->withContext($context);

        return $this;
    }

    /**
     * @param string $message
     * @param array  $context
     */
    public function sendMessage(string $message, array $context = []): void
    {
        $this->handler->sendEvent([
            'title' => $message,
            'context' => $context
        ]);
    }

    /**
     * @param \Throwable $throwable
     * @param array      $context
     */
    public function sendException(\Throwable $throwable, array $context = [])
    {
        $this->handler->handleException($throwable, $context);
    }

    /**
     * @param array $payload
     */
    public function sendEvent(array $payload): void
    {
        $this->handler->sendEvent($payload);
    }
}

<?php

declare(strict_types=1);

namespace HawkBundle;

use Hawk\Event;
use Hawk\EventPayload;
use Hawk\EventPayloadBuilder;
use Hawk\Options;
use Hawk\Transport\TransportInterface;
use HawkBundle\Exception\SilencedErrorException;

class HawkHandler
{
    /**
     * Configuration options for the handler.
     *
     * @var Options
     */
    private $options;

    /**
     * Transport layer for sending events to the remote server.
     *
     * @var TransportInterface
     */
    private $transport;

    /**
     * Builder that constructs event payloads before sending them.
     *
     * @var EventPayloadBuilder
     */
    private $eventPayloadBuilder;

    /**
     * Data related to the current user for event tracking.
     *
     * @var array
     */
    private $user = [];

    /**
     * Contextual information to be attached to events.
     *
     * @var array
     */
    private $context = [];

    /**
     * Flags for determining if handlers are already registered.
     */
    private $isErrorHandlerRegistered = false;
    private $isExceptionHandlerRegistered = false;
    private $isFatalHandlerRegistered = false;
    private $disableFatalErrorHandler = false;

    /**
     * Previous handlers to restore later if needed.
     */
    private $previousErrorHandler = null;
    private $previousExceptionHandler = null;

    /**
     * PHP 8+ fatal errors that cannot be silenced.
     */
    private const PHP8_FATAL_ERRORS = \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR;

    /**
     * Descriptions of various PHP error levels for better event tracking.
     */
    private const ERROR_LEVEL_DESCRIPTIONS = [
        \E_DEPRECATED => 'Deprecated',
        \E_USER_DEPRECATED => 'User Deprecated',
        \E_NOTICE => 'Notice',
        \E_USER_NOTICE => 'User Notice',
        \E_STRICT => 'Runtime Notice',
        \E_WARNING => 'Warning',
        \E_USER_WARNING => 'User Warning',
        \E_COMPILE_WARNING => 'Compile Warning',
        \E_CORE_WARNING => 'Core Warning',
        \E_USER_ERROR => 'User Error',
        \E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        \E_COMPILE_ERROR => 'Compile Error',
        \E_PARSE => 'Parse Error',
        \E_ERROR => 'Error',
        \E_CORE_ERROR => 'Core Error',
    ];

    public function __construct(
        Options $options,
        TransportInterface $transport,
        EventPayloadBuilder $eventPayloadBuilder
    ) {
        $this->options = $options;
        $this->transport = $transport;
        $this->eventPayloadBuilder = $eventPayloadBuilder;
    }

    /**
     * Attach user data for event logging.
     *
     * @param array $user
     */
    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    /**
     * Attach contextual data to provide more details about the event.
     *
     * @param array $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Register the error handler once to handle PHP errors.
     */
    public function registerErrorHandler(): self
    {
        if ($this->isErrorHandlerRegistered) {
            return $this;
        }

        $errorHandlerCallback = \Closure::fromCallable([$this, 'handleError']);

        $this->previousErrorHandler = set_error_handler($errorHandlerCallback);
        if (null === $this->previousErrorHandler) {
            restore_error_handler();
            set_error_handler($errorHandlerCallback, $this->options->getErrorTypes());
        }

        $this->isErrorHandlerRegistered = true;

        return $this;
    }

    /**
     * Register the exception handler once to manage uncaught exceptions.
     */
    public function registerExceptionHandler(): self
    {
        if ($this->isExceptionHandlerRegistered) {
            return $this;
        }

        $exceptionHandlerCallback = \Closure::fromCallable([$this, 'handleException']);

        $this->previousExceptionHandler = set_exception_handler($exceptionHandlerCallback);
        $this->isExceptionHandlerRegistered = true;

        return $this;
    }

    /**
     * Register the fatal error handler to catch shutdown errors.
     */
    public function registerFatalHandler(): self
    {
        if ($this->isFatalHandlerRegistered) {
            return $this;
        }

        register_shutdown_function(\Closure::fromCallable([$this, 'handleFatal']));
        $this->isFatalHandlerRegistered = true;

        return $this;
    }

    /**
     * Handle PHP errors, convert them to exceptions, and send the event.
     */
    public function handleError(int $level, string $message, string $file, int $line): bool
    {
        $isSilencedError = 0 === error_reporting();

        if (\PHP_MAJOR_VERSION >= 8) {
            // Detect if the error was silenced in PHP 8+
            $isSilencedError = 0 === (error_reporting() & ~self::PHP8_FATAL_ERRORS);

            if ($level === (self::PHP8_FATAL_ERRORS & $level)) {
                $isSilencedError = false;
            }
        }

        if ($isSilencedError) {
            $exception = new SilencedErrorException(self::ERROR_LEVEL_DESCRIPTIONS[$level] . ': ' . $message, 0, $level, $file, $line);
        } else {
            $exception = new \ErrorException(self::ERROR_LEVEL_DESCRIPTIONS[$level] . ': ' . $message, 0, $level, $file, $line);
        }

        $data = [
            'exception' => $exception,
            'context' => $this->context,
            'user' => $this->user,
            'type' => $exception->getSeverity()
        ];

        $eventPayload = $this->eventPayloadBuilder->create($data);
        $event = $this->buildEvent($eventPayload);

        if ($event !== null) {
            $this->send($event);

            return false !== ($this->previousErrorHandler)($level, $message, $file, $line);
        }

        return false;
    }

    /**
     * Handle uncaught exceptions and send the event.
     */
    public function handleException(\Throwable $exception, array $context = []): void
    {
        $data = [
            'exception' => $exception,
            'context' => array_merge($this->context, $context),
            'user' => $this->user
        ];

        $eventPayload = $this->eventPayloadBuilder->create($data);
        $event = $this->buildEvent($eventPayload);

        if ($event !== null) {
            $this->send($event);
        }

        $previousExceptionHandlerException = $exception;

        $previousExceptionHandler = $this->previousExceptionHandler;
        $this->previousExceptionHandler = null;

        try {
            if (null !== $previousExceptionHandler) {
                $previousExceptionHandler($exception);

                return;
            }
        } catch (\Throwable $previousExceptionHandlerException) {
            // This `catch` statement is here to forcefully override the
            // $previousExceptionHandlerException variable with the exception
            // we just caught
        }

        // If the instance of the exception we're handling is the same as the one
        // caught from the previous exception handler then we give it back to the
        // native PHP handler to prevent an infinite loop
        if ($exception === $previousExceptionHandlerException) {
            // Disable the fatal error handler or the error will be reported twice
            $this->disableFatalErrorHandler = true;

            throw $exception;
        }

        $this->handleException($previousExceptionHandlerException);
    }

    /**
     * Handle fatal errors that occur during script shutdown.
     */
    public function handleFatal(): void
    {
        if ($this->disableFatalErrorHandler) {
            return;
        }

        $error = error_get_last();

        if (
            $error === null
            || is_array($error) && $error['type'] && (\E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_CORE_WARNING | \E_COMPILE_ERROR | \E_COMPILE_WARNING)
        ) {
            return;
        }

        $payload = [
            'exception' => new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ),
            'context' => $this->context,
            'user' => $this->user
        ];

        $eventPayload = $this->eventPayloadBuilder->create($payload);
        $event = $this->buildEvent($eventPayload);

        if ($event !== null) {
            $this->send($event);
        }
    }

    /**
     * Prepare the event for sending by applying release information and optional modifications.
     */
    public function sendEvent(array $payload): void
    {
        $payload['context'] = array_merge($this->context, $payload['context'] ?? []);
        $payload['user'] = $this->user;

        $eventPayload = $this->eventPayloadBuilder->create($payload);
        $event = $this->buildEvent($eventPayload);

        if ($event !== null) {
            $this->send($event);
        }
    }

    /**
     * Prepare the event for sending by applying release information and optional modifications.
     */
    public function buildEvent(EventPayload $eventPayload): ?Event
    {
        $eventPayload->setRelease($this->options->getRelease());
        $beforeSendCallback = $this->options->getBeforeSend();
        if ($beforeSendCallback) {
            $eventPayload = $beforeSendCallback($eventPayload);
            if ($eventPayload === null) {
                return null;
            }
        }
        $event = new Event(
            $this->options->getIntegrationToken(),
            $eventPayload
        );

        return $event;
    }

    /**
     * Send the event to the remote server.
     */
    private function send(Event $event): void
    {
        $this->transport->send($event);
    }
}

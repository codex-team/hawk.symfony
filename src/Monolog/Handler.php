<?php

declare(strict_types=1);

namespace HawkBundle\Monolog;

use HawkBundle\Catcher;
use Http\Discovery\Psr17FactoryDiscovery;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\RequestStack;

final class Handler extends AbstractProcessingHandler
{
    use HandlerTrait;

    private $catcher;
    private $request;

    public function __construct(Catcher $catcher, RequestStack $request, $level = Logger::ERROR, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->catcher = $catcher;
        $this->request = $request;
    }

    public function doWrite($record): void
    {
        $data = [
            'level' => $record['level'],
            'title' => (new LineFormatter('%message%'))->format($record)
        ];

        $data['context'] = $this->collectRequestInfo();

        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            $data['exception'] = $record['context']['exception'];
        }

        $this->catcher->sendEvent($data);
    }

    private function collectRequestInfo(): array
    {
        $factory = new PsrHttpFactory(
            Psr17FactoryDiscovery::findServerRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
            Psr17FactoryDiscovery::findUploadedFileFactory(),
            Psr17FactoryDiscovery::findResponseFactory()
        );

        $request = $factory->createRequest(
            $this->request->getCurrentRequest()
        );

        return [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'query_params' => $request->getQueryParams(),
            'parsed_body' => $request->getParsedBody()
        ];
    }
}

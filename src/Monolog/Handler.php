<?php

declare(strict_types=1);

namespace HawkBundle\Monolog;

use HawkBundle\Catcher;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class Handler extends AbstractProcessingHandler
{
    private $catcher;

    public function __construct(Catcher $catcher, $level = Logger::ERROR, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->catcher = $catcher;
    }

    /**
     * @inheritDoc
     */
    public function write(array $record): void
    {
        $data = [
            'level' => $record['level'],
            'title' => (new LineFormatter('%message%'))->format($record)
        ];

        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            $data['exception'] = $record['context']['exception'];
        }

        $this->catcher->sendEvent($data);
    }
}

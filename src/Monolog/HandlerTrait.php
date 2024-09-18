<?php

namespace HawkBundle\Monolog;

use Monolog\Logger;
use Monolog\LogRecord;

if (Logger::API >= 3) {
    trait HandlerTrait
    {
        /**
         * @param array<string, mixed>|LogRecord $record
         */
        abstract protected function doWrite($record): void;

        protected function write(LogRecord $record): void
        {
            $this->doWrite($record);
        }
    }
} else {
    trait HandlerTrait
    {
        /**
         * @param array<string, mixed>|LogRecord $record
         */
        abstract protected function doWrite($record): void;

        protected function write(array $record): void
        {
            $this->doWrite($record);
        }
    }
}

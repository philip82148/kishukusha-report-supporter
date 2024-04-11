<?php

namespace KishukushaReportSupporter;

class ExceptionWrapper extends \RuntimeException
{
    public \Throwable $exception;
    public string $additionalMsg;

    public function __construct(\Throwable $exception, string $additionalMsg)
    {
        $this->exception = $exception;
        $this->additionalMsg = $additionalMsg;
    }

    public function __toString(): string
    {
        return "{$this->exception}\n{$this->additionalMsg}";
    }
}

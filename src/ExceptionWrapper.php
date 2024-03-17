<?php

namespace KishukushaReportSupporter;

class ExceptionWrapper extends \RuntimeException
{
    public function __construct(\Throwable $exception, string $additionalMsg)
    {
        $msg = DEBUGGING ? $exception : $exception->getMessage();
        parent::__construct("{$msg}\n{$additionalMsg}", 0, $exception);
    }
}

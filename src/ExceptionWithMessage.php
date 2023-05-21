<?php

namespace KishukushaReportSupporter;

class ExceptionWithMessage extends \RuntimeException
{
    private string $editedMessage;

    public function __construct($original, $message)
    {
        $this->editedMessage = "{$original}\n{$message}";
    }

    public function __toString(): string
    {
        return $this->editedMessage;
    }
}

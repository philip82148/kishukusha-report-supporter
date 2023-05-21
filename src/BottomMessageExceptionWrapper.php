<?php

namespace KishukushaReportSupporter;

class BottomMessageExceptionWrapper extends \RuntimeException
{
    private string $editedMessage;

    public function __construct($exception, $message)
    {
        $this->editedMessage = "{$exception}\n{$message}";
    }

    public function __toString(): string
    {
        return $this->editedMessage;
    }
}

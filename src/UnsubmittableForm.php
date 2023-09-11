<?php

namespace KishukushaReportSupporter;

abstract class UnsubmittableForm
{
    abstract public static function form(KishukushaReportSupporter $supporter, array $message): void;

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
    {
        return '';
    }
}

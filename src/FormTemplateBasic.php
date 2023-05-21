<?php

namespace KishukushaReportSupporter;

abstract class FormTemplateBasic
{
    public function __construct(protected KishukushaReportSupporter $supporter)
    {
    }
    abstract public function form(array $message): void;
}

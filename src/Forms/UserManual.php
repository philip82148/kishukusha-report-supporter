<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\FormTemplateBasic;

class UserManual extends FormTemplateBasic
{
    public function form(array $message): void
    {
        $this->supporter->pushText(USER_MANUAL);
        $this->supporter->pushImage(USER_MANUAL_PHOTO_URL);
        $this->supporter->resetForm();
    }
}

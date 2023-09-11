<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\UnsubmittableForm;

class UserManual extends UnsubmittableForm
{
    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        $supporter->pushText(USER_MANUAL);
        $supporter->pushImage(USER_MANUAL_PHOTO_URL);
        $supporter->resetForm();
    }
}

<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\UnsubmittableForm;

class UserManual extends UnsubmittableForm
{
    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        $supporter->pushText(USER_MANUAL);
        $url = $supporter->openAccessToImage(USER_MANUAL_PHOTO_FILENAME);
        $supporter->pushImage($url);
        $supporter->resetForm();
    }
}

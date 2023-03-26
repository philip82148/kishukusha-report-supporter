<?php

require_once __DIR__ . '/includes.php';

class UserManual extends FormTemplateBasic
{
    public function form(array $message): void
    {
        $this->supporter->pushMessage(USER_MANUAL);
        $this->supporter->pushMessage(USER_MANUAL_PHOTO_URL, false, 'image');
        $this->supporter->resetForm();
    }
}

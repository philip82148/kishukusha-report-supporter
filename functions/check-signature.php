<?php

require_once __DIR__ . '/../includes.php';

function checkSignature(string $requestBody): bool
{
    $hash = hash_hmac('sha256', $requestBody, CHANNEL_SECRET, true);
    $signature = base64_encode($hash);

    return isset($_SERVER['HTTP_X_LINE_SIGNATURE']) && $signature === $_SERVER['HTTP_X_LINE_SIGNATURE'];
}

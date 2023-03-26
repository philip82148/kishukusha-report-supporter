<?php

require_once __DIR__ . '/../includes.php';

function stringToDate(string $string): int | false
{
    $string = toHalfWidth($string);
    $matches = [];
    if (preg_match('/(?<year>(\d{4})?)\/?(?<month>\d{1,2})\/?(?<date>\d{1,2})/', $string, $matches) === 0)
        return false;

    if ($matches['year'] === '') {
        // 年の入力なし
        $time = strtotime(date('Y') . "/{$matches['month']}/{$matches['date']}");
    } else {
        $time = strtotime("{$matches['year']}/{$matches['month']}/{$matches['date']}");
    }

    return $time;
}

function stringToTime(string $string, string $date = '2022/1/1'): int | false
{
    $string = toHalfWidth($string);
    $matches = [];
    if (preg_match('/(?<hours>\d{1,2}):?(?<minutes>\d{1,2})/', $string, $matches) === 0)
        return false;

    // 24時を0時に戻すために一旦
    $time = strtotime("{$matches['hours']}:{$matches['minutes']}");
    if ($time === false) return false;

    $time = strtotime("$date " . date('H:i', $time));
    if ($time === false) return false;

    return $time;
}

function dateToDateStringWithDay(?int $date = null): string
{
    if (!isset($date))
        $date = time();

    $dateString = date('Y/m/d', $date);
    $day = dateToDay($date);

    return "{$dateString}({$day})";
}

function deleteParentheses(string $string): string
{
    return preg_replace('/\(.*\)/', '', $string);
}

function dateToDay(int $date): string
{
    $day = (int)date('w', $date);
    switch ($day) {
        case 0:
            return '日';
        case 1:
            return '月';
        case 2:
            return '火';
        case 3:
            return '水';
        case 4:
            return '木';
        case 5:
            return '金';
        default:
            return '土';
    }
}

function getDateAt0AM(?int $time = null): int|false
{
    if (isset($time))
        return strtotime(date('Y/m/d', $time));
    return strtotime(date('Y/m/d'));
}

function toHalfWidth(string $string): string
{
    return mb_convert_kana($string, 'as');
}

function trimString(string $string): string
{
    return preg_replace('/\A[\x00\s]++|[\x00\s]++\z/u', '', $string);
}

function insertToAssociativeArray(array &$array, int $offset, array $values): void
{
    $array = array_slice($array, 0, $offset, true) + $values + array_slice($array, $offset, null, true);
}

function generateUUID(): string
{
    $chars = str_split('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');

    foreach ($chars as $i => $char) {
        if ($char === 'x')
            $chars[$i] = dechex(random_int(0, 15));
    }

    return implode('', $chars);
}

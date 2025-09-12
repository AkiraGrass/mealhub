<?php

namespace App\Helper;

/**
 * 應用層狀態碼（僅兩種）：
 * - 0000：成功（數值 0）
 * - 9999：失敗
 * 實際 HTTP 狀態碼由對應表決定，未對應則 9999 → 500。
 */
enum Status: int
{
    case SUCCESS = 0;       // 0000 成功
    case FAILURE = 9999;    // 9999 失敗

    public function message(): string
    {
        return match ($this) {
            self::SUCCESS => 'success',
            self::FAILURE => 'failure',
        };
    }

    public static function messageFor(int $code): string
    {
        return self::tryFrom($code)?->message() ?? 'failure';
    }

    public static function codeFor(string $messageKey): ?int
    {
        return match ($messageKey) {
            'success' => self::SUCCESS->value,
            'failure' => self::FAILURE->value,
            default   => null,
        };
    }

    public static function all(): array
    {
        return [
            self::SUCCESS->value => 'success',
            self::FAILURE->value => 'failure',
        ];
    }
}

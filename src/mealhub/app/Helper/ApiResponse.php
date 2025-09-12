<?php

namespace App\Helper;

class ApiResponse
{
    private static function formatStatusString(int $code): string
    {
        // 將數字狀態碼格式化為四位字串（例如 0 -> "0000"）
        $str = (string) $code;
        if ($code === Status::SUCCESS->value) {
            return '0000';
        }
        return str_pad($str, 4, '0', STR_PAD_LEFT);
    }

    private static function decideHttpStatus(int|Status $status, ?string $message): int
    {
        $code = $status instanceof Status ? $status->value : (int) $status;
        if ($code === Status::SUCCESS->value) {
            return 200; // 成功預設 200
        }
        if ($message && ($mapped = HttpStatusMap::for($message))) {
            return $mapped; // 有對應則用對應 HTTP 狀態
        }
        return 500; // 無對應則一律 500
    }

    /**
     * 統一底層回應建構器（單一實作點）。
     */
    private static function respond(int|Status $status, $data = null, ?string $message = null, ?array $errors = null, ?array $meta = null)
    {
        $code    = $status instanceof Status ? $status->value : (int) $status;
        $message = $message ?? ($status instanceof Status ? $status->message() : Status::messageFor($code));
        $http    = self::decideHttpStatus($status, $message);
        $statusStr = self::formatStatusString($code);

        $payload = [
            'status'  => $statusStr,
            'message' => $message,
            'data'    => $data,
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()
            ->json($payload, $http)
            ->header('X-App-Status', $statusStr);
    }

    /**
     * 統一成功回應
     * - status 可傳 int 或 Status enum；預設為 Status::SUCCESS
     * - message 省略時依 status 自動帶入對應訊息鍵
     * - 可附帶 meta（非必要）
     */
    public static function success($data = null, ?string $message = null, int|Status $status = Status::SUCCESS, ?array $meta = null)
    {
        return self::respond($status, $data, $message, null, $meta);
    }

    /**
     * 統一錯誤回應
     * - 可傳 errors 與 meta
     */
    public static function error(int|Status $status = Status::FAILURE, ?string $message = null, $data = null, ?array $errors = null, ?array $meta = null)
    {
        return self::respond($status, $data, $message, $errors, $meta);
    }

    /** 驗證錯誤的糖衣方法 */
    public static function validationError(array $errors, ?string $message = 'validationError', int|Status $status = Status::FAILURE)
    {
        return self::error($status, $message, null, $errors);
    }
}

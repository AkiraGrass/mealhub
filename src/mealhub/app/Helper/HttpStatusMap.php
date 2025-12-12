<?php

namespace App\Helper;

class HttpStatusMap
{
    // message key -> HTTP status code
    private const MAP = [
        // 成功場景
        'success' => 200,

        // 驗證/權限/資源
        'validationError'        => 422,
        'unauthorized'           => 401,
        'forbidden'              => 403,
        'notFound'               => 404,

        // Auth 相關常見訊息
        'invalidCredentials'     => 401,
        'invalidRefreshToken'    => 401,
        'refreshTokenReplayed'   => 401,
        'cannotModifyTimeslotActive' => 409,
        'cannotModifyTimeslotsActive' => 409,
        'missingBearerToken'     => 401,
        'invalidToken'           => 401,
        'tokenRevoked'           => 401,
        'tokenExpired'           => 401,

        // 伺服器錯誤類
        'serverError'            => 500,
        'failure'                => 500,

        // 其他常見 HTTP 狀態對應
        'badRequest'             => 400,
        'methodNotAllowed'       => 405,
        'tooManyRequests'        => 429,
        'conflict'               => 409,
    ];

    public static function for(string $messageKey): ?int
    {
        return self::MAP[$messageKey] ?? null;
    }
}

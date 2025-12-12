<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HttpAccessLog
{
    /**
     * 處理請求並輸出結構化存取日誌（JSON）。
     * - 欄位：request, response, httpStatus, logLevel, message, url
     * - 會遮罩敏感欄位（password 相關）
     */
    public function handle(Request $request, Closure $next)
    {
        $startedAt = microtime(true);

        $response = $next($request);

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        $status     = $response->getStatusCode();
        $url        = $request->fullUrl();

        $level = 'info';
        if ($status >= 500) $level = 'error';
        elseif ($status >= 400) $level = 'warning';

        $body = $this->mask($request->all());

        $message = null;
        $respSummary = null;
        try {
            $json = json_decode($response->getContent(), true);
            if (is_array($json)) {
                $message = $json['message'] ?? null;
                $respSummary = [
                    'status'  => $json['status'] ?? null,
                    'message' => $message,
                ];
            }
        } catch (\Throwable $e) {
            // ignore non-JSON responses
        }

        $record = [
            'url'        => $url,
            'httpStatus' => $status,
            'logLevel'   => strtoupper($level),
            'message'    => $message ?? 'HTTP access',
            'request'    => [
                'method'  => $request->method(),
                'body'    => $body,
                'ip'      => $request->ip(),
                'ua'      => $request->header('User-Agent'),
                'timeMs'  => $durationMs,
            ],
            'response'   => $respSummary,
        ];

        Log::channel(config('logging.default'))->{$level}('http_access', $record);

        return $response;
    }

    private function mask($value)
    {
        $keys = [
            'password', 'password_confirmation', 'current_password',
            'new_password', 'confirm_password'
        ];

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (in_array(strtolower((string) $k), $keys, true)) {
                    $out[$k] = '***';
                } else {
                    $out[$k] = $this->mask($v);
                }
            }
            return $out;
        }
        return $value;
    }
}


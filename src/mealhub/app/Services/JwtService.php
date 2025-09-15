<?php

namespace App\Services;

use Exception;
use OpenSSLAsymmetricKey;

/**
 * JWT 服務
 * - 預設演算法 RS256；亦支援 HS256（共用祕密）。
 * - 金鑰載入支援：純文字 PEM、Base64、檔案路徑，並支援私鑰密碼。
 * - 加入 kid（可選）與時鐘偏移設定。
 */
class JwtService
{
    private readonly string $algo;                 // 簽章演算法：RS256 / HS256
    private readonly ?string $privateKey;          // 私鑰內容（若使用 RS256）
    private readonly ?string $publicKey;           // 公鑰內容（若使用 RS256）
    private readonly ?string $privatePassphrase;   // 私鑰密碼（若有）
    private readonly ?string $kid;                 // JWT Header: kid（可選）
    private readonly ?string $hsSecret;            // HS256 共用祕密

    public function __construct() {
        // 簽章演算法（預設 RS256，可由環境或 config/jwt.php 控制）
        $this->algo = config('jwt.algo', env('JWT_ALGO', 'RS256'));

        // 金鑰載入（PEM / Base64 / 路徑）
        $this->privateKey = $this->loadKey('JWT_PRIVATE_KEY', 'JWT_PRIVATE_KEY_B64', 'JWT_PRIVATE_KEY_PATH');
        $this->publicKey  = $this->loadKey('JWT_PUBLIC_KEY',  'JWT_PUBLIC_KEY_B64',  'JWT_PUBLIC_KEY_PATH');

        // 私鑰密碼（可選）
        $this->privatePassphrase = env('JWT_PRIVATE_KEY_PASSPHRASE')
            ?: env('JWT_PASSPHRASE')
            ?: null;

        // kid（可選）
        $this->kid = env('JWT_KID') ?: null;

        // HS256 共用祕密（可選）
        $this->hsSecret = env('JWT_SECRET') ?: null;
    }

    /**
     * 發行存取權杖（Access Token）
     */
    public function issueAccessToken(string $userId, array $extra = []): string
    {
        $now = time();
        $ttl = (int) env('JWT_ACCESS_TTL_MIN', 15);

        $payload = array_merge([
            'sub' => $userId,
            'jti' => $this->uuid(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl * 60,
        ], $extra);

        return $this->encode($payload);
    }

    /**
     * 產生 JWT（簽章）
     */
    public function encode(array $payload): string
    {
        $header  = ['typ' => 'JWT', 'alg' => $this->algo];
        if ($this->kid) {
            $header['kid'] = $this->kid;
        }
        $segments = [
            $this->b64(json_encode($header,  JSON_UNESCAPED_SLASHES)),
            $this->b64(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $signature = '';

        if ($this->algo === 'RS256') {
            $key = $this->getPrivateKeyResource();
            openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);
        } elseif ($this->algo === 'HS256') {
            if (!$this->hsSecret) {
                throw new Exception('JWT secret not configured for HS256');
            }
            $signature = hash_hmac('sha256', $signingInput, $this->hsSecret, true);
        } else {
            throw new Exception('Unsupported JWT algo');
        }

        $segments[] = $this->b64($signature);
        return implode('.', $segments);
    }

    /**
     * 驗證並解析 JWT
     */
    public function decode(string $jwt): array
    {
        [$h, $p, $s] = explode('.', $jwt);
        $headerJson  = $this->ub64($h);
        $payloadJson = $this->ub64($p);
        $header  = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);
        $sig     = $this->ub64($s);

        if (($header['alg'] ?? '') !== $this->algo) {
            throw new Exception('Invalid alg');
        }

        // 驗簽
        if ($this->algo === 'RS256') {
            if (!$this->publicKey) {
                throw new Exception('JWT public key not configured');
            }
            $pubKey = openssl_pkey_get_public($this->publicKey);
            if ($pubKey === false) {
                throw new Exception('Invalid public key content');
            }
            $ok = openssl_verify("$h.$p", $sig, $pubKey, OPENSSL_ALGO_SHA256);
            if ($ok !== 1) {
                throw new Exception('Invalid signature');
            }
        } elseif ($this->algo === 'HS256') {
            if (!$this->hsSecret) {
                throw new Exception('JWT secret not configured for HS256');
            }
            $expected = hash_hmac('sha256', "$h.$p", $this->hsSecret, true);
            if (!hash_equals($expected, $sig)) {
                throw new Exception('Invalid signature');
            }
        }

        // 時間檢查（允許輕微時鐘偏移）
        $now   = time();
        $skew  = (int) env('JWT_CLOCK_SKEW_SEC', 30);
        $nbf   = (int) ($payload['nbf'] ?? 0);
        $exp   = (int) ($payload['exp'] ?? 0);
        if ($nbf > 0 && $nbf - $skew > $now) {
            throw new Exception('Token not yet valid');
        }
        if ($exp > 0 && $exp + $skew < $now) {
            throw new Exception('Token expired or not yet valid');
        }

        return $payload;
    }

    // ===== Helper =====
    private function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
    private function ub64(string $s): string
    {
        // base64url → base64，補 '=' padding
        $b64 = strtr($s, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        return (string) base64_decode($b64);
    }
    public function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    /**
     * 以「環境變數名稱 / Base64 變數 / 路徑變數」載入金鑰內容。
     * - 支援在單行中以 "\n" 代表換行的 PEM。
     */
    private function loadKey(string $rawEnv, string $b64Env, string $pathEnv): ?string
    {
        $key = env($rawEnv);
        if (is_string($key) && $key !== '') {
            if (str_contains($key, '\\n')) {
                $key = str_replace('\\n', PHP_EOL, $key);
            }
            return $key;
        }

        $b64 = env($b64Env);
        if (is_string($b64) && $b64 !== '') {
            $decoded = base64_decode($b64, true);
            return $decoded !== false ? $decoded : null;
        }

        $path = env($pathEnv);
        if (is_string($path) && $path !== '' && is_readable($path)) {
            $content = @file_get_contents($path);
            return $content !== false ? $content : null;
        }
        return null;
    }

    /**
     * 取得 openssl 可接受的私鑰（帶密碼則回傳 key resource）。
     */
    private function getPrivateKeyResource(): OpenSSLAsymmetricKey|resource
    {
        if (!$this->privateKey) {
            throw new Exception('JWT private key not configured');
        }
        if ($this->privatePassphrase !== null && $this->privatePassphrase !== '') {
            $key = openssl_pkey_get_private($this->privateKey, $this->privatePassphrase);
            if ($key === false) {
                throw new Exception('Invalid private key or passphrase');
            }
            return $key;
        }
        // 嘗試解析為 key resource；若失敗，openssl_sign 仍可接受 PEM 字串
        return openssl_pkey_get_private($this->privateKey) ?: $this->privateKey;
    }
}

<?php

namespace App\Services;

use Exception;

class JwtService
{
    private readonly string $algo;
    private readonly string $privateKey;
    private readonly string $publicKey;

    public function __construct() {
        $this->algo       = config('jwt.algo', 'RS256');
        $this->privateKey = env('JWT_PRIVATE_KEY');
        $this->publicKey  = env('JWT_PUBLIC_KEY');
    }

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

    public function encode(array $payload): string
    {
        $header  = ['typ' => 'JWT', 'alg' => $this->algo];
        $segments = [
            $this->b64(json_encode($header,  JSON_UNESCAPED_SLASHES)),
            $this->b64(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $signature = '';

        if ($this->algo === 'RS256') {
            openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        } else {
            throw new Exception('Unsupported JWT algo');
        }

        $segments[] = $this->b64($signature);
        return implode('.', $segments);
    }

    public function decode(string $jwt): array
    {
        [$h, $p, $s] = explode('.', $jwt);
        $header  = json_decode($this->ub64($h), true);
        $payload = json_decode($this->ub64($p), true);
        $sig     = $this->ub64($s);

        if (($header['alg'] ?? '') !== $this->algo) {
            throw new Exception('Invalid alg');
        }

        $ok = openssl_verify("$h.$p", $sig, $this->publicKey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new Exception('Invalid signature');
        }

        $now = time();
        if (($payload['nbf'] ?? 0) > $now || ($payload['exp'] ?? 0) < $now) {
            throw new Exception('Token expired or not yet valid');
        }

        return $payload;
    }

    private function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
    private function ub64(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/'));
    }
    public function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}

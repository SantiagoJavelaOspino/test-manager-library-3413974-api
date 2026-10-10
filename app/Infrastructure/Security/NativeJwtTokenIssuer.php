<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Port\TokenIssuerInterface;
use App\Domain\Exception\InvalidCredentialsException;
use App\Domain\ValueObject\Role;
use App\Domain\ValueObject\Uuid;

final class NativeJwtTokenIssuer implements TokenIssuerInterface
{
    private string $secret;
    private int $ttlSeconds;

    public function __construct(string $secret, int $ttlSeconds = 28800)
    {
        $this->secret = $secret;
        $this->ttlSeconds = $ttlSeconds;
    }

    public function issue(Uuid $userId, Role $role): array
    {
        $now = time();
        $expiresAt = $now + $this->ttlSeconds;

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = [
            'sub' => $userId->value(),
            'role' => $role->value(),
            'iat' => $now,
            'exp' => $expiresAt,
        ];

        $base64Header = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $base64Payload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $this->secret, true);
        $base64Signature = $this->base64UrlEncode($signature);

        $token = "{$base64Header}.{$base64Payload}.{$base64Signature}";

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->ttlSeconds,
            'role' => $role->value(),
        ];
    }

    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidCredentialsException('Token inválido.');
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;
        $expectedSignature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $this->secret, true);

        if (!hash_equals($expectedSignature, $this->base64UrlDecode($base64Signature))) {
            throw new InvalidCredentialsException('Firma de token inválida.');
        }

        $payloadJson = $this->base64UrlDecode($base64Payload);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload) || !isset($payload['sub'], $payload['role'], $payload['exp'])) {
            throw new InvalidCredentialsException('Contenido de token inválido.');
        }

        if (time() >= (int)$payload['exp']) {
            throw new InvalidCredentialsException('Token expirado.');
        }

        return [
            'user_id' => (string)$payload['sub'],
            'role' => (string)$payload['role'],
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

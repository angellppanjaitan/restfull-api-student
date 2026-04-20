<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class JwtService
{
    public function issueToken(User $user): array
    {
        $now = Carbon::now();
        $ttl = (int) config('jwt.ttl', 60);
        $expiresAt = $now->copy()->addMinutes($ttl);
        $jti = (string) Str::uuid();

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => config('jwt.issuer'),
            'sub' => (string) $user->getKey(),
            'iat' => $now->timestamp,
            'exp' => $expiresAt->timestamp,
            'jti' => $jti,
            'name' => $user->name,
            'email' => $user->email,
        ];

        return [
            'access_token' => $this->encode($header, $payload),
            'token_type' => 'Bearer',
            'expires_in' => $ttl * 60,
            'expires_at' => $expiresAt->toDateTimeString(),
            'jti' => $jti,
        ];
    }

    public function decodeToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format.');
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        $header = $this->decodeSegment($encodedHeader);
        $payload = $this->decodeSegment($encodedPayload);

        if (($header['alg'] ?? null) !== 'HS256') {
            throw new RuntimeException('Unsupported token algorithm.');
        }

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret(), true)
        );

        if (! hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        if (($payload['exp'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('Token has expired.');
        }

        return $payload;
    }

    public function blacklist(string $jti, ?int $ttlSeconds = null): void
    {
        $ttlSeconds ??= ((int) config('jwt.ttl', 60)) * 60;

        Cache::put($this->blacklistKey($jti), true, now()->addSeconds($ttlSeconds));
    }

    public function isBlacklisted(string $jti): bool
    {
        return Cache::has($this->blacklistKey($jti));
    }

    private function encode(array $header, array $payload): string
    {
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret(), true)
        );

        return $encodedHeader.'.'.$encodedPayload.'.'.$signature;
    }

    private function decodeSegment(string $segment): array
    {
        $decoded = json_decode($this->base64UrlDecode($segment), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid token payload.');
        }

        return $decoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');
        $secret = str_starts_with($secret, 'base64:')
            ? base64_decode(substr($secret, 7)) ?: ''
            : $secret;

        if ($secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        return $secret;
    }

    private function blacklistKey(string $jti): string
    {
        return 'jwt:blacklist:'.$jti;
    }
}

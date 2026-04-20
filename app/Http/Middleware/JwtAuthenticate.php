<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private readonly JwtService $jwtService)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'Unauthorized. Bearer token is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->jwtService->decodeToken($token);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Unauthorized.',
                'error' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (isset($payload['jti']) && $this->jwtService->isBlacklisted((string) $payload['jti'])) {
            return response()->json([
                'message' => 'Unauthorized. Token has been revoked.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $payload['sub'] ?? null;
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user) {
            return response()->json([
                'message' => 'Unauthorized. User not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn (): User => $user);
        $request->attributes->set('jwt_payload', $payload);

        return $next($request);
    }
}

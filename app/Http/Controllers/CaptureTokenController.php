<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaptureTokenRequest;
use App\Models\CaptureToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CaptureTokenController extends Controller
{
    public function store(StoreCaptureTokenRequest $request): JsonResponse
    {
        $plain = 'ct_'.Str::random(64);
        $token = CaptureToken::create($request->validated() + [
            'user_id' => $request->user()->id, 'token_hash' => hash('sha256', $plain),
            'scopes' => ['capture:write'], 'rate_limit_per_hour' => 120,
        ]);

        return response()->json(['token' => $plain, 'device' => $token], 201)->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $record = CaptureToken::forUser($request->user())->findOrFail($token);
        if (! $record->revoked_at) {
            $record->update(['revoked_at' => now()]);
        }

        return response()->json(['device' => $record])->header('Cache-Control', 'no-store, private');
    }
}

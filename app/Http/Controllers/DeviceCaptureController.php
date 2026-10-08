<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceCaptureRequest;
use App\Models\Capture;
use App\Services\CaptureService;
use App\Services\CaptureWaiter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class DeviceCaptureController extends Controller
{
    public function __invoke(StoreDeviceCaptureRequest $request, CaptureService $service, CaptureWaiter $waiter): JsonResponse
    {
        $deadline = $waiter->time() + 8;
        $data = $request->validated();
        $token = $request->attributes->get('capture_token');
        $identity = $token ? 'token:'.$token->id : 'session:'.$request->user()->id;
        $timestamp = isset($data['captured_at']) ? CarbonImmutable::parse($data['captured_at'])->utc()->startOfSecond()->toIso8601String() : null;
        $key = $data['request_key'] ?? ($timestamp ? $timestamp.':'.hash('sha256', $data['text']) : Str::uuid()->toString());
        $data['request_key'] = Uuid::uuid5(Uuid::NAMESPACE_URL, 'chart:capture:'.$identity.':'.$key)->toString();
        $existing = Capture::forUser($request->user())->where('request_key', $data['request_key'])->first();
        $data['captured_at'] = $timestamp ?? $existing?->client_captured_at->toIso8601String() ?? now()->toIso8601String();
        $data['capture_token_id'] = $token?->id;
        $data['device_label'] = $data['device_label'] ?? $token?->device_name ?? $token?->label;
        $capture = $service->receive($request->user(), $data);
        $token?->update(['last_used_at' => now()]);
        if ($request->boolean('wait')) {
            $capture = $waiter->wait($capture, $deadline);
        }

        return response()->json($service->confirmation($capture), 202);
    }
}

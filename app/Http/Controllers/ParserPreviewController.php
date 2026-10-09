<?php

namespace App\Http\Controllers;

use App\Exceptions\CaptureParserFailure;
use App\Http\Requests\PreviewCaptureRequest;
use App\Models\Capture;
use App\Services\CaptureActions;
use App\Services\CaptureContext;
use App\Services\CaptureTranscript;
use App\Services\LocalDate;
use App\Services\OpenAICaptureClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class ParserPreviewController extends Controller
{
    public function __invoke(PreviewCaptureRequest $request): JsonResponse
    {
        $data = $request->validated();
        $capture = new Capture([
            'user_id' => $request->user()->id, 'raw_text' => $data['text'], 'source' => 'in_app',
            'mode' => count(preg_split('/\s+/u', trim($data['text']))) > 60 ? 'dump' : 'single',
            'client_captured_at' => CarbonImmutable::parse($data['captured_at'])->utc(), 'timezone' => app(LocalDate::class)->timezone($request->user()),
        ]);
        try {
            $result = app(OpenAICaptureClient::class)->generate($data['text'], app(CaptureContext::class)->forCapture($capture));
            $items = app(CaptureTranscript::class)->items($data['text'], $result['parsed']['actions']);
            $result['items'] = array_map(fn ($action) => app(CaptureActions::class)->preview($request->user(), $data['text'], $action, $capture), $items);
            unset($result['parsed']);

            return response()->json($result);
        } catch (CaptureParserFailure $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason, 'usage' => $exception->usage], 422);
        }
    }
}

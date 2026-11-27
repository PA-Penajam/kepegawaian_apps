<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sisukma\InboundEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SisukmaWebhookController extends Controller
{
    /**
     * Menerima event outbox yang dikirim oleh SiSukma secara idempoten.
     */
    public function __invoke(Request $request, InboundEventProcessor $processor): JsonResponse
    {
        $headerEventId = (string) $request->header('X-Event-Id');

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json([
                'message' => 'Payload JSON tidak valid.',
            ], 422);
        }

        $validator = Validator::make($payload, [
            'event_id' => ['nullable', 'uuid'],
            'event_type' => ['required', 'string', 'max:100'],
            'occurred_at' => ['nullable', 'date'],
            'data' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Format event SiSukma tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (isset($payload['event_id']) && strtolower((string) $payload['event_id']) !== strtolower($headerEventId)) {
            return response()->json([
                'message' => 'Field event_id pada body harus cocok dengan header X-Event-Id.',
            ], 422);
        }

        $payload['event_id'] = $headerEventId;
        $result = $processor->process($headerEventId, $payload);

        return response()->json([
            'accepted' => true,
            'duplicate' => $result['duplicate'],
            'event_id' => $result['event']->event_id,
        ], 202);
    }
}

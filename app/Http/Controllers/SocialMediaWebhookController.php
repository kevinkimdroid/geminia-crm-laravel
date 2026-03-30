<?php

namespace App\Http\Controllers;

use App\Services\SocialMediaWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SocialMediaWebhookController extends Controller
{
    public function __construct(
        private SocialMediaWebhookService $webhooks
    ) {}

    /**
     * Meta (Facebook Page + Instagram) — same URL in developer console for both products.
     * GET: webhook verification. POST: event delivery (signed with X-Hub-Signature-256).
     */
    public function meta(Request $request): Response|\Illuminate\Http\JsonResponse|string
    {
        if ($request->isMethod('GET')) {
            // PHP converts dotted query keys: hub.mode → hub_mode
            $challenge = $this->webhooks->verifyMetaChallenge(
                $request->query('hub_mode', $request->query('hub.mode')),
                $request->query('hub_verify_token', $request->query('hub.verify_token')),
                $request->query('hub_challenge', $request->query('hub.challenge'))
            );
            if ($challenge === null || $challenge === '') {
                abort(403, 'Invalid verify token');
            }
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        $raw = $request->getContent();
        if (!$this->webhooks->metaSignatureValid($raw, $request->header('X-Hub-Signature-256'))) {
            Log::warning('Social webhook: invalid Meta signature');
            abort(403, 'Invalid signature');
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return response()->json(['ok' => false], 400);
        }

        try {
            $stored = $this->webhooks->processMetaPayload($payload);
            return response()->json(['ok' => true, 'stored' => $stored]);
        } catch (\Throwable $e) {
            Log::error('Social webhook meta: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['ok' => false], 500);
        }
    }

    /**
     * Optional generic ingest for tests or middleware forwarding (Bearer token).
     */
    public function ingest(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = config('services.social_webhook.ingest_bearer_token');
        if (app()->environment('production') && ($token === null || $token === '')) {
            abort(503, 'Set SOCIAL_WEBHOOK_INGEST_TOKEN for this endpoint');
        }
        if ($token !== null && $token !== '' && $request->bearerToken() !== $token) {
            abort(401, 'Unauthorized');
        }

        $data = $request->json()->all();
        if ($data === []) {
            $data = $request->all();
        }

        $ok = $this->webhooks->ingestManualPayload($data);
        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }
}

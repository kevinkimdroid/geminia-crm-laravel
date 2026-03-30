<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialInteraction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialMediaWebhookService
{
    public function verifyMetaChallenge(?string $mode, ?string $token, ?string $challenge): ?string
    {
        $expected = config('services.social_webhook.meta_verify_token');
        if (!$expected || $mode !== 'subscribe' || $token !== $expected) {
            return null;
        }
        return $challenge;
    }

    public function metaSignatureValid(string $rawBody, ?string $signatureHeader): bool
    {
        if (config('services.social_webhook.skip_signature_verify')) {
            return true;
        }

        $secret = config('services.social_webhook.meta_app_secret');
        if (!$secret || !$signatureHeader || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expectedHash = substr($signatureHeader, 7);
        $computed = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expectedHash, $computed);
    }

    /**
     * Process Meta webhook JSON (object "page" or "instagram"). Returns number of interactions stored.
     */
    public function processMetaPayload(array $payload): int
    {
        $object = $payload['object'] ?? '';
        if (!in_array($object, ['page', 'instagram'], true)) {
            return 0;
        }

        $platform = $object === 'instagram' ? 'instagram' : 'facebook';
        $stored = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            $recipientId = isset($entry['id']) ? (string) $entry['id'] : null;
            $account = $this->resolveSocialAccount($platform, $recipientId);
            if (!$account) {
                Log::notice("Social webhook: no SocialAccount for {$platform} recipient {$recipientId}");
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? '';
                $value = $change['value'] ?? [];

                if ($field === 'feed' && ($value['item'] ?? '') === 'comment') {
                    if ($this->storeCommentInteraction($account, $platform, $value)) {
                        $stored++;
                    }
                    continue;
                }

                if ($field === 'comments' || $field === 'mentions') {
                    if ($this->storeCommentInteraction($account, $platform, $value)) {
                        $stored++;
                    }
                }
            }

            foreach ($entry['messaging'] ?? [] as $msg) {
                if ($this->storeMessagingDm($account, $platform, $msg)) {
                    $stored++;
                }
            }
        }

        return $stored;
    }

    /**
     * Manual / partner ingest (JSON body) when platforms send custom payloads.
     *
     * @param  array<string, mixed>  $data
     */
    public function ingestManualPayload(array $data): bool
    {
        $platform = strtolower((string) ($data['platform'] ?? ''));
        if (!in_array($platform, SocialAccount::platforms(), true)) {
            return false;
        }

        $account = SocialAccount::where('platform', $platform)->first();
        if (!$account) {
            Log::notice("Social webhook manual: no account for {$platform}");
            return false;
        }

        $type = $data['type'] ?? SocialInteraction::TYPE_COMMENT;
        $externalId = $data['external_id'] ?? null;
        if (!$externalId) {
            $externalId = 'manual-' . sha1(json_encode($data) . microtime(true));
        }

        SocialInteraction::updateOrCreate(
            [
                'social_account_id' => $account->id,
                'platform' => $platform,
                'external_id' => $externalId,
            ],
            [
                'post_external_id' => $data['post_external_id'] ?? null,
                'type' => is_string($type) ? $type : SocialInteraction::TYPE_COMMENT,
                'author_name' => $data['author_name'] ?? null,
                'author_handle' => $data['author_handle'] ?? null,
                'author_email' => $data['author_email'] ?? null,
                'author_phone' => $data['author_phone'] ?? null,
                'content' => $data['content'] ?? null,
                'post_url' => $data['post_url'] ?? null,
                'metadata' => $data['metadata'] ?? $data,
                'interaction_at' => isset($data['interaction_at'])
                    ? \Carbon\Carbon::parse($data['interaction_at'])
                    : now(),
            ]
        );

        return true;
    }

    public function resolveSocialAccount(string $platform, ?string $recipientId): ?SocialAccount
    {
        if ($recipientId) {
            $byPage = SocialAccount::where('platform', $platform)
                ->where(function ($q) use ($recipientId) {
                    $q->where('account_id', $recipientId)
                        ->orWhere('metadata->page_id', $recipientId)
                        ->orWhere('metadata->instagram_business_account_id', $recipientId);
                })
                ->first();
            if ($byPage) {
                return $byPage;
            }
        }

        return SocialAccount::where('platform', $platform)->orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function storeCommentInteraction(SocialAccount $account, string $platform, array $value): bool
    {
        $media = is_array($value['media'] ?? null) ? $value['media'] : [];
        $commentId = $value['comment_id'] ?? $value['id'] ?? null;
        $postId = $value['post_id'] ?? $value['media_id'] ?? ($media['id'] ?? null);
        if (!$commentId && !$postId) {
            return false;
        }

        $externalId = $commentId ? (string) $commentId : ('post-' . $postId);
        $from = $value['from'] ?? [];
        $authorName = is_array($from) ? ($from['name'] ?? $from['username'] ?? null) : null;
        $authorHandle = is_array($from) ? ($from['username'] ?? null) : null;

        $message = $value['message'] ?? $value['text'] ?? null;
        $permalink = is_array($value['post'] ?? null)
            ? ($value['post']['permalink_url'] ?? null)
            : ($value['permalink_url'] ?? null);

        $created = $value['created_time'] ?? $value['timestamp'] ?? null;
        $interactionAt = is_numeric($created)
            ? \Carbon\Carbon::createFromTimestamp((int) $created)
            : ($created ? \Carbon\Carbon::parse((string) $created) : now());

        $parentId = $value['parent_id'] ?? null;
        $type = ($parentId && $postId && (string) $parentId !== (string) $postId)
            ? SocialInteraction::TYPE_REPLY
            : SocialInteraction::TYPE_COMMENT;

        $metadata = ['raw' => $value, 'webhook' => 'meta'];

        $interaction = SocialInteraction::updateOrCreate(
            [
                'social_account_id' => $account->id,
                'platform' => $platform,
                'external_id' => $externalId,
            ],
            [
                'post_external_id' => $postId ? (string) $postId : null,
                'type' => $type,
                'author_name' => $authorName,
                'author_handle' => $authorHandle,
                'content' => $message,
                'post_url' => $permalink,
                'metadata' => $metadata,
                'interaction_at' => $interactionAt,
            ]
        );

        if (config('services.social_webhook.enrich_with_graph') && $commentId && $account->access_token) {
            $this->enrichFromGraph($interaction, $account, (string) $commentId);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $msg
     */
    private function storeMessagingDm(SocialAccount $account, string $platform, array $msg): bool
    {
        if (($msg['message'] ?? null) === null && ($msg['postback'] ?? null) === null) {
            return false;
        }

        $mid = $msg['message']['mid'] ?? $msg['sender']['id'] ?? null;
        if (!$mid) {
            return false;
        }

        $text = $msg['message']['text'] ?? json_encode($msg['postback'] ?? []);

        SocialInteraction::updateOrCreate(
            [
                'social_account_id' => $account->id,
                'platform' => $platform,
                'external_id' => (string) $mid,
            ],
            [
                'post_external_id' => null,
                'type' => SocialInteraction::TYPE_DM,
                'author_name' => null,
                'author_handle' => isset($msg['sender']['id']) ? (string) $msg['sender']['id'] : null,
                'content' => is_string($text) ? $text : null,
                'post_url' => null,
                'metadata' => ['raw' => $msg, 'webhook' => 'meta'],
                'interaction_at' => now(),
            ]
        );

        return true;
    }

    private function enrichFromGraph(SocialInteraction $interaction, SocialAccount $account, string $commentId): void
    {
        try {
            $response = Http::withToken($account->access_token)
                ->timeout(5)
                ->get("https://graph.facebook.com/v18.0/{$commentId}", [
                    'fields' => 'id,from,message,permalink_url,created_time,parent{id}',
                ]);
            if (!$response->successful()) {
                return;
            }
            $j = $response->json();
            $meta = $interaction->metadata ?? [];
            $meta['graph'] = $j;
            $interaction->fill([
                'author_name' => $interaction->author_name ?? data_get($j, 'from.name'),
                'content' => $interaction->content ?? ($j['message'] ?? null),
                'post_url' => $interaction->post_url ?? ($j['permalink_url'] ?? null),
                'metadata' => $meta,
            ]);
            $interaction->save();
        } catch (\Throwable $e) {
            Log::debug('Social webhook graph enrich failed: ' . $e->getMessage());
        }
    }
}

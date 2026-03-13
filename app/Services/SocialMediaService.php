<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialInteraction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialMediaService
{
    /**
     * Get aggregated metrics from all connected social accounts.
     *
     * @return array{total_audience: int|string, mentions: int|string, engagement: int|string}
     */
    public function getMetrics(): array
    {
        $accounts = SocialAccount::all();
        $totalAudience = 0;
        $mentions = 0;
        $engagement = 0;

        foreach ($accounts as $account) {
            try {
                switch ($account->platform) {
                    case 'twitter':
                        $stats = $this->getTwitterMetrics($account);
                        break;
                    case 'youtube':
                        $stats = $this->getYouTubeMetrics($account);
                        break;
                    case 'tiktok':
                        $stats = $this->getTikTokMetrics($account);
                        break;
                    case 'facebook':
                        $stats = $this->getFacebookMetrics($account);
                        break;
                    case 'instagram':
                        $stats = $this->getInstagramMetrics($account);
                        break;
                    default:
                        $stats = null;
                }

                if ($stats) {
                    $totalAudience += $stats['audience'] ?? 0;
                    $mentions += $stats['mentions'] ?? 0;
                    $engagement += $stats['engagement'] ?? 0;
                }
            } catch (\Throwable $e) {
                Log::warning("Social media metrics failed for {$account->platform}: {$e->getMessage()}");
            }
        }

        return [
            'total_audience' => $totalAudience > 0 ? $this->formatNumber($totalAudience) : '—',
            'mentions' => $mentions > 0 ? $this->formatNumber($mentions) : '—',
            'engagement' => $engagement > 0 ? $this->formatNumber($engagement) : '—',
        ];
    }

    /**
     * Get per-platform statistics for connected accounts.
     *
     * @return array<int, array{platform: string, account_name: string, followers: string, engagement: string, posts: int|null, raw: array}>
     */
    public function getPlatformStats(): array
    {
        $accounts = SocialAccount::all();
        $stats = [];

        foreach ($accounts as $account) {
            try {
                switch ($account->platform) {
                    case 'twitter':
                        $data = $this->getTwitterMetrics($account);
                        break;
                    case 'youtube':
                        $data = $this->getYouTubeMetrics($account);
                        break;
                    case 'tiktok':
                        $data = $this->getTikTokMetrics($account);
                        break;
                    case 'facebook':
                        $data = $this->getFacebookMetrics($account);
                        break;
                    case 'instagram':
                        $data = $this->getInstagramMetrics($account);
                        break;
                    default:
                        $data = null;
                }

                if ($data) {
                    $stats[] = [
                        'platform' => $account->platform,
                        'account_name' => $account->account_name ?? ucfirst($account->platform),
                        'followers' => $this->formatNumber($data['audience'] ?? 0),
                        'engagement' => $this->formatNumber($data['engagement'] ?? 0),
                        'posts' => $data['posts'] ?? null,
                        'raw' => $data,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning("Social platform stats failed for {$account->platform}: {$e->getMessage()}");
            }
        }

        return $stats;
    }

    /**
     * Get recent posts from all connected platforms.
     *
     * @param  string|null  $platform  Filter by platform (facebook, instagram, twitter, youtube, tiktok)
     * @param  string  $sort  Sort by: date_desc, date_asc, engagement_desc, engagement_asc
     * @param  int  $limit  Max posts to return
     * @return array<int, array{platform: string, account_name: string, content: string, url: string|null, created_at: string|null, engagement: string|null, engagement_raw: int}>
     */
    public function getRecentPosts(?string $platform = null, string $sort = 'date_desc', int $limit = 30): array
    {
        $posts = [];
        $accounts = SocialAccount::all();
        if ($platform) {
            $accounts = $accounts->where('platform', $platform);
        }

        foreach ($accounts as $account) {
            try {
                switch ($account->platform) {
                    case 'twitter':
                        $platformPosts = $this->getTwitterPosts($account);
                        break;
                    case 'youtube':
                        $platformPosts = $this->getYouTubePosts($account);
                        break;
                    case 'tiktok':
                        $platformPosts = $this->getTikTokPosts($account);
                        break;
                    default:
                        $platformPosts = [];
                }
                $posts = array_merge($posts, $platformPosts);
            } catch (\Throwable $e) {
                Log::warning("Social media posts failed for {$account->platform}: {$e->getMessage()}");
            }
        }

        $sortFn = match ($sort) {
            'date_asc' => fn ($a, $b) => strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0'),
            'engagement_desc' => fn ($a, $b) => ($b['engagement_raw'] ?? 0) - ($a['engagement_raw'] ?? 0),
            'engagement_asc' => fn ($a, $b) => ($a['engagement_raw'] ?? 0) - ($b['engagement_raw'] ?? 0),
            default => fn ($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'),
        };
        usort($posts, $sortFn);

        return array_slice($posts, 0, $limit);
    }

    /**
     * Get interactions (comments, replies) with sort and filter.
     *
     * @param  string|null  $platform  Filter by platform
     * @param  string|null  $type  Filter by type (comment, reply, etc.)
     * @param  string  $sort  Sort by: date_desc, date_asc
     * @param  int  $limit  Max interactions to return
     * @return \Illuminate\Database\Eloquent\Collection<int, SocialInteraction>
     */
    public function getInteractions(?string $platform = null, ?string $type = null, string $sort = 'date_desc', int $limit = 50)
    {
        $query = SocialInteraction::with('socialAccount')
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('interaction_at', $sort === 'date_asc' ? 'asc' : 'desc')
            ->limit($limit);

        return $query->get();
    }

    /**
     * Get Meta (Facebook/Instagram) ad campaigns with performance insights.
     *
     * @return array{campaigns: array, summary: array, error: string|null}
     */
    public function getMetaCampaigns(): array
    {
        $account = SocialAccount::where('platform', 'facebook')->first();
        if (!$account || !$account->access_token) {
            return ['campaigns' => [], 'summary' => ['spend' => 0, 'impressions' => 0, 'clicks' => 0], 'error' => 'Connect your Facebook account to view Meta campaigns.'];
        }

        $adAccountId = $account->metadata['ad_account_id'] ?? null;
        if (!$adAccountId) {
            return ['campaigns' => [], 'summary' => ['spend' => 0, 'impressions' => 0, 'clicks' => 0], 'error' => 'No Ad Account found. Reconnect Facebook with Ads access.'];
        }

        $since = now()->subDays(30)->format('Y-m-d');
        $until = now()->format('Y-m-d');
        $timeRange = json_encode(['since' => $since, 'until' => $until]);

        $response = Http::withToken($account->access_token)
            ->get("https://graph.facebook.com/v18.0/{$adAccountId}/campaigns", [
                'fields' => 'id,name,status,objective,daily_budget,lifetime_budget,created_time,insights{impressions,clicks,spend,actions}',
                'time_range' => $timeRange,
                'limit' => 50,
            ]);

        if (!$response->successful()) {
            $err = $response->json('error.message', $response->body());
            Log::warning("Meta campaigns API failed: {$err}");
            return ['campaigns' => [], 'summary' => ['spend' => 0, 'impressions' => 0, 'clicks' => 0], 'error' => $err];
        }

        $data = $response->json('data', []);
        $campaigns = [];
        $totalSpend = 0;
        $totalImpressions = 0;
        $totalClicks = 0;

        foreach ($data as $c) {
            $insights = $c['insights']['data'][0] ?? [];
            $spend = (float) ($insights['spend'] ?? 0);
            $impressions = (int) ($insights['impressions'] ?? 0);
            $clicks = (int) ($insights['clicks'] ?? 0);
            $actions = $insights['action_type'] ?? null;
            $leads = 0;
            if (!empty($insights['actions'])) {
                foreach ((array) $insights['actions'] as $a) {
                    if (($a['action_type'] ?? '') === 'lead') {
                        $leads = (int) ($a['value'] ?? 0);
                        break;
                    }
                }
            }
            $totalSpend += $spend;
            $totalImpressions += $impressions;
            $totalClicks += $clicks;

            $campaigns[] = [
                'id' => $c['id'] ?? '',
                'name' => $c['name'] ?? 'Unknown',
                'status' => $c['status'] ?? '—',
                'objective' => $c['objective'] ?? '—',
                'created_time' => $c['created_time'] ?? null,
                'spend' => $spend,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'leads' => $leads,
                'url' => "https://business.facebook.com/adsmanager/manage/campaigns?act=" . preg_replace('/^act_/', '', $adAccountId),
            ];
        }

        return [
            'campaigns' => $campaigns,
            'summary' => ['spend' => $totalSpend, 'impressions' => $totalImpressions, 'clicks' => $totalClicks],
            'error' => null,
        ];
    }

    /**
     * Fetch and store Twitter replies for connected account (call via job/scheduler).
     */
    public function fetchTwitterReplies(SocialAccount $account): int
    {
        $count = 0;
        try {
            $meResponse = Http::withToken($account->access_token)->get('https://api.twitter.com/2/users/me');
            if (!$meResponse->successful()) return 0;
            $userId = $meResponse->json('data.id');
            if (!$userId) return 0;

            $response = Http::withToken($account->access_token)
                ->get("https://api.twitter.com/2/users/{$userId}/tweets", [
                    'max_results' => 10,
                    'tweet.fields' => 'created_at,conversation_id',
                    'exclude' => 'retweets',
                ]);
            if (!$response->successful()) return 0;

            $tweets = $response->json('data') ?? [];
            foreach ($tweets as $tweet) {
                $tid = $tweet['id'];
                $repliesResp = Http::withToken($account->access_token)
                    ->get("https://api.twitter.com/2/tweets/{$tid}/quote_tweets", ['max_results' => 5]);
                // Quote tweets - for replies we'd need search or different endpoint
                // Twitter API v2 has limited reply access. Store mentions for now.
            }
        } catch (\Throwable $e) {
            Log::warning("Social media fetch replies failed: {$e->getMessage()}");
        }
        return $count;
    }

    private function getTwitterMetrics(SocialAccount $account): ?array
    {
        $response = Http::withToken($account->access_token)
            ->get('https://api.twitter.com/2/users/me', [
                'user.fields' => 'public_metrics',
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json('data');
        $metrics = $data['public_metrics'] ?? [];

        return [
            'audience' => $metrics['followers_count'] ?? 0,
            'mentions' => 0,
            'engagement' => $metrics['tweet_count'] ?? 0,
            'posts' => $metrics['tweet_count'] ?? 0,
        ];
    }

    private function getYouTubeMetrics(SocialAccount $account): ?array
    {
        $response = Http::withToken($account->access_token)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'statistics',
                'mine' => 'true',
            ]);

        if (!$response->successful()) {
            return null;
        }

        $items = $response->json('items');
        if (empty($items)) {
            return null;
        }

        $stats = $items[0]['statistics'] ?? [];

        return [
            'audience' => (int) ($stats['subscriberCount'] ?? 0),
            'mentions' => 0,
            'engagement' => (int) ($stats['viewCount'] ?? 0),
            'posts' => (int) ($stats['videoCount'] ?? 0),
        ];
    }

    private function getTikTokMetrics(SocialAccount $account): ?array
    {
        $response = Http::withToken($account->access_token)
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'follower_count,following_count,likes_count,video_count',
            ]);

        if (!$response->successful()) {
            return null;
        }

        $user = $response->json('data.user');
        if (!$user) {
            return null;
        }

        $followers = $user['follower_count'] ?? 0;
        $likes = $user['likes_count'] ?? 0;

        $videoCount = $user['video_count'] ?? 0;

        return [
            'audience' => (int) $followers,
            'mentions' => 0,
            'engagement' => (int) $likes,
            'posts' => (int) $videoCount,
        ];
    }

    private function getFacebookMetrics(SocialAccount $account): ?array
    {
        if (!config('services.facebook.client_id')) {
            return null;
        }

        $response = Http::withToken($account->access_token)
            ->get('https://graph.facebook.com/v18.0/me', [
                'fields' => 'id,name',
            ]);

        if (!$response->successful()) {
            return null;
        }

        return [
            'audience' => 0,
            'mentions' => 0,
            'engagement' => 0,
            'posts' => 0,
        ];
    }

    private function getInstagramMetrics(SocialAccount $account): ?array
    {
        if (!config('services.instagram.client_id')) {
            return null;
        }

        return [
            'audience' => 0,
            'mentions' => 0,
            'engagement' => 0,
            'posts' => 0,
        ];
    }

    private function getTwitterPosts(SocialAccount $account): array
    {
        $meResponse = Http::withToken($account->access_token)->get('https://api.twitter.com/2/users/me');
        if (!$meResponse->successful()) {
            return [];
        }
        $userId = $meResponse->json('data.id');
        if (!$userId) {
            return [];
        }

        $response = Http::withToken($account->access_token)
            ->get("https://api.twitter.com/2/users/{$userId}/tweets", [
                'max_results' => 5,
                'tweet.fields' => 'created_at,public_metrics',
                'exclude' => 'replies,retweets',
            ]);

        if (!$response->successful()) {
            return [];
        }

        $tweets = $response->json('data') ?? [];
        $username = $account->metadata['username'] ?? $account->account_name;

        return array_map(function ($tweet) use ($account, $username) {
            $metrics = $tweet['public_metrics'] ?? [];
            $engagementRaw = ($metrics['like_count'] ?? 0) + ($metrics['reply_count'] ?? 0) + ($metrics['retweet_count'] ?? 0);

            return [
                'platform' => 'twitter',
                'account_name' => $account->account_name ?? 'Twitter',
                'content' => $tweet['text'] ?? '',
                'url' => $username ? "https://twitter.com/{$username}/status/{$tweet['id']}" : null,
                'created_at' => $tweet['created_at'] ?? null,
                'engagement' => $engagementRaw > 0 ? $this->formatNumber($engagementRaw) : null,
                'engagement_raw' => $engagementRaw,
            ];
        }, $tweets);
    }

    private function getYouTubePosts(SocialAccount $account): array
    {
        $channelResponse = Http::withToken($account->access_token)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'contentDetails',
                'mine' => 'true',
            ]);

        if (!$channelResponse->successful()) {
            return [];
        }

        $items = $channelResponse->json('items');
        if (empty($items)) {
            return [];
        }

        $uploadPlaylistId = $items[0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        if (!$uploadPlaylistId) {
            return [];
        }

        $playlistResponse = Http::withToken($account->access_token)
            ->get('https://www.googleapis.com/youtube/v3/playlistItems', [
                'part' => 'snippet',
                'playlistId' => $uploadPlaylistId,
                'maxResults' => 5,
            ]);

        if (!$playlistResponse->successful()) {
            return [];
        }

        $videos = $playlistResponse->json('items') ?? [];

        return array_map(function ($item) use ($account) {
            $snippet = $item['snippet'] ?? [];
            $videoId = $snippet['resourceId']['videoId'] ?? null;

            return [
                'platform' => 'youtube',
                'account_name' => $account->account_name ?? 'YouTube',
                'content' => $snippet['title'] ?? '',
                'url' => $videoId ? "https://youtube.com/watch?v={$videoId}" : null,
                'created_at' => $snippet['publishedAt'] ?? null,
                'engagement' => null,
                'engagement_raw' => 0,
            ];
        }, $videos);
    }

    private function getTikTokPosts(SocialAccount $account): array
    {
        $response = Http::withToken($account->access_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('https://open.tiktokapis.com/v2/video/list/?fields=id,title,create_time,share_url,like_count', [
                'max_count' => 5,
            ]);

        if (!$response->successful()) {
            return [];
        }

        $videos = $response->json('data.videos') ?? [];

        return array_map(function ($video) use ($account) {
            $likes = (int) ($video['like_count'] ?? 0);
            return [
                'platform' => 'tiktok',
                'account_name' => $account->account_name ?? 'TikTok',
                'content' => $video['title'] ?? '',
                'url' => $video['share_url'] ?? null,
                'created_at' => isset($video['create_time']) ? date('c', $video['create_time']) : null,
                'engagement' => $likes > 0 ? $this->formatNumber($likes) : null,
                'engagement_raw' => $likes,
            ];
        }, $videos);
    }

    private function formatNumber(int $num): string
    {
        if ($num >= 1_000_000) {
            return round($num / 1_000_000, 1) . 'M';
        }
        if ($num >= 1_000) {
            return round($num / 1_000, 1) . 'K';
        }
        return (string) $num;
    }
}

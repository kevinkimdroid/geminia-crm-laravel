<?php

namespace App\Services;

use App\Models\SocialAccount;
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
     * @return array<int, array{platform: string, account_name: string, content: string, url: string|null, created_at: string|null, engagement: string|null}>
     */
    public function getRecentPosts(): array
    {
        $posts = [];
        $accounts = SocialAccount::all();

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

        usort($posts, fn ($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));

        return array_slice($posts, 0, 10);
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
            $engagement = ($metrics['like_count'] ?? 0) + ($metrics['reply_count'] ?? 0) + ($metrics['retweet_count'] ?? 0);

            return [
                'platform' => 'twitter',
                'account_name' => $account->account_name ?? 'Twitter',
                'content' => $tweet['text'] ?? '',
                'url' => $username ? "https://twitter.com/{$username}/status/{$tweet['id']}" : null,
                'created_at' => $tweet['created_at'] ?? null,
                'engagement' => $engagement > 0 ? $this->formatNumber($engagement) : null,
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
            return [
                'platform' => 'tiktok',
                'account_name' => $account->account_name ?? 'TikTok',
                'content' => $video['title'] ?? '',
                'url' => $video['share_url'] ?? null,
                'created_at' => isset($video['create_time']) ? date('c', $video['create_time']) : null,
                'engagement' => isset($video['like_count']) ? $this->formatNumber($video['like_count']) : null,
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

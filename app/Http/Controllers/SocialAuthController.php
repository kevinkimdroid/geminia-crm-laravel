<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(string $platform): RedirectResponse
    {
        $platform = strtolower($platform);
        $this->validatePlatform($platform);

        return match ($platform) {
            'facebook' => Socialite::driver('facebook')
                ->scopes(['pages_show_list', 'pages_read_engagement', 'pages_manage_posts'])
                ->redirect(),
            'instagram' => Socialite::driver('facebook')
                ->scopes(['instagram_basic', 'instagram_manage_insights', 'pages_show_list'])
                ->redirectUrl(config('services.instagram.redirect'))
                ->redirect(),
            'twitter' => Socialite::driver('twitter-oauth-2')
                ->redirect(),
            'youtube' => Socialite::driver('google')
                ->scopes(['https://www.googleapis.com/auth/youtube.readonly', 'https://www.googleapis.com/auth/youtube.upload'])
                ->redirect(),
            'tiktok' => $this->redirectTikTok(),
            default => abort(404),
        };
    }

    public function callback(Request $request, string $platform): RedirectResponse
    {
        $platform = strtolower($platform);
        $this->validatePlatform($platform);

        try {
            $account = match ($platform) {
                'facebook' => $this->handleFacebookCallback(),
                'instagram' => $this->handleInstagramCallback(),
                'twitter' => $this->handleTwitterCallback(),
                'youtube' => $this->handleYouTubeCallback(),
                'tiktok' => $this->handleTikTokCallback($request),
                default => null,
            };

            if ($account) {
                return redirect()->route('marketing.social-media')
                    ->with('success', ucfirst($platform) . ' account connected successfully.');
            }
        } catch (\Throwable $e) {
            return redirect()->route('marketing.social-media')
                ->with('error', 'Failed to connect: ' . $e->getMessage());
        }

        return redirect()->route('marketing.social-media')
            ->with('error', 'Failed to connect ' . $platform . ' account.');
    }

    public function disconnect(string $platform): RedirectResponse
    {
        $platform = strtolower($platform);
        SocialAccount::where('platform', $platform)->delete();

        return redirect()->route('marketing.social-media')
            ->with('success', ucfirst($platform) . ' account disconnected.');
    }

    private function validatePlatform(string $platform): void
    {
        if (!in_array($platform, SocialAccount::platforms())) {
            abort(404);
        }
    }

    private function handleFacebookCallback(): ?SocialAccount
    {
        $user = Socialite::driver('facebook')->user();
        SocialAccount::updateOrCreate(
            ['platform' => 'facebook', 'account_id' => $user->getId()],
            [
                'account_name' => $user->getName(),
                'access_token' => $user->token,
                'metadata' => ['email' => $user->getEmail()],
            ]
        );
        return SocialAccount::where('platform', 'facebook')->first();
    }

    private function handleInstagramCallback(): ?SocialAccount
    {
        $user = Socialite::driver('facebook')->user();
        SocialAccount::updateOrCreate(
            ['platform' => 'instagram', 'account_id' => $user->getId()],
            [
                'account_name' => $user->getName(),
                'access_token' => $user->token,
                'metadata' => ['email' => $user->getEmail()],
            ]
        );
        return SocialAccount::where('platform', 'instagram')->first();
    }

    private function handleTwitterCallback(): ?SocialAccount
    {
        $user = Socialite::driver('twitter')->user();
        SocialAccount::updateOrCreate(
            ['platform' => 'twitter', 'account_id' => $user->getId()],
            [
                'account_name' => $user->getName(),
                'access_token' => $user->token,
                'refresh_token' => $user->refreshToken ?? null,
                'metadata' => ['username' => $user->getNickname()],
            ]
        );
        return SocialAccount::where('platform', 'twitter')->first();
    }

    private function handleYouTubeCallback(): ?SocialAccount
    {
        $user = Socialite::driver('google')->user();
        SocialAccount::updateOrCreate(
            ['platform' => 'youtube', 'account_id' => $user->getId()],
            [
                'account_name' => $user->getName(),
                'access_token' => $user->token,
                'refresh_token' => $user->refreshToken,
                'token_expires_at' => isset($user->expiresIn) ? now()->addSeconds($user->expiresIn) : null,
                'metadata' => ['email' => $user->getEmail()],
            ]
        );
        return SocialAccount::where('platform', 'youtube')->first();
    }

    private function redirectTikTok(): RedirectResponse
    {
        $clientKey = config('services.tiktok.client_key');
        $redirectUri = config('services.tiktok.redirect');
        $state = bin2hex(random_bytes(16));

        // PKCE: TikTok requires code_challenge for OAuth 2.0
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        session([
            'tiktok_oauth_state' => $state,
            'tiktok_oauth_code_verifier' => $codeVerifier,
        ]);

        $url = 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key' => $clientKey,
            'scope' => 'user.info.basic,user.info.stats,video.list',
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect($url);
    }

    private function generateCodeVerifier(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
        $verifier = '';
        for ($i = 0; $i < 64; $i++) {
            $verifier .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $verifier;
    }

    private function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    private function handleTikTokCallback(Request $request): ?SocialAccount
    {
        if ($request->get('state') !== session('tiktok_oauth_state')) {
            throw new \Exception('Invalid state');
        }

        $code = $request->get('code');
        if (!$code) {
            throw new \Exception('No authorization code received');
        }

        $codeVerifier = session('tiktok_oauth_code_verifier');
        if (!$codeVerifier) {
            throw new \Exception('PKCE code verifier missing. Please try connecting again.');
        }

        $params = [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.tiktok.redirect'),
            'code_verifier' => $codeVerifier,
        ];

        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', $params);

        $data = $response->json();
        if (!isset($data['data']['access_token'])) {
            throw new \Exception($data['error'] ?? 'TikTok token exchange failed');
        }

        $tokenData = $data['data'];
        $userId = $tokenData['open_id'] ?? 'unknown';

        SocialAccount::updateOrCreate(
            ['platform' => 'tiktok', 'account_id' => $userId],
            [
                'account_name' => 'TikTok Account',
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
                'metadata' => $tokenData,
            ]
        );

        session()->forget(['tiktok_oauth_state', 'tiktok_oauth_code_verifier']);
        return SocialAccount::where('platform', 'tiktok')->first();
    }
}

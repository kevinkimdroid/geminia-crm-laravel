# Social Media Integration Setup

Connect Facebook, Instagram, Twitter, YouTube, and TikTok to your Geminia CRM.

## 1. Add Credentials to `.env`

Add your API keys to the `.env` file:

```env
FACEBOOK_CLIENT_ID=your_facebook_app_id
FACEBOOK_CLIENT_SECRET=your_facebook_app_secret
INSTAGRAM_CLIENT_ID=your_meta_app_id
INSTAGRAM_CLIENT_SECRET=your_meta_app_secret
TWITTER_CLIENT_ID=your_twitter_client_id
TWITTER_CLIENT_SECRET=your_twitter_client_secret
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
TIKTOK_CLIENT_KEY=your_tiktok_client_key
TIKTOK_CLIENT_SECRET=your_tiktok_client_secret
```

## 2. Get API Keys

### Facebook & Instagram (Meta)
1. Go to [developers.facebook.com](https://developers.facebook.com/)
2. Create an app → Add **Facebook Login** and **Instagram Graph API**
3. Add redirect URIs: `{APP_URL}/social-auth/facebook/callback` and `{APP_URL}/social-auth/instagram/callback`
4. Use same App ID/Secret for both (Instagram uses Meta's API)
5. **For Meta Ad Campaigns:** Add the **Marketing API** product and request the `ads_read` permission. In App Review, submit for approval if you need production access. Development mode works with test users who have Ad Account access.

### Twitter / X
1. Go to [developer.twitter.com](https://developer.twitter.com/)
2. Create a project and app
3. Enable OAuth 2.0, set callback: `{APP_URL}/social-auth/twitter/callback`
4. Add **Read and write** permissions

### YouTube (Google)
1. Go to [console.cloud.google.com](https://console.cloud.google.com/)
2. Create project → Enable **YouTube Data API v3**
3. Create OAuth 2.0 credentials (Web application)
4. Add redirect: `{APP_URL}/social-auth/youtube/callback`

### TikTok
1. Go to [developers.tiktok.com](https://developers.tiktok.com/)
2. Create an app
3. Add redirect URL: `{APP_URL}/social-auth/tiktok/callback`
4. Request scopes: `user.info.basic`, `video.list`

## 3. Redirect URIs

Replace `{APP_URL}` with your app URL (e.g. `http://localhost:8000` or `https://yourdomain.com`).

Ensure `APP_URL` in `.env` is correct.

## 4. Connect Accounts

1. Go to **Marketing → Social Media**
2. Click **Connect** on each platform
3. Authorize the app when redirected
4. You'll be redirected back with the account connected

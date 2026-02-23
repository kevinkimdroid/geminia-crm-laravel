<?php

namespace App\Http\Controllers;

use App\Models\ScheduledSocialPost;
use App\Models\SocialAccount;
use App\Services\SocialMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SocialMediaController extends Controller
{
    /** @var SocialMediaService */
    protected $socialMedia;

    public function __construct(SocialMediaService $socialMedia)
    {
        $this->socialMedia = $socialMedia;
    }

    public function index(): View
    {
        $socialAccounts = SocialAccount::all();
        $metrics = $this->socialMedia->getMetrics();
        $platformStats = $this->socialMedia->getPlatformStats();
        $recentPosts = $this->socialMedia->getRecentPosts();
        $scheduledPosts = ScheduledSocialPost::with('socialAccount')
            ->whereIn('status', [ScheduledSocialPost::STATUS_SCHEDULED, ScheduledSocialPost::STATUS_PUBLISHED])
            ->orderByDesc('scheduled_at')
            ->limit(20)
            ->get();
        $hasConnectedAccounts = $socialAccounts->isNotEmpty();

        return view('marketing.social-media', [
            'socialAccounts' => $socialAccounts,
            'metrics' => $metrics,
            'platformStats' => $platformStats,
            'recentPosts' => $recentPosts,
            'scheduledPosts' => $scheduledPosts,
            'hasConnectedAccounts' => $hasConnectedAccounts,
        ]);
    }

    public function schedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'social_account_id' => ['required', Rule::exists('social_accounts', 'id')],
            'content' => ['required', 'string', 'max:10000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ], [
            'social_account_id.required' => 'Please select a platform.',
            'content.required' => 'Post content is required.',
            'scheduled_at.after' => 'Scheduled time must be in the future.',
        ]);

        $account = SocialAccount::findOrFail($validated['social_account_id']);

        ScheduledSocialPost::create([
            'social_account_id' => $account->id,
            'platform' => $account->platform,
            'content' => $validated['content'],
            'scheduled_at' => $validated['scheduled_at'],
            'status' => ScheduledSocialPost::STATUS_SCHEDULED,
        ]);

        return redirect()->route('marketing.social-media')
            ->with('success', 'Post scheduled for ' . \Carbon\Carbon::parse($validated['scheduled_at'])->format('M j, Y g:i A') . '.');
    }

    public function cancelSchedule(int $id): RedirectResponse
    {
        $post = ScheduledSocialPost::where('status', ScheduledSocialPost::STATUS_SCHEDULED)->findOrFail($id);
        $post->update(['status' => ScheduledSocialPost::STATUS_CANCELLED]);

        return redirect()->route('marketing.social-media')
            ->with('success', 'Scheduled post cancelled.');
    }
}

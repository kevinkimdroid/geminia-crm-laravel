<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\SocialInteraction;
use App\Models\ScheduledSocialPost;
use App\Models\SocialAccount;
use App\Services\CrmService;
use App\Services\SocialMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function index(Request $request): View
    {
        $socialAccounts = SocialAccount::all();
        $metrics = $this->socialMedia->getMetrics();
        $platformStats = $this->socialMedia->getPlatformStats();

        $postPlatform = $request->get('post_platform');
        $postSort = $request->get('post_sort', 'date_desc');
        $recentPosts = $this->socialMedia->getRecentPosts($postPlatform, $postSort, 30);

        $schedPlatform = $request->get('sched_platform');
        $schedSort = $request->get('sched_sort', 'date_desc');
        $scheduledQuery = ScheduledSocialPost::with('socialAccount')
            ->whereIn('status', [ScheduledSocialPost::STATUS_SCHEDULED, ScheduledSocialPost::STATUS_PUBLISHED]);
        if ($schedPlatform) {
            $scheduledQuery->where('platform', $schedPlatform);
        }
        $scheduledQuery->orderBy('scheduled_at', $schedSort === 'date_asc' ? 'asc' : 'desc');
        $scheduledPosts = $scheduledQuery->limit(50)->get();

        $intPlatform = $request->get('int_platform');
        $intSort = $request->get('int_sort', 'date_desc');
        $interactions = $this->socialMedia->getInteractions($intPlatform, null, $intSort, 50);

        $socialLeads = $this->getSocialLeads($request->get('lead_platform'));

        $campaigns = Campaign::orderBy('campaign_name')->get();

        $metaCampaignsData = $this->socialMedia->getMetaCampaigns();

        $hasConnectedAccounts = $socialAccounts->isNotEmpty();

        return view('marketing.social-media', [
            'socialAccounts' => $socialAccounts,
            'metrics' => $metrics,
            'platformStats' => $platformStats,
            'recentPosts' => $recentPosts,
            'scheduledPosts' => $scheduledPosts,
            'interactions' => $interactions,
            'socialLeads' => $socialLeads,
            'campaigns' => $campaigns,
            'hasConnectedAccounts' => $hasConnectedAccounts,
            'postPlatform' => $postPlatform,
            'postSort' => $postSort,
            'schedPlatform' => $schedPlatform,
            'schedSort' => $schedSort,
            'intPlatform' => $intPlatform,
            'intSort' => $intSort,
            'metaCampaigns' => $metaCampaignsData['campaigns'] ?? [],
            'metaCampaignsSummary' => $metaCampaignsData['summary'] ?? ['spend' => 0, 'impressions' => 0, 'clicks' => 0],
            'metaCampaignsError' => $metaCampaignsData['error'] ?? null,
        ]);
    }

    /**
     * Get leads with source "Social Media" from vtiger.
     */
    protected function getSocialLeads(?string $platformFilter = null): array
    {
        try {
            $query = DB::connection('vtiger')
                ->table('vtiger_leaddetails as l')
                ->join('vtiger_crmentity as e', 'l.leadid', '=', 'e.crmid')
                ->leftJoin('vtiger_leadaddress as la', 'l.leadid', '=', 'la.leadaddressid')
                ->where('e.deleted', 0)
                ->whereIn('e.setype', ['Leads', 'Lead'])
                ->where('l.leadsource', 'Social Media')
                ->select('l.leadid', 'l.firstname', 'l.lastname', 'l.company', 'l.email', 'l.leadsource', 'e.createdtime')
                ->orderByDesc('e.createdtime')
                ->limit(20);
            $rows = $query->get();
            return $rows->map(fn ($r) => (array) $r)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function schedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'social_account_id' => ['required', Rule::exists('social_accounts', 'id')],
            'content' => ['required', 'string', 'max:10000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')],
        ], [
            'social_account_id.required' => 'Please select a platform.',
            'content.required' => 'Post content is required.',
            'scheduled_at.after' => 'Scheduled time must be in the future.',
        ]);

        $account = SocialAccount::findOrFail($validated['social_account_id']);

        ScheduledSocialPost::create([
            'social_account_id' => $account->id,
            'platform' => $account->platform,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'content' => $validated['content'],
            'scheduled_at' => $validated['scheduled_at'],
            'status' => ScheduledSocialPost::STATUS_SCHEDULED,
        ]);

        return redirect()->route('marketing.social-media')
            ->with('success', 'Post scheduled for ' . \Carbon\Carbon::parse($validated['scheduled_at'])->format('M j, Y g:i A') . '.');
    }

    /**
     * Redirect to lead creation form with interaction data prefilled.
     */
    public function createLeadFromInteraction(int $id): RedirectResponse
    {
        $interaction = SocialInteraction::with('socialAccount')->findOrFail($id);
        $name = trim($interaction->author_name ?? '');
        $parts = explode(' ', $name, 2);

        return redirect()->route('leads.create')
            ->withInput([
                'firstname' => $parts[0] ?? $name ?: 'Social',
                'lastname' => $parts[1] ?? 'Lead',
                'email' => $interaction->author_email ?? '',
                'company' => 'From ' . ucfirst($interaction->platform),
                'leadsource' => 'Social Media',
            ])
            ->with('from_interaction_id', $id);
    }

    public function cancelSchedule(int $id): RedirectResponse
    {
        $post = ScheduledSocialPost::where('status', ScheduledSocialPost::STATUS_SCHEDULED)->findOrFail($id);
        $post->update(['status' => ScheduledSocialPost::STATUS_CANCELLED]);

        return redirect()->route('marketing.social-media')
            ->with('success', 'Scheduled post cancelled.');
    }
}

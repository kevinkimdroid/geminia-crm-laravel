@extends('layouts.app')

@section('title', 'Social Media')

@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">Social Media</h1>
        <p class="page-subtitle">Monitor, schedule, and analyze your social media presence.</p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Webhook URLs (for developer setup) --}}
<div class="card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="card-section-title mb-0"><i class="bi bi-webhook me-2"></i>Webhook / Callback URLs</h6>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#webhookUrlsCollapse" aria-expanded="false">
            <i class="bi bi-chevron-down"></i> Show URLs
        </button>
    </div>
    <p class="text-muted small mb-3">Use these URLs when configuring webhooks in each platform's developer console (Meta, Twitter, TikTok, Google).</p>
    <div class="collapse" id="webhookUrlsCollapse">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Platform</th>
                        <th>Webhook / Callback URL</th>
                        <th class="text-end">Copy</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(config('services.social_webhooks', []) as $platform => $url)
                    <tr>
                        <td class="fw-semibold text-capitalize">{{ $platform }}</td>
                        <td><code class="text-break" style="font-size: 0.85em;">{{ $url }}</code></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary copy-webhook-btn" data-url="{{ $url }}" title="Copy URL">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Connect Accounts --}}
<div class="card p-4 mb-4">
    <h6 class="card-section-title mb-3"><i class="bi bi-link-45deg me-2"></i>Connected Accounts</h6>
    <p class="text-muted small mb-4">Connect your social media accounts to pull metrics, schedule posts, and view analytics.</p>
    <div class="row g-3">
        @php $accounts = $socialAccounts ?? collect(); $connected = $accounts->pluck('platform')->toArray(); @endphp
        @php
            $platforms = [
                'facebook' => ['Facebook', 'bi-facebook', '#1877f2', config('services.facebook.client_id')],
                'instagram' => ['Instagram', 'bi-instagram', '#e4405f', config('services.instagram.client_id')],
                'twitter' => ['Twitter', 'bi-twitter', '#1da1f2', config('services.twitter.client_id')],
                'youtube' => ['YouTube', 'bi-youtube', '#ff0000', config('services.google.client_id')],
                'tiktok' => ['TikTok', 'bi-music-note-beamed', '#000000', config('services.tiktok.client_key')],
            ];
        @endphp
        @foreach($platforms as $platform => $info)
            @if($info[3])
            <div class="col-md-6 col-lg-4">
                <div class="social-connect-card {{ in_array($platform, $connected) ? 'connected' : '' }}">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="social-platform-icon" style="background: {{ $info[2] }}20; color: {{ $info[2] }};">
                                <i class="bi {{ $info[1] }}"></i>
                            </div>
                            <div>
                                <strong>{{ $info[0] }}</strong>
                                @if(in_array($platform, $connected))
                                    @php $acc = $accounts->firstWhere('platform', $platform); @endphp
                                    <small class="d-block text-muted">{{ $acc->account_name ?? 'Connected' }}</small>
                                @endif
                            </div>
                        </div>
                        @if(in_array($platform, $connected))
                            <form action="{{ route('social-auth.disconnect', $platform) }}" method="POST" class="d-inline" onsubmit="return confirm('Disconnect {{ $info[0] }}?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Disconnect</button>
                            </form>
                        @else
                            <a href="{{ route('social-auth.redirect', $platform) }}" class="btn btn-sm btn-primary-custom">Connect</a>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        @endforeach
    </div>
</div>

<ul class="nav nav-tabs social-media-tabs mb-4" id="socialMediaTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="monitoring-tab" data-bs-toggle="tab" data-bs-target="#monitoring" type="button" role="tab">
            <i class="bi bi-speedometer2 me-2"></i>Monitoring
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="scheduling-tab" data-bs-toggle="tab" data-bs-target="#scheduling" type="button" role="tab">
            <i class="bi bi-calendar-event me-2"></i>Scheduling & Publishing
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
            <i class="bi bi-graph-up me-2"></i>Analytics
        </button>
    </li>
</ul>

<div class="tab-content" id="socialMediaTabContent">
    {{-- MONITORING - Dashboard --}}
    <div class="tab-pane fade show active" id="monitoring" role="tabpanel">
        <div class="card p-4 mb-4">
            <h6 class="card-section-title mb-4"><i class="bi bi-speedometer2 me-2"></i>Key Metrics</h6>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <p class="metric-label">Total Audience</p>
                            <h3 class="metric-value">{{ $metrics['total_audience'] ?? '—' }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="bi bi-chat-quote-fill"></i></div>
                        <div>
                            <p class="metric-label">Mentions</p>
                            <h3 class="metric-value">{{ $metrics['mentions'] ?? '—' }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="bi bi-heart-fill"></i></div>
                        <div>
                            <p class="metric-label">Engagement</p>
                            <h3 class="metric-value">{{ $metrics['engagement'] ?? '—' }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Per-platform statistics --}}
        @if(!empty($platformStats ?? []))
        <div class="card p-4 mb-4">
            <h6 class="card-section-title mb-4"><i class="bi bi-bar-chart-line me-2"></i>Statistics by Platform</h6>
            <div class="row g-3">
                @foreach($platformStats as $stat)
                <div class="col-md-6 col-lg-4">
                    <div class="platform-stat-card">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge platform-badge platform-{{ $stat['platform'] }}">{{ ucfirst($stat['platform']) }}</span>
                            <span class="text-muted small">{{ Str::limit($stat['account_name'], 20) }}</span>
                        </div>
                        <div class="row g-2 small">
                            <div class="col-4"><span class="text-muted">Followers</span><br><strong>{{ $stat['followers'] }}</strong></div>
                            <div class="col-4"><span class="text-muted">Engagement</span><br><strong>{{ $stat['engagement'] }}</strong></div>
                            @if($stat['posts'] !== null)
                            <div class="col-4"><span class="text-muted">Posts</span><br><strong>{{ $stat['posts'] }}</strong></div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card p-4 h-100">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-broadcast me-2"></i>Live Stream / Community</h6>
                    </div>
                    @if($hasConnectedAccounts ?? false)
                        <div class="placeholder-content">
                            <i class="bi bi-broadcast text-muted"></i>
                            <p class="text-muted mt-2 mb-0 small">Live stream content from community will appear here when available.</p>
                        </div>
                    @else
                        <div class="placeholder-content">
                            <i class="bi bi-broadcast text-muted"></i>
                            <p class="text-muted mt-2 mb-0">Live stream content from community will appear here when connected.</p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card p-4 h-100">
                    <div class="card-header-custom">
                        <h6><i class="bi bi-inbox me-2"></i>Recent Posts & Conversations</h6>
                        <a href="#" class="card-arrow" title="View inbox"><i class="bi bi-arrow-right"></i></a>
                    </div>
                    @if(!empty($recentPosts))
                        <div class="recent-posts-list">
                            @foreach($recentPosts as $post)
                                <div class="recent-post-item">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="flex-grow-1 min-w-0">
                                            <span class="badge bg-primary me-2">{{ ucfirst($post['platform']) }}</span>
                                            <span class="text-muted small">{{ $post['account_name'] }}</span>
                                            <p class="mb-1 mt-1 small text-break">{{ Str::limit($post['content'], 80) }}</p>
                                            @if($post['engagement'])
                                                <span class="text-muted small"><i class="bi bi-heart-fill"></i> {{ $post['engagement'] }}</span>
                                            @endif
                                        </div>
                                        @if($post['url'])
                                            <a href="{{ $post['url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">View</a>
                                        @endif
                                    </div>
                                    @if($post['created_at'])
                                        <small class="text-muted">{{ \Carbon\Carbon::parse($post['created_at'])->diffForHumans() }}</small>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="placeholder-content">
                            <i class="bi bi-inbox text-muted"></i>
                            <p class="text-muted mt-2 mb-0">Recent posts and conversations from platform inboxes will appear here.</p>
                            <p class="text-muted small mt-1">Connect your accounts above to see your posts.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- SCHEDULING & PUBLISHING --}}
    <div class="tab-pane fade" id="scheduling" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card p-4">
                    <h6 class="card-section-title mb-3"><i class="bi bi-plus-circle me-2"></i>Schedule New Post</h6>
                    @if(($socialAccounts ?? collect())->isNotEmpty())
                    <form action="{{ route('marketing.social-media.schedule') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Platform</label>
                            <select name="social_account_id" class="form-select" required>
                                <option value="">Select platform...</option>
                                @foreach($socialAccounts ?? [] as $acc)
                                <option value="{{ $acc->id }}">{{ ucfirst($acc->platform) }} — {{ Str::limit($acc->account_name, 25) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea name="content" class="form-control" rows="4" placeholder="Write your post..." required maxlength="10000"></textarea>
                            <small class="text-muted">Max 10,000 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date & Time</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" required min="{{ date('Y-m-d\TH:i', strtotime('+1 hour')) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Media (optional)</label>
                            <div class="border rounded p-3 text-center bg-light">
                                <i class="bi bi-cloud-arrow-up text-muted"></i>
                                <p class="text-muted small mt-2 mb-0">Image/video upload coming soon</p>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary-custom"><i class="bi bi-calendar-plus me-1"></i>Schedule</button>
                    </form>
                    @else
                    <p class="text-muted mb-0">Connect at least one social account above to schedule posts.</p>
                    @endif
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card p-4">
                    <h6 class="card-section-title mb-3"><i class="bi bi-calendar-event me-2"></i>Scheduled & Published Posts</h6>
                    @if(!empty($scheduledPosts ?? []) && $scheduledPosts->isNotEmpty())
                    <div class="scheduled-posts-list">
                        @foreach($scheduledPosts as $post)
                        <div class="scheduled-post-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="flex-grow-1 min-w-0">
                                    <span class="badge platform-badge platform-{{ $post->platform }} me-2">{{ ucfirst($post->platform) }}</span>
                                    <span class="badge {{ $post->status === 'published' ? 'bg-success' : ($post->status === 'scheduled' ? 'bg-primary' : 'bg-secondary') }}">{{ ucfirst($post->status) }}</span>
                                    <p class="mb-1 mt-2 small text-break">{{ Str::limit($post->content, 120) }}</p>
                                    <small class="text-muted">
                                        {{ $post->scheduled_at->format('M j, Y g:i A') }}
                                        @if($post->published_at)
                                        · Published {{ $post->published_at->diffForHumans() }}
                                        @endif
                                    </small>
                                </div>
                                @if($post->status === 'scheduled' && $post->scheduled_at->isFuture())
                                <form action="{{ route('marketing.social-media.schedule.cancel', $post->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this scheduled post?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                </form>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="placeholder-content min-h-150">
                        <i class="bi bi-calendar-x text-muted"></i>
                        <p class="text-muted mt-2 mb-0">No scheduled or published posts yet.</p>
                        <p class="text-muted small">Schedule a post using the form on the left.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ANALYTICS --}}
    <div class="tab-pane fade" id="analytics" role="tabpanel">
        @if(!empty($platformStats ?? []))
        <div class="row g-4">
            <div class="col-12">
                <div class="card p-4">
                    <h6 class="card-section-title mb-4"><i class="bi bi-bar-chart me-2"></i>Followers by Platform</h6>
                    <div class="analytics-bars">
                        @php $maxAudience = collect($platformStats)->pluck('raw.audience')->max() ?: 1; @endphp
                        @foreach($platformStats as $stat)
                        <div class="analytics-bar-row mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-semibold">{{ ucfirst($stat['platform']) }} — {{ Str::limit($stat['account_name'], 20) }}</span>
                                <span class="text-muted">{{ $stat['followers'] }}</span>
                            </div>
                            <div class="progress" style="height: 24px;">
                                <div class="progress-bar platform-{{ $stat['platform'] }}-bg" role="progressbar" style="width: {{ ($stat['raw']['audience'] ?? 0) / $maxAudience * 100 }}%" aria-valuenow="{{ $stat['raw']['audience'] ?? 0 }}" aria-valuemin="0" aria-valuemax="{{ $maxAudience }}"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-4 h-100">
                    <h6 class="card-section-title mb-3"><i class="bi bi-heart-fill me-2"></i>Engagement by Platform</h6>
                    <div class="list-group list-group-flush">
                        @foreach($platformStats as $stat)
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="badge platform-badge platform-{{ $stat['platform'] }}">{{ ucfirst($stat['platform']) }}</span>
                            <strong>{{ $stat['engagement'] }}</strong>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-4 h-100">
                    <h6 class="card-section-title mb-3"><i class="bi bi-file-post me-2"></i>Posts by Platform</h6>
                    <div class="list-group list-group-flush">
                        @foreach($platformStats as $stat)
                        @if(($stat['posts'] ?? null) !== null)
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="badge platform-badge platform-{{ $stat['platform'] }}">{{ ucfirst($stat['platform']) }}</span>
                            <strong>{{ number_format($stat['posts']) }}</strong>
                        </div>
                        @endif
                        @endforeach
                        @if(collect($platformStats)->filter(fn($s) => ($s['posts'] ?? null) !== null)->isEmpty())
                        <p class="text-muted small mb-0">Post counts not available for connected accounts.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="card p-5 text-center">
            <i class="bi bi-graph-up-arrow text-muted" style="font-size: 3rem;"></i>
            <p class="text-muted mt-3 mb-2">Connect your social accounts to see analytics.</p>
            <a href="{{ route('marketing.social-media') }}#monitoring" class="btn btn-primary-custom btn-sm">Connect Accounts</a>
        </div>
        @endif
    </div>
</div>

<style>
.social-media-tabs { border-bottom: 2px solid var(--card-border); }
.social-media-tabs .nav-link { border: none; color: var(--text-muted); font-weight: 600; padding: 0.75rem 1.25rem; border-radius: 0; }
.social-media-tabs .nav-link:hover { color: var(--primary); }
.social-media-tabs .nav-link.active { color: var(--primary); border-bottom: 2px solid var(--primary); margin-bottom: -2px; background: transparent; }
.card-section-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--primary); }
.metric-card { display: flex; align-items: center; gap: 1rem; padding: 1.25rem; background: var(--primary-muted); border-radius: 12px; border-left: 4px solid var(--primary); }
.metric-icon { width: 48px; height: 48px; background: var(--primary-light); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.25rem; }
.metric-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin: 0 0 0.25rem; text-transform: uppercase; }
.metric-value { font-size: 1.5rem; font-weight: 700; margin: 0; }
.placeholder-content, .placeholder-chart { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; }
.placeholder-content i, .placeholder-chart i { font-size: 2.5rem; opacity: 0.5; }
.min-h-200 { min-height: 200px; }
.min-h-150 { min-height: 150px; }
.social-connect-card { padding: 1rem 1.25rem; border: 1px solid var(--card-border); border-radius: 12px; transition: all .2s; }
.social-connect-card.connected { border-color: var(--success); background: rgba(5, 150, 105, 0.05); }
.social-platform-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
.recent-posts-list { padding: 0; }
.recent-post-item { padding: 1rem; border-bottom: 1px solid var(--card-border); }
.recent-post-item:last-child { border-bottom: none; }
.text-break { word-break: break-word; }
.platform-stat-card { padding: 1rem; border: 1px solid var(--card-border); border-radius: 10px; background: var(--bs-body-bg); }
.platform-badge { font-size: 0.7rem; }
.platform-facebook { background: #1877f2; }
.platform-instagram { background: #e4405f; }
.platform-twitter { background: #1da1f2; }
.platform-youtube { background: #ff0000; }
.platform-tiktok { background: #000; }
.platform-facebook-bg { background-color: #1877f2 !important; }
.platform-instagram-bg { background-color: #e4405f !important; }
.platform-twitter-bg { background-color: #1da1f2 !important; }
.platform-youtube-bg { background-color: #ff0000 !important; }
.platform-tiktok-bg { background-color: #333 !important; }
.scheduled-posts-list { padding: 0; }
.scheduled-post-item { padding: 1rem; border-bottom: 1px solid var(--card-border); }
.scheduled-post-item:last-child { border-bottom: none; }
</style>
<script>
document.querySelectorAll('.copy-webhook-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const url = this.getAttribute('data-url');
        navigator.clipboard.writeText(url).then(function() {
            const icon = btn.querySelector('i.bi');
            if (icon) icon.className = 'bi bi-check';
            setTimeout(function() { if (icon) icon.className = 'bi bi-clipboard'; }, 1500);
        });
    });
});
</script>
@endsection

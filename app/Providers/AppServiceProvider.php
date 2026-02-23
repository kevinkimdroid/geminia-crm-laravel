<?php

namespace App\Providers;

use App\Services\CrmService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            try {
                $view->with('leadsTodayCount', Cache::remember(
                    'geminia_leads_today_' . now()->format('Y-m-d'),
                    120,
                    fn () => app(CrmService::class)->getLeadsTodayCount()
                ));
            } catch (\Throwable $e) {
                $view->with('leadsTodayCount', 0);
            }

            $user = Auth::guard('vtiger')->user();
            if (!$user) {
                $view->with('currentUserName', 'User');
                $view->with('currentUserRole', '—');
                $view->with('currentUserEmail', '');
                $view->with('allowedModules', app(\App\Services\ModuleService::class)->getEnabledModuleKeys());
                $view->with('pbxCanCall', false);
                $view->with('pbxDefaultExtension', '');
                return;
            }

            $layoutData = Cache::remember('geminia_user_layout_' . $user->id, 300, function () use ($user) {
                return [
                    'role' => $user->primary_role?->rolename ?? '—',
                    'allowed' => $user->getAllowedModules(),
                ];
            });
            $view->with('currentUserName', $user->full_name);
            $view->with('currentUserRole', $layoutData['role']);
            $view->with('currentUserEmail', $user->email1 ?? '');
            $allowed = $layoutData['allowed'];
            $enabled = app(\App\Services\ModuleService::class)->getEnabledModuleKeys();
            $effective = empty($allowed) ? $enabled : array_values(array_intersect($allowed, $enabled));
            if (empty($allowed)) {
                $effective = $enabled;
            }
            if (strcasecmp($layoutData['role'], 'Administrator') === 0 && ! in_array('tools.pbx-manager', $effective, true)) {
                $effective[] = 'tools.pbx-manager';
            }
            $view->with('allowedModules', $effective);

            $pbxConfig = app(\App\Services\PbxConfigService::class);
            $view->with('pbxCanCall', $pbxConfig->isConfigured());
            $view->with('pbxDefaultExtension', config('services.pbx.default_extension', env('PBX_DEFAULT_EXTENSION', '')));
        });

        View::composer('settings', function ($view) {
            $user = Auth::guard('vtiger')->user();
            $view->with('currentUserName', $user?->full_name ?? 'User');
            $view->with('currentUserRole', $user?->primary_role?->rolename ?? '—');
            $view->with('currentUserEmail', $user?->email1 ?? '');
        });
    }
}

<?php

namespace App\Providers;

use App\Cache\ResilientFileStore;
use App\Services\CrmService;
use App\Support\StoragePaths;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
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
        StoragePaths::ensure();

        // Some server OCI8 builds (or OCI8-missing CLI/FPM mismatch) do not expose OCI_DEFAULT,
        // but yajra/pdo-via-oci8 still references it. Define a safe fallback to prevent fatals.
        if (! defined('OCI_DEFAULT')) {
            define('OCI_DEFAULT', 0);
        }

        require_once app_path('helpers.php');

        // Receipt reprint (Finance) — choose the data source and configure the
        // RTF/PDF renderer. Reads from the shared ERP Oracle connection.
        $this->app->bind(\App\Services\Receipts\ReceiptDataSource::class, function () {
            if (config('receipt.demo')) {
                return new \App\Services\Receipts\DemoReceiptRepository();
            }

            return new \App\Services\Receipts\OracleReceiptRepository(
                connection: (string) config('receipt.connection', 'erp'),
                table: (string) config('receipt.table'),
            );
        });

        $this->app->singleton(\App\Services\Receipts\RtfReceiptRenderer::class, function () {
            $template = (string) config('receipt.template');
            if (! is_file($template)) {
                $template = rtrim((string) config('receipt.templates_path'), '/\\')
                    . DIRECTORY_SEPARATOR . $template;
            }

            return new \App\Services\Receipts\RtfReceiptRenderer(
                templatePath: $template,
                outputPath: (string) config('receipt.output_path'),
                sofficePath: config('receipt.soffice_path') ?: null,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cache::extend('file', function ($app) {
            $config = $app['config']['cache.stores.file'];

            return new Repository(new ResilientFileStore(
                $app['files'],
                $config['path'],
                $config['permission'] ?? null
            ));
        });

        Blade::directive('vite', function ($expression) {
            return "<?php echo app(\App\Support\ViteAssets::class)->toHtml({$expression}); ?>";
        });

        View::composer(['layouts.app', 'dashboard', 'marketing', 'support', 'support.pension-administration'], function ($view) {
            try {
                $view->with('leadsTodayCount', safe_cache_remember(
                    'geminia_leads_today_' . now()->format('Y-m-d'),
                    120,
                    fn () => app(CrmService::class)->getLeadsTodayCount()
                ));
            } catch (\Throwable $e) {
                $view->with('leadsTodayCount', 0);
            }

            try {
                $user = Auth::guard('vtiger')->user();
            } catch (\Throwable $e) {
                $user = null;
            }
            if (!$user) {
                try {
                    $view->with('allowedModules', app(\App\Services\ModuleService::class)->getEnabledModuleKeys());
                } catch (\Throwable $e) {
                    $view->with('allowedModules', []);
                }
                $view->with('currentUserName', 'User');
                $view->with('currentUserRole', '—');
                $view->with('currentUserEmail', '');
                $view->with('currentUserInitials', 'U');
                $view->with('pbxCanCall', false);
                $view->with('pbxDefaultExtension', '');
                $allowed = $view->getData()['allowedModules'] ?? [];
            $view->with('can', function ($k) use ($allowed) {
                if ($k === 'finance.payments') {
                    return false;
                }
                return empty($allowed) || in_array($k, $allowed);
            });
                return;
            }

            try {
                $layoutData = safe_cache_remember('geminia_user_layout_v2_' . $user->id, 600, function () use ($user) {
                    return [
                        'role' => ($user->primary_role ? $user->primary_role->rolename : null) ?? '—',
                        'allowed' => $user->getAllowedModules(),
                    ];
                });
                $view->with('currentUserName', $user->full_name);
                $initials = strtoupper(substr($user->first_name ?? '', 0, 1) . substr($user->last_name ?? '', 0, 1));
                $view->with('currentUserInitials', $initials ?: strtoupper(substr($user->full_name ?? 'U', 0, 2)));
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
            } catch (\Throwable $e) {
                $view->with('currentUserName', 'User');
                $view->with('currentUserRole', '—');
                $view->with('currentUserEmail', '');
                $view->with('currentUserInitials', 'U');
                try {
                    $view->with('allowedModules', app(\App\Services\ModuleService::class)->getEnabledModuleKeys());
                } catch (\Throwable $e2) {
                    $view->with('allowedModules', []);
                }
                $view->with('pbxCanCall', false);
                $view->with('pbxDefaultExtension', '');
            }

            $allowed = $view->getData()['allowedModules'] ?? [];
            $view->with('can', function ($k) use ($allowed, $user) {
                $isAllowedByModule = empty($allowed) || in_array($k, $allowed, true);
                if (!$isAllowedByModule) {
                    return false;
                }

                if (! str_starts_with($k, 'finance.')) {
                    return true;
                }

                if (method_exists($user, 'isAdministrator') && $user->isAdministrator()) {
                    return true;
                }

                $department = strtolower(trim((string) app(\App\Services\UserDepartmentService::class)->getDepartment((int) $user->id)));
                $roleName = strtolower(trim((string) ($user->primary_role->rolename ?? '')));
                $email = strtolower(trim((string) ($user->email1 ?? '')));
                return str_contains($department, 'finance')
                    || str_contains($roleName, 'finance')
                    || str_contains($email, 'finance');
            });
        });

        View::composer('settings', function ($view) {
            try {
                $user = Auth::guard('vtiger')->user();
                $view->with('currentUserName', ($user ? $user->full_name : null) ?? 'User');
                $view->with('currentUserRole', ($user && $user->primary_role ? $user->primary_role->rolename : null) ?? '—');
                $view->with('currentUserEmail', ($user ? $user->email1 : null) ?? '');
                $initials = $user ? (strtoupper(substr($user->first_name ?? '', 0, 1) . substr($user->last_name ?? '', 0, 1)) ?: strtoupper(substr($user->full_name, 0, 2))) : 'U';
                $view->with('currentUserInitials', $initials);
            } catch (\Throwable $e) {
                $view->with('currentUserName', 'User');
                $view->with('currentUserRole', '—');
                $view->with('currentUserEmail', '');
                $view->with('currentUserInitials', 'U');
            }
        });
    }

}

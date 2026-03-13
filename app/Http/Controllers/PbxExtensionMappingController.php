<?php

namespace App\Http\Controllers;

use App\Models\PbxExtensionMapping;
use App\Services\PbxExtensionMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PbxExtensionMappingController extends Controller
{
    public function __construct(
        protected PbxExtensionMappingService $extService
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'extension' => 'required|string|max:32',
            'vtiger_user_id' => 'required|integer|min:1',
        ]);

        $ext = preg_replace('/\D/', '', $validated['extension']);
        if ($ext === '') {
            return redirect($request->get('redirect', route('settings.crm') . '?section=pbx-extension-mapping'))
                ->with('error', 'Extension must contain at least one digit.');
        }

        $user = \Illuminate\Support\Facades\DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $validated['vtiger_user_id'])
            ->select('id', 'first_name', 'last_name', 'user_name')
            ->first();

        if (! $user) {
            return redirect(route('settings.crm', ['section' => 'pbx-extension-mapping']))
                ->with('error', 'User not found.');
        }

        $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->user_name ?? '';

        PbxExtensionMapping::updateOrCreate(
            ['extension' => $ext],
            [
                'vtiger_user_id' => $user->id,
                'user_name' => $userName,
            ]
        );

        Cache::forget('pbx_ext_map:' . $ext);

        return redirect(route('settings.crm', ['section' => 'pbx-extension-mapping']))
            ->with('success', "Extension {$ext} mapped to {$userName}.");
    }

    public function destroy(PbxExtensionMapping $mapping): RedirectResponse
    {
        $ext = $mapping->extension;
        $mapping->delete();
        Cache::forget('pbx_ext_map:' . $ext);

        return redirect()->route('settings.crm', ['section' => 'pbx-extension-mapping'])
            ->with('success', "Mapping for extension {$ext} removed.");
    }

    public function sync(): RedirectResponse
    {
        $count = $this->extService->syncFromVtiger();

        return redirect()->route('settings.crm', ['section' => 'pbx-extension-mapping'])
            ->with('success', $count > 0
                ? "Synced {$count} mapping(s) from vTiger user extensions."
                : 'No extensions found in vTiger user profiles. Add mappings manually.');
    }
}

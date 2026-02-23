<?php

namespace App\Http\Controllers;

use App\Services\LayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LayoutEditorController extends Controller
{
    public function index(LayoutService $layout): View
    {
        $modules = $layout->getEditableModules();
        return view('settings.layout-editor', [
            'modules' => $modules,
            'selectedTabid' => null,
            'layout' => null,
        ]);
    }

    /** @return View|RedirectResponse */
    public function show(Request $request, LayoutService $layout, ?int $tabid = null)
    {
        $tabid = $tabid ?? (int) $request->get('module');
        $modules = $layout->getEditableModules();

        if ($tabid <= 0) {
            return view('settings.layout-editor', [
                'modules' => $modules,
                'selectedTabid' => null,
                'layout' => null,
            ]);
        }

        $layoutData = $layout->getLayoutForModule($tabid);
        if (!$layoutData['tab']) {
            return redirect()->route('settings.layout-editor')->with('error', 'Module not found.');
        }

        return view('settings.layout-editor', [
            'modules' => $modules,
            'selectedTabid' => $tabid,
            'layout' => $layoutData,
        ]);
    }

    public function updateField(Request $request, LayoutService $layout): RedirectResponse
    {
        $request->validate([
            'fieldid' => 'required|integer|min:1',
            'mandatory' => 'nullable|boolean',
            'quickcreate' => 'nullable|integer|in:0,1,2,3',
            'masseditable' => 'nullable|integer|in:0,1',
            'headerfield' => 'nullable|integer|in:0,1',
            'summaryfield' => 'nullable|integer|in:0,1',
            'redirect_tabid' => 'nullable|integer',
        ]);

        $options = [
            'mandatory' => (bool) $request->input('mandatory', false),
            'quickcreate' => (int) $request->input('quickcreate', 0),
            'masseditable' => (int) $request->input('masseditable', 0),
            'headerfield' => (int) $request->input('headerfield', 0),
            'summaryfield' => (int) $request->input('summaryfield', 0),
        ];

        $layout->updateFieldOptions((int) $request->fieldid, $options);

        $tabid = (int) $request->redirect_tabid;
        $url = $tabid > 0
            ? route('settings.layout-editor.show', ['tabid' => $tabid])
            : route('settings.layout-editor');

        return redirect($url)->with('success', 'Field updated.');
    }
}

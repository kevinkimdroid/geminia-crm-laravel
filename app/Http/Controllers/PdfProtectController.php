<?php

namespace App\Http\Controllers;

use App\Services\PdfPasswordProtectorService;
use App\Services\PdfProtectionException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfProtectController extends Controller
{
    public function index(): View
    {
        return view('tools.pdf-protect');
    }

    public function process(Request $request, PdfPasswordProtectorService $protector): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $maxKb = (int) config('pdf-protect.max_upload_kb', 51200);

        $validated = $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:' . $maxKb,
            'user_password' => 'required|string|min:8|max:128',
        ]);

        $binary = file_get_contents($request->file('pdf')->getRealPath());
        if ($binary === false || $binary === '') {
            return back()->withInput()->with('error', 'Could not read the uploaded PDF.');
        }

        try {
            $out = $protector->protectWithBestDefaults($binary, $validated['user_password']);
        } catch (PdfProtectionException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $base = pathinfo($request->file('pdf')->getClientOriginalName(), PATHINFO_FILENAME) ?: 'document';
        $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $base) . '-protected.pdf';

        return response()->streamDownload(function () use ($out) {
            echo $out;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}

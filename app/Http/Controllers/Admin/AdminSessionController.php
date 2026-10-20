<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\PlatformAdmin;
use App\Support\Audit\PlatformAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminSessionController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('platform')->check()) {
            return redirect()->route('admin.churches.index');
        }

        return Inertia::render('admin/login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        Auth::guard('web')->logout();
        $request->session()->forget('current_church_id');
        $request->session()->regenerate();

        /** @var PlatformAdmin $admin */
        $admin = Auth::guard('platform')->user();

        PlatformAudit::record($admin, PlatformAudit::LOGIN);

        return redirect()->route('admin.churches.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

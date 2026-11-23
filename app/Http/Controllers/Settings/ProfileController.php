<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        try {
            $request->user()->fill($request->validated());

            if ($request->user()->isDirty('email')) {
                $request->user()->email_verified_at = null;
            }

            $request->user()->save();

            return to_route('profile.edit')->with('success', 'Profil berhasil diperbarui.');
        } catch (\Exception $e) {
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat memperbarui profil. Silakan coba lagi.');
        }
    }

    /**
     * Nonaktifkan penghapusan profil mandiri pada mode SSO-only.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort(403, 'Penghapusan akun dinonaktifkan pada mode SSO-only.');
    }
}

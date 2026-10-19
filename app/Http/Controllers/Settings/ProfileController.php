<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\MarketingConsent;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'marketing' => MarketingConsent::has($request->user()),
            'marketingText' => MarketingConsent::TEXT,
        ]);
    }

    /** Согласие на рекламные письма: дать или отозвать */
    public function marketing(Request $request): RedirectResponse
    {
        $request->validate(['marketing' => ['required', 'boolean']]);

        $request->boolean('marketing')
            ? MarketingConsent::grant($request->user(), $request, 'settings')
            : MarketingConsent::revoke($request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->boolean('marketing') ? 'Будем присылать новости и предложения.' : 'Больше не будем присылать новости и предложения.',
        ]);

        return to_route('profile.edit');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

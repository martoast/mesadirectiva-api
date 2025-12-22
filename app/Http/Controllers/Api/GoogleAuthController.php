<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     * GET /api/auth/google/redirect
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     * GET /api/auth/google/callback
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if (!$user) {
                return redirect(config('app.frontend_url') . '/login?error=user_not_found');
            }

            if (!$user->is_active) {
                return redirect(config('app.frontend_url') . '/login?error=account_deactivated');
            }

            if (!$user->google_id) {
                $user->update([
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                ]);
            }

            $user->tokens()->delete();
            $token = $user->createToken('auth-token')->plainTextToken;

            return redirect(config('app.frontend_url') . '/auth/callback?token=' . $token);

        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '/login?error=oauth_failed');
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * Double authentification (TOTP — Google Authenticator / Authy / FreeOTP).
 *  - Page « Sécurité » : activation avec QR code + code de confirmation.
 *  - Challenge après login pour les comptes ayant activé la 2FA.
 *  - Réinitialisation d'un compte par un gestionnaire d'utilisateurs.
 */
class TwoFactorController extends Controller
{
    /* ── Page Sécurité (activation / désactivation) ────────── */

    public function show(Request $request)
    {
        $user = $request->user();
        $qrSvg = null;
        $secret = null;

        if (!$user->hasTwoFactor()) {
            $g2fa = new Google2FA();
            // Secret provisoire conservé en session jusqu'à confirmation
            $secret = $request->session()->get('2fa_setup_secret') ?: $g2fa->generateSecretKey(32);
            $request->session()->put('2fa_setup_secret', $secret);

            $otpauth = $g2fa->getQRCodeUrl(
                \App\Models\Setting::get('app.short_name', 'UK') . ' Démat',
                $user->numero_identifiant,
                $secret
            );
            $qrSvg = $this->svgQr($otpauth);
        }

        return view('admin.securite', compact('user', 'qrSvg', 'secret'));
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);
        $user = $request->user();

        if ($user->hasTwoFactor()) {
            return back()->withErrors('La double authentification est déjà active.');
        }

        $secret = $request->session()->get('2fa_setup_secret');
        if (!$secret) {
            return back()->withErrors('Session expirée : rechargez la page et rescannez le QR code.');
        }

        if (!(new Google2FA())->verifyKey($secret, $request->code)) {
            return back()->withErrors('Code incorrect. Vérifiez l\'heure de votre téléphone et réessayez.');
        }

        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = now();
        $user->save();
        $request->session()->forget('2fa_setup_secret');
        $request->session()->put('2fa_passed', true); // il vient de prouver son code

        AuditLog::record('2fa.activee', $user, "2FA activée sur le compte {$user->numero_identifiant}");

        return back()->with('success', 'Double authentification activée. Un code vous sera demandé à chaque connexion.');
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => 'required']);
        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors('Mot de passe incorrect.');
        }

        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        AuditLog::record('2fa.desactivee', $user, "2FA désactivée sur le compte {$user->numero_identifiant}");

        return back()->with('success', 'Double authentification désactivée.');
    }

    /* ── Challenge après login ─────────────────────────────── */

    public function challenge(Request $request)
    {
        $user = $request->user();
        if (!$user->hasTwoFactor() || $request->session()->get('2fa_passed')) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);
        $user = $request->user();

        if (!(new Google2FA())->verifyKey($user->two_factor_secret, $request->code)) {
            AuditLog::record('2fa.echec', $user, "Code 2FA incorrect ({$user->numero_identifiant})");

            return back()->withErrors('Code incorrect ou expiré.');
        }

        $request->session()->put('2fa_passed', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    /* ── Réinitialisation par un gestionnaire ──────────────── */

    public function reset(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin() && !$request->user()->isSuperAdmin(), 403,
            'Seul un super-admin peut réinitialiser la 2FA d\'un super-admin.');

        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        AuditLog::record('2fa.reinitialisee', $user,
            "2FA réinitialisée pour {$user->staff_name} ({$user->numero_identifiant})");

        return back()->with('success', "2FA réinitialisée pour {$user->staff_name}. Le compte se connecte à nouveau sans code.");
    }

    /* ── QR code SVG (aucune dépendance image côté serveur) ── */

    private function svgQr(string $data): string
    {
        $renderer = new ImageRenderer(new RendererStyle(190), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString($data);
    }
}

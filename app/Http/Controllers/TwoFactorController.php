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

        // Sans codes de secours, un téléphone perdu bloque le compte
        $codes = $user->genererCodesSecours();

        AuditLog::record('2fa.activee', $user, "2FA activée sur le compte {$user->numero_identifiant}");

        return back()
            ->with('codes_secours', $codes)
            ->with('success', 'Double authentification activée. Notez vos codes de secours : ils ne seront plus affichés.');
    }

    /**
     * Nouvelle série de codes de secours. Les précédents sont annulés :
     * c'est le geste à faire quand on doute qu'ils soient encore secrets.
     */
    public function regenererCodes(Request $request)
    {
        $request->validate(['password' => 'required'], [], ['password' => 'mot de passe']);
        $user = $request->user();

        if (!$user->hasTwoFactor()) {
            return back()->withErrors("La double authentification n'est pas active sur ce compte.");
        }
        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors('Mot de passe incorrect.');
        }

        $codes = $user->genererCodesSecours();

        AuditLog::record('2fa.codes_regeneres', $user,
            "Codes de secours régénérés pour {$user->numero_identifiant} — les anciens sont annulés");

        return back()
            ->with('codes_secours', $codes)
            ->with('success', 'Nouveaux codes de secours générés. Les anciens ne fonctionnent plus.');
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
        $user->two_factor_recovery_codes = null;   // ils n'ouvriraient plus rien
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
        $request->validate(['code' => 'required|string|max:20']);
        $user = $request->user();
        $saisi = trim($request->code);

        // Six chiffres = code de l'application ; sinon, code de secours
        $estTotp = (bool) preg_match('/^\d{6}$/', $saisi);

        if ($estTotp && (new Google2FA())->verifyKey($user->two_factor_secret, $saisi)) {
            $request->session()->put('2fa_passed', true);

            return redirect()->intended(route('admin.dashboard'));
        }

        if (!$estTotp && $user->consommerCodeSecours($saisi)) {
            $restants = $user->codesSecoursRestants();

            AuditLog::record('2fa.code_secours', $user,
                "Connexion par code de secours ({$user->numero_identifiant}) — {$restants} code(s) restant(s)");

            $request->session()->put('2fa_passed', true);

            // Le prévenir tôt : à zéro code, un téléphone perdu bloque tout
            $alerte = $restants === 0
                ? "Vous avez utilisé votre dernier code de secours. Générez-en de nouveaux dès maintenant."
                : "Code de secours accepté — il ne fonctionnera plus. Il vous en reste {$restants}.";

            return redirect()->intended(route('admin.dashboard'))->with('warning', $alerte);
        }

        AuditLog::record('2fa.echec', $user, "Code 2FA incorrect ({$user->numero_identifiant})");

        return back()->withErrors('Code incorrect ou expiré.');
    }

    /* ── Réinitialisation par un gestionnaire ──────────────── */

    public function reset(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin() && !$request->user()->isSuperAdmin(), 403,
            'Seul un super-admin peut réinitialiser la 2FA d\'un super-admin.');

        // Retirer la 2FA d'un compte plus puissant que le sien, c'est lui
        // ôter sa seconde serrure : même règle que pour son mot de passe.
        abort_unless(\App\Support\Privileges::peutAdministrer($request->user(), $user), 403,
            "Ce compte détient des droits que vous n'avez pas : vous ne pouvez pas réinitialiser sa 2FA.");

        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
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

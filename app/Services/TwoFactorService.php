<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time password (TOTP) two-factor authentication, compatible
 * with common authenticator apps.
 */
class TwoFactorService
{
    public function __construct(private readonly Google2FA $engine = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $url = $this->engine->getQRCodeUrl(setting('organisation_name', config('app.name')), $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        return strlen($code) === 6 && $this->engine->verifyKey($secret, $code, 1);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return Collection::times(8, fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
    }

    /**
     * Accept either a TOTP code or one unused recovery code (which is then
     * consumed).
     */
    public function attempt(User $user, string $code): bool
    {
        if ($user->two_factor_secret && $this->verify($user->two_factor_secret, $code)) {
            return true;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $code = Str::lower(trim($code));

        if ($code !== '' && in_array($code, $codes, true)) {
            $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$code]))])->save();

            return true;
        }

        return false;
    }
}

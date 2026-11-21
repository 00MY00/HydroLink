<?php

declare(strict_types=1);

namespace Modules\HydroLink\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * A Proton account served by hydroxide. The bridge password is kept encrypted (APP_KEY), never in clear text.
 */
class Account extends Model
{
    const PENDING = 'pending';            // waiting for the cron worker
    const RUNNING = 'running';            // hydroxide auth is running
    const WAITING_2FA = 'waiting_2fa';
    const WAITING_CAPTCHA = 'waiting_captcha';
    const NEEDS_PASSWORD = 'needs_password';  // the request expired or a mailbox password is needed
    const CONNECTED = 'connected';
    const ERROR = 'error';
    const REMOVING = 'removing';

    protected $table = 'hydrolink_accounts';

    protected $guarded = ['id'];

    protected $hidden = ['bridge_password'];

    protected $casts = ['paused' => 'boolean'];

    protected $dates = ['checked_at', 'connected_at'];

    public function setBridgePassword(string $plain): void
    {
        $this->bridge_password = encrypt($plain);
    }

    public function bridgePassword(): string
    {
        if (!$this->bridge_password) {
            return '';
        }
        try {
            return (string) decrypt($this->bridge_password);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function mailbox()
    {
        return $this->belongsTo(\App\Mailbox::class, 'mailbox_id');
    }

    /** The person has something to do in the panel (2FA code, verification, password). */
    public function waitsForPerson(): bool
    {
        return in_array($this->status, [self::WAITING_2FA, self::WAITING_CAPTCHA, self::NEEDS_PASSWORD], true);
    }

    public function isBusy(): bool
    {
        return in_array($this->status, [self::PENDING, self::RUNNING, self::WAITING_2FA, self::WAITING_CAPTCHA, self::REMOVING], true);
    }

    public function setError(string $code, string $detail = ''): void
    {
        $this->status = self::ERROR;
        $this->error_code = $code;
        $this->error_detail = $detail !== '' ? mb_substr($detail, 0, 1000) : null;
    }

    public function clearError(): void
    {
        $this->error_code = null;
        $this->error_detail = null;
        $this->captcha_url = null;
    }
}

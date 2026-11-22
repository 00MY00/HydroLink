<?php

declare(strict_types=1);

namespace Modules\HydroLink\Features;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Support\Settings;

/** Keep-alive of the free Proton accounts (Services/KeepAlive/KeepAlive.php), run by hydrolink:tick. */
final class KeepAliveFeature implements Feature
{
    public function key(): string
    {
        return 'keepalive';
    }

    public function order(): int
    {
        return 25;
    }

    public function view(): ?string
    {
        return 'hydrolink::partials.keepalive';
    }

    public function settings(): array
    {
        return [
            'keepalive'        => ['default' => true, 'rules' => 'boolean'],
            'keepalive_method' => ['default' => 'read', 'rules' => 'required|in:read,self,other'],
            'keepalive_days'   => ['default' => 30, 'rules' => 'integer|min:1|max:365'],
            'keepalive_to'     => ['default' => '', 'rules' => 'nullable|email|max:191'],
        ];
    }

    public function schedule($schedule): void
    {
        // run by hydrolink:tick (ServiceFeature), together with the service it needs
    }

    public function status(array $state): array
    {
        if (!Settings::keepAlive()) {
            return ['level' => 'off', 'label' => __('hydrolink::messages.st_off')];
        }
        $failed = Account::whereNotNull('keepalive_code')->whereNotNull('bridge_password')->count();
        if ($failed > 0) {
            return ['level' => 'warning', 'label' => trans_choice('hydrolink::messages.st_alerts', $failed, ['n' => $failed])];
        }
        if (Settings::keepAliveMethod() === 'other' && Settings::keepAliveTo() === '') {
            return ['level' => 'warning', 'label' => __('hydrolink::messages.ka_st_no_address')];
        }

        return ['level' => 'ok', 'label' => __('hydrolink::messages.st_ok')];
    }
}

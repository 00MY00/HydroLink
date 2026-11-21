<?php

declare(strict_types=1);

namespace Modules\HydroLink\Features;

use Modules\HydroLink\Services\Support\Settings;

/** Optional VirusTotal analysis of each new binary. */
final class VirusTotalFeature implements Feature
{
    public function key(): string
    {
        return 'virustotal';
    }

    public function order(): int
    {
        return 50;
    }

    public function view(): ?string
    {
        return 'hydrolink::partials.virustotal';
    }

    public function settings(): array
    {
        return [
            'vt_key'       => ['default' => '', 'rules' => 'nullable|string|max:128|regex:/^[A-Za-z0-9]*$/', 'secret' => true],
            'vt_threshold' => ['default' => 0, 'rules' => 'integer|min:0|max:50'],
        ];
    }

    public function schedule($schedule): void
    {
    }

    public function status(array $state): array
    {
        if (Settings::vtKey() === '') {
            return ['level' => 'off', 'label' => __('hydrolink::messages.st_off')];
        }
        $code = (string) ($state['real']['binary']['last']['code'] ?? '');

        return in_array($code, ['HL-VT-01', 'HL-VT-02', 'HL-BIN-05'], true)
            ? ['level' => 'warning', 'label' => $code]
            : ['level' => 'ok', 'label' => __('hydrolink::messages.st_on')];
    }
}

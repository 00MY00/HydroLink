<?php

declare(strict_types=1);

namespace Modules\HydroLink\Features;

/** hydroxide binary: installed and available versions, update, automatic update. */
final class BinaryFeature implements Feature
{
    public function key(): string
    {
        return 'binary';
    }

    public function order(): int
    {
        return 30;
    }

    public function view(): ?string
    {
        return 'hydrolink::partials.binary';
    }

    public function settings(): array
    {
        return ['auto_update' => ['default' => false, 'rules' => 'boolean']];
    }

    public function schedule($schedule): void
    {
    }

    public function status(array $state): array
    {
        $installed = (string) ($state['binary']['version'] ?? '');
        $last = (array) ($state['real']['binary']['last'] ?? []);
        if ($installed === '') {
            return ['level' => 'error', 'label' => __('hydrolink::messages.st_missing')];
        }
        if (($last['code'] ?? '') !== '') {
            return ['level' => 'warning', 'label' => __('hydrolink::messages.st_check')];
        }
        $latest = (string) ($state['real']['binary']['latest'] ?? '');
        if ($latest !== '' && $latest !== $installed) {
            return ['level' => 'warning', 'label' => __('hydrolink::messages.st_update')];
        }

        return ['level' => 'ok', 'label' => __('hydrolink::messages.st_ok')];
    }
}

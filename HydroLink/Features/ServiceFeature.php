<?php

declare(strict_types=1);

namespace Modules\HydroLink\Features;

use Modules\HydroLink\Services\Env\EnvironmentCheck;

/** hydroxide service and environment: the cron tasks of HydroLink and the status bar of the panel. */
final class ServiceFeature implements Feature
{
    public function key(): string
    {
        return 'service';
    }

    public function order(): int
    {
        return 10;
    }

    public function view(): ?string
    {
        return 'hydrolink::partials.service';
    }

    public function settings(): array
    {
        return [];
    }

    /**
     * Both tasks run in the background so that they never delay FreeScout's own tasks; each one holds its own
     * lock (run/*.lock), so a run that is still going makes the next one end at once.
     */
    public function schedule($schedule): void
    {
        $schedule->command('hydrolink:tick')->everyMinute()->runInBackground();
        $schedule->command('hydrolink:auth')->everyMinute()->runInBackground();
    }

    public function status(array $state): array
    {
        if (EnvironmentCheck::problems($state['real'])) {
            return ['level' => 'error', 'label' => __('hydrolink::messages.st_problem')];
        }
        $status = (string) ($state['real']['service']['status'] ?? 'stopped');
        $map = ['running' => 'ok', 'starting' => 'warning', 'failed' => 'error', 'stopped' => 'off'];

        return ['level' => $map[$status] ?? 'off', 'label' => __('hydrolink::messages.svc_'.$status)];
    }
}

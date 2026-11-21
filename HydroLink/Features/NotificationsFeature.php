<?php

declare(strict_types=1);

namespace Modules\HydroLink\Features;

use Modules\HydroLink\Services\Support\Settings;

/** Notification channels (detected), reminder, return to normal. */
final class NotificationsFeature implements Feature
{
    public function key(): string
    {
        return 'notifications';
    }

    public function order(): int
    {
        return 40;
    }

    public function view(): ?string
    {
        return 'hydrolink::partials.notifications';
    }

    public function settings(): array
    {
        return [
            'channels'        => ['default' => ['mail'], 'rules' => 'array'],
            'reminder_hours'  => ['default' => 24, 'rules' => 'integer|min:0|max:720'],
            'notify_recovery' => ['default' => true, 'rules' => 'boolean'],
        ];
    }

    public function schedule($schedule): void
    {
    }

    public function status(array $state): array
    {
        if (!Settings::channels()) {
            return ['level' => 'off', 'label' => __('hydrolink::messages.st_off')];
        }

        return $state['alerts']
            ? ['level' => 'warning', 'label' => trans_choice('hydrolink::messages.st_alerts', count($state['alerts']), ['n' => count($state['alerts'])])]
            : ['level' => 'ok', 'label' => __('hydrolink::messages.st_ok')];
    }
}

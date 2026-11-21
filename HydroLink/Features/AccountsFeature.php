<?php

declare(strict_types=1);

namespace Modules\HydroLink\Features;

/** Proton accounts: one card per account and the "Add" card. */
final class AccountsFeature implements Feature
{
    public function key(): string
    {
        return 'accounts';
    }

    public function order(): int
    {
        return 20;
    }

    public function view(): ?string
    {
        return 'hydrolink::partials.accounts';
    }

    public function settings(): array
    {
        return [];
    }

    public function schedule($schedule): void
    {
    }

    public function status(array $state): array
    {
        $accounts = $state['accounts'];
        if (!count($accounts)) {
            return ['level' => 'off', 'label' => __('hydrolink::messages.st_none')];
        }
        foreach ($accounts as $account) {
            if ($account->status === 'error' || $account->waitsForPerson()) {
                return ['level' => 'warning', 'label' => __('hydrolink::messages.st_check')];
            }
        }

        return ['level' => 'ok', 'label' => __('hydrolink::messages.st_ok')];
    }
}

<?php

declare(strict_types=1);

namespace Modules\HydroLink\Features;

/**
 * A feature of HydroLink. Adding a feature = adding one class in this folder that implements this interface:
 * the registry finds it, the panel shows its card, its settings are saved by the panel, its tasks are scheduled.
 */
interface Feature
{
    /** Unique key, also the id of its card. */
    public function key(): string;

    /** Position of its card in the panel (small first). */
    public function order(): int;

    /** View of its card (null: no card). The view receives $feature and $state. */
    public function view(): ?string;

    /**
     * Settings saved from its card: key => ['default' => mixed, 'rules' => Laravel validation rules,
     * 'secret' => bool (encrypted, never sent back to the browser)].
     */
    public function settings(): array;

    /** Scheduled tasks (FreeScout's "schedule" filter). */
    public function schedule($schedule): void;

    /** Status of its card: ['level' => ok|warning|error|off, 'label' => short word]. */
    public function status(array $state): array;
}

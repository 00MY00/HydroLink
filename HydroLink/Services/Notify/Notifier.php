<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Notify;

use Modules\HydroLink\Services\Support\ErrorCatalog;
use Modules\HydroLink\Services\Support\Settings;

/** Builds the text of a notification and hands it to every channel chosen in the panel. */
final class Notifier
{
    /** @param string $kind problem | reminder | recovered | test */
    public static function send(string $kind, string $code, string $detail = ''): int
    {
        list($title, $text) = self::compose($kind, $code, $detail);
        $url = self::panelUrl();
        $sent = 0;
        foreach (Settings::channels() as $channel) {
            if (Channels::send($channel, $title, $text, $url)) {
                $sent++;
            }
        }
        try {
            \Eventy::action('hydrolink.alert', $kind, $code, $title, $text);
        } catch (\Throwable $e) {
        }

        return $sent;
    }

    /** @return string[] [title, text] */
    public static function compose(string $kind, string $code, string $detail): array
    {
        $info = ErrorCatalog::describe($code);
        $title = __('hydrolink::messages.notify_'.$kind, ['code' => $code, 'title' => $info['title']]);
        if ($kind === 'recovered' || $kind === 'test') {
            return [$title, __('hydrolink::messages.notify_'.$kind.'_text', ['code' => $code, 'title' => $info['title']])];
        }
        $lines = array_filter([$info['effect'], $info['action'], $detail !== '' ? $detail : null]);

        return [$title, implode("\n", $lines)];
    }

    private static function panelUrl(): string
    {
        try {
            return route('hydrolink.panel');
        } catch (\Throwable $e) {
            return '';
        }
    }
}

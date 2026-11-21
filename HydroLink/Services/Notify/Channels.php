<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Notify;

/**
 * Notification channels, detected on each call:
 *   mail     FreeScout's own alert e-mail to the administrators (+ "alert_recipients"): \MailHelper::sendAlertMail()
 *   webpush  the WebPush module (github.com/altmenorg/freescout-webpush) when it is active: sendToUsers()
 *   others   any module can add one with the Eventy filter "hydrolink.notification_channels":
 *            $channels['key'] = ['label' => 'Name', 'send' => function (string $title, string $text, string $url) {...}]
 * Every notification is also fired as the Eventy action "hydrolink.alert" ($kind, $code, $title, $text).
 */
final class Channels
{
    const WEBPUSH_SERVICE = '\Modules\WebPush\Services\WebPush';

    /** @return array key => ['label' => string, 'send' => callable] */
    public static function all(): array
    {
        $channels = [
            'mail' => ['label' => __('hydrolink::messages.channel_mail'), 'send' => [self::class, 'sendMail']],
        ];
        if (self::webPushAvailable()) {
            $channels['webpush'] = ['label' => 'WebPush', 'send' => [self::class, 'sendWebPush']];
        }
        try {
            $extra = \Eventy::filter('hydrolink.notification_channels', []);
        } catch (\Throwable $e) {
            $extra = [];
        }
        foreach ((array) $extra as $key => $channel) {
            if (is_string($key) && !isset($channels[$key]) && is_array($channel) && isset($channel['send']) && is_callable($channel['send'])) {
                $channels[$key] = ['label' => (string) ($channel['label'] ?? $key), 'send' => $channel['send']];
            }
        }

        return $channels;
    }

    public static function labels(): array
    {
        return array_map(function ($c) {
            return $c['label'];
        }, self::all());
    }

    public static function send(string $key, string $title, string $text, string $url): bool
    {
        $channels = self::all();
        if (!isset($channels[$key])) {
            return false;
        }
        try {
            call_user_func($channels[$key]['send'], $title, $text, $url);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('[HydroLink] notification '.$key.': '.$e->getMessage());

            return false;
        }
    }

    public static function sendMail(string $title, string $text, string $url): void
    {
        \MailHelper::sendAlertMail(nl2br(e($text)).'<br><br><a href="'.e($url).'">'.e($url).'</a>', $title);
    }

    public static function webPushAvailable(): bool
    {
        try {
            return \App\Module::isActive('webpush') && class_exists(self::WEBPUSH_SERVICE) && method_exists(self::WEBPUSH_SERVICE, 'sendToUsers');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** To the active administrators, with WebPush's payload keys (title, body, url, tag). */
    public static function sendWebPush(string $title, string $text, string $url): void
    {
        $payload = ['title' => mb_substr($title, 0, 120), 'body' => mb_substr($text, 0, 500), 'url' => $url, 'tag' => 'hydrolink'];
        $items = [];
        foreach (\App\User::where('role', \App\User::ROLE_ADMIN)->where('status', \App\User::STATUS_ACTIVE)->pluck('id') as $id) {
            $items[] = [(int) $id, $payload];
        }
        if ($items) {
            $service = self::WEBPUSH_SERVICE;
            $service::sendToUsers($items);
        }
    }
}

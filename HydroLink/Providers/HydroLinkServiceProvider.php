<?php

namespace Modules\HydroLink\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\HydroLink\Features\Registry;
use Modules\HydroLink\Services\Accounts\AccountService;

/**
 * HydroLink: free Proton Mail accounts in FreeScout through a local hydroxide bridge run by FreeScout's cron.
 *
 * Nothing of FreeScout or of another module is modified. The module adds its routes, views, translations, two tables
 * and its commands, and listens to FreeScout hooks (Eventy):
 *   menu.manage.after_mailboxes (resources/views/layouts/app.blade.php) -> "HydroLink" in Manage (administrators)
 *   refresh.rail_items          (Refresh module, optional)              -> "HydroLink" in Refresh's left bar
 *   schedule                    (app/Console/Kernel.php)                -> hydrolink:tick and hydrolink:auth
 *   mailbox.in_active           (app/Mailbox.php, isInActive())         -> a paused account is not fetched
 *   updateguard.health_check    (UpdateGuard module, optional)          -> hydroxide, cron and accounts in its report
 *   fetch_emails.should_save_thread (app/Console/Commands/FetchEmails.php) -> keep-alive e-mails are not tickets
 *
 * Updates: module.json declares latestVersionUrl / latestVersionZipUrl (FreeScout's Modules page and UpdateGuard);
 * hydrolink:health is the health check UpdateGuard runs after an update.
 */
class HydroLinkServiceProvider extends ServiceProvider
{
    const ALIAS = 'hydrolink';

    protected $defer = false;

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', self::ALIAS);
        $this->commands([
            \Modules\HydroLink\Console\TickCommand::class,
            \Modules\HydroLink\Console\AuthCommand::class,
            \Modules\HydroLink\Console\UpdateBinaryCommand::class,
            \Modules\HydroLink\Console\DiagnoseCommand::class,
            \Modules\HydroLink\Console\HealthCommand::class,
            \Modules\HydroLink\Console\UninstallCommand::class,
        ]);
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', self::ALIAS);
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', self::ALIAS);
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');

        $this->registerMenu();
        $this->registerSchedule();
        $this->registerPause();
        $this->registerUpdateGuard();
        $this->registerKeepAliveFilter();
    }

    /** E-mails of the keep-alive (sent to the account itself or to another FreeScout mailbox) are not tickets. */
    protected function registerKeepAliveFilter()
    {
        \Eventy::addFilter('fetch_emails.should_save_thread', function ($save, $data = []) {
            try {
                if ($save !== false && \Modules\HydroLink\Services\KeepAlive\KeepAlive::isOwnMessage((string) ($data['subject'] ?? ''))) {
                    return false;   // FreeScout marks it read and skips it (app/Console/Commands/FetchEmails.php)
                }
            } catch (\Throwable $e) {
                // never block the fetching of e-mails
            }

            return $save;
        }, 20, 2);
    }

    /**
     * UpdateGuard (optional): its probe adds the checks returned by the "updateguard.health_check" filter to the
     * health report of the module. State of hydroxide, cron and accounts, PASS or WARN only (never a rollback).
     * The native check is hydrolink:health (module.json "healthCommand").
     */
    protected function registerUpdateGuard()
    {
        \Eventy::addFilter('updateguard.health_check', function ($checks, $name = '', $alias = '') {
            if (strtolower((string) $alias) !== self::ALIAS && (string) $name !== 'HydroLink') {
                return $checks;
            }
            try {
                return array_merge((array) $checks, \Modules\HydroLink\Services\Health\ModuleHealth::runtime());
            } catch (\Throwable $e) {
                return $checks;
            }
        }, 20, 3);
    }

    /** Version of the installed module (module.json), '1' if unreadable. */
    public static function version(): string
    {
        static $version = null;
        if ($version === null) {
            $version = (string) (json_decode((string) @file_get_contents(__DIR__.'/../module.json'), true)['version'] ?? '1');
        }

        return $version;
    }

    protected function registerMenu()
    {
        \Eventy::addAction('menu.manage.after_mailboxes', function () {
            try {
                $user = auth()->user();
                if ($user && $user->isAdmin()) {
                    $active = \Route::is('hydrolink.*') ? 'active' : '';
                    echo '<li class="'.$active.'"><a href="'.e(route('hydrolink.panel')).'">HydroLink</a></li>';
                }
            } catch (\Throwable $e) {
                // optional entry: never break the layout
            }
        });

        // Refresh's left bar (extension point of the Refresh module, also used by WorkflowPacks and UpdateGuard;
        // ignored when Refresh is absent). The Manage menu entry above stays for FreeScout without Refresh.
        \Eventy::addFilter('refresh.rail_items', function ($items) {
            try {
                $user = auth()->user();
                if ($user && $user->isAdmin()) {
                    $items[] = [
                        'url'    => route('hydrolink.panel'),
                        'label'  => 'HydroLink',
                        // ?v=: a new version is a new address, so no browser or proxy keeps an old icon
                        'icon'   => asset(\Module::getPublicPath(self::ALIAS).'/img/module.svg').'?v='.rawurlencode(self::version()),
                        'active' => \Route::is('hydrolink.*'),
                        'order'  => 80,
                    ];
                }
            } catch (\Throwable $e) {
                // optional entry
            }

            return $items;
        });

        // Settings menu of a mailbox (cog of the mailbox, resources/views/mailboxes/settings_menu.blade.php). Also shown
        // on phones and tablets, where Refresh hides its left bar.
        \Eventy::addAction('mailboxes.settings.menu', function ($mailbox) {
            try {
                $user = auth()->user();
                if ($user && $user->isAdmin()) {
                    $active = \Route::is('hydrolink.*') ? 'active' : '';
                    echo '<li class="'.$active.'"><a href="'.e(route('hydrolink.panel')).'">'
                        .'<i class="glyphicon glyphicon-link"></i> HydroLink</a></li>';
                }
            } catch (\Throwable $e) {
                // optional entry
            }
        }, 40);
    }

    protected function registerSchedule()
    {
        \Eventy::addFilter('schedule', function ($schedule) {
            try {
                Registry::schedule($schedule);
            } catch (\Throwable $e) {
                // no HydroLink task rather than a broken scheduler
            }

            return $schedule;
        });
    }

    protected function registerPause()
    {
        \Eventy::addFilter('mailbox.in_active', function ($value, $mailbox) {
            if ($mailbox && in_array((int) $mailbox->id, AccountService::pausedMailboxIds(), true)) {
                return false;
            }

            return $value;
        }, 20, 2);
    }
}

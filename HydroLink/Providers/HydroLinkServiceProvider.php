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
                        'icon'   => asset(\Module::getPublicPath(self::ALIAS).'/img/module.svg'),
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

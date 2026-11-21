<?php

namespace Modules\HydroLink\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\HydroLink\Services\Process\HydroxideProcess;
use Modules\HydroLink\Services\Support\Paths;

/**
 * Base of the module's tests, run inside a FreeScout installation (Modules/HydroLink), each test in a rolled back
 * transaction and with its own storage folder. Support/fake-hydroxide.php stands in for hydroxide (no Proton account).
 */
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use DatabaseTransactions;

    /** @var array notifications received by the "test" channel */
    public static $sent = [];

    public function createApplication()
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Paths::$base = sys_get_temp_dir().'/hydrolink-test-'.bin2hex(random_bytes(4));
        Paths::ensure();
        self::$sent = [];
        \Eventy::addFilter('hydrolink.notification_channels', function ($channels) {
            $channels['test'] = ['label' => 'Test', 'send' => function ($title, $text, $url) {
                TestCase::$sent[] = $title;
            }];

            return $channels;
        });
        \Modules\HydroLink\Services\Support\Settings::set('channels', ['test']);
    }

    protected function tearDown(): void
    {
        if (HydroxideProcess::running()) {
            HydroxideProcess::stop();
        }
        exec('rm -rf '.escapeshellarg((string) Paths::$base));
        Paths::$base = null;
        parent::tearDown();
        // FreeScout's application installs its own handlers at each boot
        restore_error_handler();
        restore_exception_handler();
    }

    /** Installs the test double as the hydroxide binary. */
    protected function installFake(): void
    {
        copy(__DIR__.'/Support/fake-hydroxide.php', Paths::bin());
        chmod(Paths::bin(), 0700);
        file_put_contents(Paths::binMeta(), json_encode(['version' => 'test-1']));
    }

    protected function admin()
    {
        $user = \App\User::where('role', \App\User::ROLE_ADMIN)->first();
        if (!$user) {
            $user = new \App\User();
            $user->fill(['first_name' => 'Test', 'last_name' => 'Admin', 'email' => 'admin-'.uniqid().'@example.test', 'password' => bcrypt(uniqid('', true)), 'role' => \App\User::ROLE_ADMIN]);
            $user->save();
        }

        return $user;
    }
}

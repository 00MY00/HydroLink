<?php

namespace Modules\HydroLink\Tests\Feature;

use Modules\HydroLink\Services\Binary\BinaryManager;
use Modules\HydroLink\Services\Health\Probes;
use Modules\HydroLink\Services\Process\HydroxideProcess;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Tests\TestCase;

/**
 * With the hydroxide binary built by the CI (HL_REAL_BINARY): it passes the self test, "serve" starts with the
 * flags HydroLink uses (CardDAV off, 127.0.0.1) and answers IMAP and SMTP locally. No Proton account involved.
 */
class RealBinaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $path = (string) getenv('HL_REAL_BINARY');
        if ($path === '' || !is_file($path)) {
            $this->markTestSkipped('HL_REAL_BINARY not set');
        }
        copy($path, Paths::bin());
        chmod(Paths::bin(), 0700);
    }

    public function testSelfTestAndServe()
    {
        $this->assertTrue(BinaryManager::selfTest(Paths::bin()));
        $start = HydroxideProcess::start();
        $this->assertTrue($start['ok'], $start['error']);
        $this->assertTrue(HydroxideProcess::waitReady(20));
        $this->assertTrue(HydroxideProcess::running());
        $this->assertTrue(Probes::smtp(1025)['ok'], 'EHLO');
        $this->assertTrue(Probes::imap(1143)['ok'], 'IMAP greeting');
        $this->assertFalse(Probes::imapLogin(1143, 'nobody@example.test', base64_encode(random_bytes(32)))['ok'], 'unknown user refused');
        $this->assertFalse(\Modules\HydroLink\Services\Process\Ports::listening(8080) && strpos((string) @file_get_contents(Paths::serveLog()), 'CardDAV') !== false, 'CardDAV disabled');
        HydroxideProcess::stop();
        $this->assertFalse(HydroxideProcess::running());
    }
}

<?php

declare(strict_types=1);

namespace Modules\HydroLink\Http\Controllers;

use Illuminate\Http\Request;
use Modules\HydroLink\Features\Registry;
use Modules\HydroLink\Http\Requests\SettingRequest;
use Modules\HydroLink\Services\Notify\Channels;
use Modules\HydroLink\Services\Notify\Notifier;
use Modules\HydroLink\Services\Support\Settings;
use Modules\HydroLink\Services\VirusTotal\VirusTotalClient;

/** Automatic save of the settings of the cards, and the two "Test" buttons. */
class SettingsController extends Controller
{
    public function save(SettingRequest $request)
    {
        $key = (string) $request->input('key');
        $definition = Registry::settings()[$key];
        $value = $request->input('value');
        if (!empty($definition['secret'])) {
            Settings::setSecret($key, (string) $value);
        } elseif ($key === 'channels') {
            Settings::set($key, array_values(array_intersect((array) $value, array_keys(Channels::all()))));
        } elseif (is_bool($definition['default'])) {
            Settings::set($key, filter_var($value, FILTER_VALIDATE_BOOLEAN));
        } elseif (is_int($definition['default'])) {
            Settings::set($key, (int) $value);
        } else {
            Settings::set($key, $value);
        }

        return $this->done($request, __('hydrolink::messages.saved'));
    }

    public function testNotification(Request $request)
    {
        $sent = Notifier::send('test', 'HL-ENV-05');

        return $sent > 0
            ? $this->done($request, trans_choice('hydrolink::messages.test_sent', $sent, ['n' => $sent]))
            : $this->fail($request, __('hydrolink::messages.test_none'));
    }

    public function testVirusTotal(Request $request)
    {
        $key = Settings::vtKey();
        if ($key === '') {
            return $this->fail($request, __('hydrolink::messages.vt_no_key'));
        }
        $result = (new VirusTotalClient($key))->test();

        return $result === 'valid'
            ? $this->done($request, __('hydrolink::messages.vt_valid'))
            : $this->fail($request, __('hydrolink::messages.vt_'.$result));
    }
}

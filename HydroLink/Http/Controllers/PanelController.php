<?php

declare(strict_types=1);

namespace Modules\HydroLink\Http\Controllers;

use Illuminate\Http\Request;
use Modules\HydroLink\Features\Registry;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\PanelState;

/** The panel page and the service / binary requests (written for the cron, never run here). */
class PanelController extends Controller
{
    public function index()
    {
        $version = \Modules\HydroLink\Providers\HydroLinkServiceProvider::version();

        return view('hydrolink::panel', ['state' => PanelState::build(), 'features' => Registry::all(), 'version' => $version]);
    }

    public function state()
    {
        return response()->json(PanelState::json());
    }

    public function service(Request $request)
    {
        $action = (string) $request->input('action');
        if ($action === 'start') {
            State::want(['running' => true, 'restart' => time()]);
        } elseif ($action === 'stop') {
            State::want(['running' => false]);
        } elseif ($action === 'restart') {
            State::want(['running' => true, 'restart' => time()]);
        } else {
            return $this->fail($request, __('hydrolink::messages.bad_request'));
        }

        return $this->done($request, __('hydrolink::messages.queued_'.$action));
    }

    public function binary(Request $request)
    {
        State::want(['binary_request' => time()]);

        return $this->done($request, __('hydrolink::messages.queued_binary'));
    }
}

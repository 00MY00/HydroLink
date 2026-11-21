<?php

declare(strict_types=1);

namespace Modules\HydroLink\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Modules\HydroLink\Services\Support\PanelState;

/** Thin controllers: validate, call one service, answer JSON (fetch) or redirect with a message (no JavaScript). */
abstract class Controller extends BaseController
{
    protected function done(Request $request, string $message, bool $ok = true, int $status = 200)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => $ok, 'message' => $message, 'state' => $ok ? PanelState::json() : null], $ok ? 200 : $status);
        }
        \Session::flash($ok ? 'flash_success_floating' : 'flash_error_floating', $message);

        return redirect()->route('hydrolink.panel');
    }

    protected function fail(Request $request, string $message, int $status = 422)
    {
        return $this->done($request, $message, false, $status);
    }
}

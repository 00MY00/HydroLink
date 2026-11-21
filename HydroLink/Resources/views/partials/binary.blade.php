@php
    $bin = $state['binary'];
    $rb = $state['real']['binary'] ?? [];
    $last = $rb['last'] ?? [];
    $asked = (int) ($state['desired']['binary_request'] ?? 0) > (int) ($rb['request_done'] ?? 0);
    $vtKey = \Modules\HydroLink\Services\Support\Settings::vtKey() !== '';
@endphp
<section class="hl-card" id="hl-card-binary">
    @include('hydrolink::partials.card-head', ['title' => __('hydrolink::messages.binary'), 'status' => $status, 'id' => 'binary'])
    <div class="hl-tiles">
        <div class="hl-tile"><span class="hl-tile-k">{{ __('hydrolink::messages.installed') }}</span><span class="hl-tile-v">{{ $bin['version'] ?? '-' }}</span></div>
        <div class="hl-tile"><span class="hl-tile-k">{{ __('hydrolink::messages.available') }}</span><span class="hl-tile-v">{{ ($rb['latest'] ?? '') ?: '-' }}</span></div>
    </div>
    @if (($last['result'] ?? '') === 'pending_vt')
        <p class="hl-step"><span class="hl-spinner" aria-hidden="true"></span>{{ __('hydrolink::messages.vt_waiting') }}</p>
    @endif
    @if (($last['code'] ?? '') !== '')
        @include('hydrolink::partials.error', ['code' => $last['code'], 'detail' => $last['detail'] ?? ''])
    @endif
    <p class="hl-hint">{{ $vtKey ? __('hydrolink::messages.vt_step_on') : __('hydrolink::messages.vt_step_off') }}</p>
    <div class="hl-actions">
        <form method="post" action="{{ route('hydrolink.binary') }}" class="hl-form" data-hl-ajax data-hl-reload>
            {{ csrf_field() }}
            <button type="submit" class="hl-btn hl-btn-primary" @if ($asked) disabled aria-describedby="hl-why-bin" @endif>{{ empty($bin) ? __('hydrolink::messages.install') : __('hydrolink::messages.update') }}</button>
        </form>
        @if ($asked)<span class="hl-why" id="hl-why-bin">{{ __('hydrolink::messages.why_queued') }}</span>@endif
    </div>
    <label class="hl-switch">
        <input type="checkbox" data-hl-setting="auto_update" @if (\Modules\HydroLink\Services\Support\Settings::autoUpdate()) checked @endif>
        <span class="hl-switch-ui" aria-hidden="true"></span><span>{{ __('hydrolink::messages.auto_update') }}</span>
    </label>
</section>

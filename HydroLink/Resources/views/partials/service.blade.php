@php
    $svc = $state['real']['service'] ?? [];
    $running = ($svc['status'] ?? '') === 'running';
    $wantRunning = !empty($state['desired']['running']);
    $noAccount = !\Modules\HydroLink\Services\Accounts\AccountService::servable();
    $noBinary = empty($state['binary']);
    $ports = \Modules\HydroLink\Services\Env\EnvironmentCheck::ports();
@endphp
<section class="hl-card hl-card-wide" id="hl-card-service">
    @include('hydrolink::partials.card-head', ['title' => __('hydrolink::messages.service'), 'status' => $status, 'id' => 'service'])
    <div class="hl-tiles">
        <div class="hl-tile"><span class="hl-tile-k">IMAP</span><span class="hl-tile-v">127.0.0.1:{{ $ports['imap'] }}</span></div>
        <div class="hl-tile"><span class="hl-tile-k">SMTP</span><span class="hl-tile-v">127.0.0.1:{{ $ports['smtp'] }}</span></div>
        <div class="hl-tile"><span class="hl-tile-k">{{ __('hydrolink::messages.cron') }}</span><span class="hl-tile-v">{{ \Modules\HydroLink\Services\Process\State::cronAlive($state['real']) ? __('hydrolink::messages.cron_ok') : __('hydrolink::messages.cron_missing') }}</span></div>
    </div>
    @foreach (($svc['errors'] ?? []) as $code)
        @include('hydrolink::partials.error', ['code' => $code])
    @endforeach
    <div class="hl-actions">
        <form method="post" action="{{ route('hydrolink.service') }}" class="hl-form" data-hl-ajax data-hl-reload>
            {{ csrf_field() }}
            @if ($wantRunning)
                <button type="submit" name="action" value="restart" class="hl-btn" @if ($noAccount || $noBinary) disabled @endif>{{ __('hydrolink::messages.restart') }}</button>
                <button type="submit" name="action" value="stop" class="hl-btn hl-btn-quiet">{{ __('hydrolink::messages.stop') }}</button>
            @else
                <button type="submit" name="action" value="start" class="hl-btn hl-btn-primary">{{ __('hydrolink::messages.start') }}</button>
            @endif
        </form>
        @if ($wantRunning && ($noAccount || $noBinary))
            <span class="hl-why">{{ $noBinary ? __('hydrolink::messages.why_no_binary') : __('hydrolink::messages.why_no_account') }}</span>
        @endif
    </div>
    <details class="hl-more">
        <summary>{{ __('hydrolink::messages.env_details') }}</summary>
        @php($env = $state['real']['env'] ?? [])
        <ul class="hl-checks">
            <li>{{ __('hydrolink::messages.env_proc_open') }} : <b>{{ !empty($env['proc_open']) ? 'OK' : '-' }}</b></li>
            <li>{{ __('hydrolink::messages.env_exec') }} : <b>{{ !empty($env['exec']) ? 'OK' : '-' }}</b></li>
            <li>{{ __('hydrolink::messages.env_writable') }} : <b>{{ !empty($env['writable']) ? 'OK' : '-' }}</b></li>
            <li>{{ __('hydrolink::messages.env_arch') }} : <b>{{ ($env['arch'] ?? '') ?: (($env['machine'] ?? '') ?: '-') }}</b></li>
        </ul>
    </details>
</section>

@php
    $S = \Modules\HydroLink\Services\Support\Settings::class;
    $key = $S::vtKey();
@endphp
<section class="hl-card" id="hl-card-virustotal">
    @include('hydrolink::partials.card-head', ['title' => 'VirusTotal', 'status' => $status, 'id' => 'virustotal'])
    <label class="hl-field"><span>{{ __('hydrolink::messages.vt_key') }}</span>
        <input type="password" data-hl-setting="vt_key" data-hl-secret autocomplete="off" maxlength="128"
               placeholder="{{ $key !== '' ? \Modules\HydroLink\Services\Support\Secrets::hint($key).' ('.__('hydrolink::messages.vt_key_set').')' : __('hydrolink::messages.optional') }}"></label>
    <label class="hl-field hl-field-small"><span>{{ __('hydrolink::messages.vt_threshold') }}</span>
        <input type="number" min="0" max="50" step="1" data-hl-setting="vt_threshold" value="{{ $S::vtThreshold() }}"></label>
    <p class="hl-hint">{{ __('hydrolink::messages.vt_hint') }}</p>
    <div class="hl-actions">
        <form method="post" action="{{ route('hydrolink.vt.test') }}" class="hl-form" data-hl-ajax>
            {{ csrf_field() }}
            <button type="submit" class="hl-btn" @if ($key === '') disabled aria-describedby="hl-why-vt" @endif>{{ __('hydrolink::messages.test') }}</button>
        </form>
        @if ($key === '')<span class="hl-why" id="hl-why-vt">{{ __('hydrolink::messages.vt_no_key') }}</span>@endif
        @if ($key !== '')
            <button type="button" class="hl-btn hl-btn-quiet" data-hl-clear="vt_key">{{ __('hydrolink::messages.vt_remove') }}</button>
        @endif
    </div>
</section>

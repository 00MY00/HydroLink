@php
    $S = \Modules\HydroLink\Services\Support\Settings::class;
    $method = $S::keepAliveMethod();
@endphp
<section class="hl-card" id="hl-card-keepalive">
    @include('hydrolink::partials.card-head', ['title' => __('hydrolink::messages.ka_title'), 'status' => $status, 'id' => 'keepalive'])
    <p class="hl-hint">{{ __('hydrolink::messages.ka_intro') }}</p>
    <label class="hl-switch">
        <input type="checkbox" data-hl-setting="keepalive" @if ($S::keepAlive()) checked @endif>
        <span class="hl-switch-ui" aria-hidden="true"></span><span>{{ __('hydrolink::messages.ka_enable') }}</span>
    </label>
    <fieldset class="hl-stack hl-choices">
        <legend class="hl-legend">{{ __('hydrolink::messages.ka_method') }}</legend>
        @foreach (['read', 'self', 'other'] as $m)
            <label class="hl-choice">
                <input type="radio" name="hl-keepalive-method" value="{{ $m }}" data-hl-setting="keepalive_method" @if ($method === $m) checked @endif>
                <span><b>{{ __('hydrolink::messages.ka_m_'.$m) }}</b><small>{{ __('hydrolink::messages.ka_m_'.$m.'_hint') }}</small></span>
            </label>
        @endforeach
    </fieldset>
    <label class="hl-field"><span>{{ __('hydrolink::messages.ka_to') }}</span>
        <input type="email" maxlength="191" data-hl-setting="keepalive_to" data-hl-onchange value="{{ $S::keepAliveTo() }}" placeholder="name@example.com" autocomplete="off"></label>
    <label class="hl-field hl-field-small"><span>{{ __('hydrolink::messages.ka_days') }}</span>
        <input type="number" min="1" max="365" step="1" data-hl-setting="keepalive_days" value="{{ $S::keepAliveDays() }}"></label>
    <details class="hl-more">
        <summary>{{ __('hydrolink::messages.ka_policy') }}</summary>
        <p class="hl-hint">{{ __('hydrolink::messages.ka_policy_text') }}</p>
    </details>
</section>

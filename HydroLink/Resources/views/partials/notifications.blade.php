@php
    $S = \Modules\HydroLink\Services\Support\Settings::class;
    $chosen = $S::channels();
@endphp
<section class="hl-card" id="hl-card-notifications">
    @include('hydrolink::partials.card-head', ['title' => __('hydrolink::messages.notifications'), 'status' => $status, 'id' => 'notifications'])
    <fieldset class="hl-stack">
        <legend class="hl-legend">{{ __('hydrolink::messages.channels') }}</legend>
        @foreach (\Modules\HydroLink\Services\Notify\Channels::labels() as $key => $label)
            <label class="hl-switch">
                <input type="checkbox" data-hl-setting="channels" data-hl-multi value="{{ $key }}" @if (in_array($key, $chosen, true)) checked @endif>
                <span class="hl-switch-ui" aria-hidden="true"></span><span>{{ $label }}</span>
            </label>
        @endforeach
    </fieldset>
    <label class="hl-field hl-field-small"><span>{{ __('hydrolink::messages.reminder') }}</span>
        <input type="number" min="0" max="720" step="1" data-hl-setting="reminder_hours" value="{{ $S::reminderHours() }}"></label>
    <label class="hl-switch">
        <input type="checkbox" data-hl-setting="notify_recovery" @if ($S::notifyRecovery()) checked @endif>
        <span class="hl-switch-ui" aria-hidden="true"></span><span>{{ __('hydrolink::messages.notify_recovery') }}</span>
    </label>
    @if ($state['alerts'])
        <details class="hl-more" open>
            <summary>{{ __('hydrolink::messages.active_alerts') }}</summary>
            @foreach ($state['alerts'] as $alert)
                @include('hydrolink::partials.error', ['code' => $alert->code, 'detail' => $alert->detail])
            @endforeach
        </details>
    @endif
    <div class="hl-actions">
        <form method="post" action="{{ route('hydrolink.notify.test') }}" class="hl-form" data-hl-ajax>
            {{ csrf_field() }}
            <button type="submit" class="hl-btn">{{ __('hydrolink::messages.test') }}</button>
        </form>
    </div>
</section>

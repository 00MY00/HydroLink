<section class="hl-card hl-card-wide" id="hl-card-add">
    <div class="hl-card-head"><h2>{{ __('hydrolink::messages.add_account') }}</h2></div>
    @if (empty($state['binary']))
        <p class="hl-why">{{ __('hydrolink::messages.why_no_binary') }}</p>
    @endif
    <form method="post" action="{{ route('hydrolink.accounts.store') }}" class="hl-form hl-add-form" data-hl-ajax data-hl-reload autocomplete="off">
        {{ csrf_field() }}
        <label class="hl-field"><span>{{ __('hydrolink::messages.address') }}</span>
            <input type="email" name="email" required maxlength="191" placeholder="user@example.com" autocomplete="off"></label>
        @include('hydrolink::partials.login-fields')
        <button type="submit" class="hl-btn hl-btn-primary" @if (empty($state['binary'])) disabled @endif>{{ __('hydrolink::messages.connect') }}</button>
        <p class="hl-hint">{{ __('hydrolink::messages.add_hint') }}</p>
    </form>
</section>

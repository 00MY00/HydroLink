@php
    $view = \Modules\HydroLink\Services\Support\AccountView::class;
    $pill = $view::pill($account);
    $mailbox = $account->mailbox_id ? \App\Mailbox::find($account->mailbox_id) : null;
    $pauseWhy = $view::pauseBlocked($account);
    $reconnectWhy = $view::reconnectBlocked($account);
@endphp
<section class="hl-card" id="hl-account-{{ $account->id }}" data-account="{{ $account->id }}" data-status="{{ $account->status }}" data-paused="{{ $account->paused ? 1 : 0 }}">
    <div class="hl-card-head">
        <h2 class="hl-ellipsis" title="{{ $account->name ?: $account->email }}">{{ $account->name ?: $account->email }}</h2>
        @include('hydrolink::partials.pill', ['s' => $pill, 'id' => 'account-'.$account->id])
    </div>
    @if ($account->name || $mailbox)
        <p class="hl-sub hl-ellipsis">
            @if ($account->name)<span class="hl-email">{{ $account->email }}</span>@endif
            @if ($account->name && $mailbox) · @endif
            @if ($mailbox)<a href="{{ route('mailboxes.update', ['id' => $mailbox->id]) }}">{{ __('hydrolink::messages.mailbox_link') }}</a>@endif
        </p>
    @endif

    @if (in_array($account->status, ['pending', 'running'], true))
        <p class="hl-step"><span class="hl-spinner" aria-hidden="true"></span>{{ __('hydrolink::messages.step_'.$account->status) }}</p>
    @elseif ($account->status === 'waiting_2fa')
        <form method="post" action="{{ route('hydrolink.accounts.totp', ['id' => $account->id]) }}" class="hl-form hl-inline" data-hl-ajax>
            {{ csrf_field() }}
            <label class="hl-field"><span>{{ __('hydrolink::messages.totp') }}</span>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6,8}" maxlength="8" required autocomplete="one-time-code" autofocus></label>
            <button type="submit" class="hl-btn hl-btn-primary">{{ __('hydrolink::messages.send') }}</button>
        </form>
    @elseif ($account->status === 'waiting_captcha')
        <div class="hl-stack">
            <p class="hl-step">{{ __('hydrolink::messages.captcha_step') }}</p>
            @if ($account->captcha_url)
                <a class="hl-btn" href="{{ $account->captcha_url }}" target="_blank" rel="noopener noreferrer">{{ __('hydrolink::messages.captcha_open') }}</a>
            @endif
            <form method="post" action="{{ route('hydrolink.accounts.verified', ['id' => $account->id]) }}" class="hl-form" data-hl-ajax>
                {{ csrf_field() }}
                <button type="submit" class="hl-btn hl-btn-primary">{{ __('hydrolink::messages.captcha_done') }}</button>
            </form>
        </div>
    @endif

    @if ($account->error_code)
        @include('hydrolink::partials.error', ['code' => $account->error_code, 'detail' => $account->error_detail])
    @endif

    @if (in_array($account->status, ['error', 'needs_password', 'connected'], true))
        <details class="hl-more" @if ($account->status !== 'connected') open @endif>
            <summary>{{ __('hydrolink::messages.reconnect') }}</summary>
            <form method="post" action="{{ route('hydrolink.accounts.reconnect', ['id' => $account->id]) }}" class="hl-form hl-stack" data-hl-ajax data-hl-reload autocomplete="off">
                {{ csrf_field() }}
                @include('hydrolink::partials.login-fields', ['openMailbox' => $account->error_code === 'HL-AUTH-06'])
                <button type="submit" class="hl-btn hl-btn-primary" @if ($reconnectWhy) disabled @endif>{{ __('hydrolink::messages.reconnect') }}</button>
                @if ($reconnectWhy)<span class="hl-why">{{ $reconnectWhy }}</span>@endif
            </form>
        </details>
    @endif

    <div class="hl-actions">
        <form method="post" action="{{ route('hydrolink.accounts.pause', ['id' => $account->id]) }}" class="hl-form" data-hl-ajax data-hl-reload>
            {{ csrf_field() }}
            <button type="submit" class="hl-btn" @if ($pauseWhy) disabled aria-describedby="hl-why-pause-{{ $account->id }}" @endif>{{ $account->paused ? __('hydrolink::messages.resume') : __('hydrolink::messages.pause') }}</button>
        </form>
        @if ($pauseWhy)<span class="hl-why" id="hl-why-pause-{{ $account->id }}">{{ $pauseWhy }}</span>@endif
        <details class="hl-confirm">
            <summary class="hl-btn hl-btn-danger">{{ __('hydrolink::messages.delete') }}</summary>
            <form method="post" action="{{ route('hydrolink.accounts.delete', ['id' => $account->id]) }}" class="hl-form hl-stack" data-hl-ajax data-hl-reload>
                {{ csrf_field() }}
                <input type="hidden" name="confirm" value="{{ $account->email }}">
                <p class="hl-hint">{{ __('hydrolink::messages.delete_hint') }}</p>
                <button type="submit" class="hl-btn hl-btn-danger-solid">{{ __('hydrolink::messages.delete_confirm') }}</button>
            </form>
        </details>
    </div>

    <details class="hl-more">
        <summary>{{ __('hydrolink::messages.rename') }}</summary>
        <form method="post" action="{{ route('hydrolink.accounts.rename', ['id' => $account->id]) }}" class="hl-form hl-stack" data-hl-ajax data-hl-reload autocomplete="off">
            {{ csrf_field() }}
            <div class="hl-inline">
                <label class="hl-field"><span>{{ __('hydrolink::messages.name') }}</span>
                    <input type="text" name="name" maxlength="40" value="{{ $account->name }}" placeholder="{{ \Modules\HydroLink\Services\Accounts\MailboxLinker::name($account->email) }}"></label>
                <button type="submit" class="hl-btn hl-btn-primary">{{ __('hydrolink::messages.save') }}</button>
            </div>
            <p class="hl-hint">{{ __('hydrolink::messages.rename_hint') }}</p>
        </form>
    </details>

    <details class="hl-more" data-hl-logs="{{ route('hydrolink.accounts.logs', ['id' => $account->id]) }}">
        <summary>{{ __('hydrolink::messages.logs') }}</summary>
        <pre class="hl-log">{{ __('hydrolink::messages.loading') }}</pre>
    </details>
</section>

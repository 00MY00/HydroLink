{{-- Password fields of a login; the mailbox password only for accounts in "two passwords" mode. --}}
<label class="hl-field"><span>{{ __('hydrolink::messages.password') }}</span>
    <input type="password" name="password" required autocomplete="off" maxlength="512"></label>
<details class="hl-more" @if (!empty($openMailbox)) open @endif>
    <summary>{{ __('hydrolink::messages.two_passwords') }}</summary>
    <label class="hl-field"><span>{{ __('hydrolink::messages.mailbox_password') }}</span>
        <input type="password" name="mailbox_password" autocomplete="off" maxlength="512"></label>
</details>

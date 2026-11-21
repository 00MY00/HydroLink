{{-- One error code: title on one line, what is expected / the effect / the action folded. --}}
@php($e = \Modules\HydroLink\Services\Support\ErrorCatalog::describe($code))
<details class="hl-error hl-error-{{ $e['severity'] }}">
    <summary><span class="hl-code">{{ $code }}</span> {{ $e['title'] }}</summary>
    <dl>
        <dt>{{ __('hydrolink::messages.err_expected') }}</dt><dd>{{ $e['expected'] }}</dd>
        <dt>{{ __('hydrolink::messages.err_effect') }}</dt><dd>{{ $e['effect'] }}</dd>
        <dt>{{ __('hydrolink::messages.err_action') }}</dt><dd>{{ $e['action'] }}</dd>
        @if (!empty($detail))<dt>{{ __('hydrolink::messages.err_detail') }}</dt><dd class="hl-mono">{{ $detail }}</dd>@endif
    </dl>
</details>

<div class="hl-section"><h2>{{ __('hydrolink::messages.accounts') }}</h2><span class="hl-count">{{ count($state['accounts']) }}</span></div>
@include('hydrolink::partials.add-account')
@foreach ($state['accounts'] as $account)
    @include('hydrolink::partials.account', ['account' => $account])
@endforeach

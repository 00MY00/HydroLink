@foreach ($state['accounts'] as $account)
    @include('hydrolink::partials.account', ['account' => $account])
@endforeach
@include('hydrolink::partials.add-account')

{{-- HydroLink panel: one card per feature (Features/*), state read from files, actions queued for the cron. --}}
@extends('layouts.app')

@section('title', 'HydroLink')

@section('stylesheets')
    <link href="{{ asset(\Module::getPublicPath('hydrolink').'/css/hydrolink.css') }}?v={{ $version }}" rel="stylesheet" type="text/css">
@endsection

@section('content')
<div class="hl-page" id="hl-page"
     data-state-url="{{ route('hydrolink.state') }}"
     data-settings-url="{{ route('hydrolink.settings') }}"
     data-msg-saved="{{ __('hydrolink::messages.saved') }}"
     data-msg-error="{{ __('hydrolink::messages.request_failed') }}"
     data-msg-unsaved="{{ __('hydrolink::messages.unsaved') }}">
    <header class="hl-header">
        <div>
            <h1>HydroLink</h1>
            <p class="hl-desc">{{ __('hydrolink::messages.description') }}</p>
        </div>
    </header>

    @if ($state['env_problems'])
        <div class="hl-banner" role="alert">
            @foreach ($state['env_problems'] as $code)
                @include('hydrolink::partials.error', ['code' => $code])
            @endforeach
        </div>
    @endif

    <div class="hl-grid">
        @foreach ($features as $feature)
            @if ($feature->view())
                @include($feature->view(), ['feature' => $feature, 'state' => $state, 'status' => $state['statuses'][$feature->key()]])
            @endif
        @endforeach
    </div>

    <p class="hl-foot">{{ __('hydrolink::messages.disclaimer') }}</p>
    <div class="hl-toast" id="hl-toast" role="status" aria-live="polite" hidden></div>
</div>
@endsection

@section('javascripts')
    <script src="{{ asset(\Module::getPublicPath('hydrolink').'/js/hydrolink.js') }}?v={{ $version }}" {!! \Helper::cspNonceAttr() !!}></script>
@endsection

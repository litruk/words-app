@extends('layouts.page')

@section('title', __('Password changed'))

@section('content')
    <span class="badge" aria-hidden="true">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg>
    </span>
    <h1>{{ __('Password changed') }}</h1>
    <p>{{ __('Go back to the app and sign in with the new password.') }}</p>
@endsection

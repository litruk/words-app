@extends('layouts.page')

@section('title', __('New password'))

@section('content')
    <span class="badge" aria-hidden="true">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10.5" width="14" height="10" rx="2"></rect><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"></path><path d="M12 14.5v2"></path></svg>
    </span>
    <h1>{{ __('New password') }}</h1>
    <p>{{ __('Choose a new password for :email. You will use it to sign in on every device.', ['email' => $email]) }}</p>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ old('email', $email) }}">

        <div>
            <div class="field @error('password') error @enderror">
                <label for="password">{{ __('New password') }}</label>
                <input id="password" name="password" type="password" minlength="8" required autofocus autocomplete="new-password" aria-describedby="password-help">
            </div>
            <span id="password-help" class="help">{{ $errors->first('password') ?: $errors->first('email') ?: __('At least 8 characters') }}</span>
        </div>

        <button type="submit">{{ __('Save password') }}</button>
    </form>
@endsection

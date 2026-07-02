@extends('layouts.app')

@section('title', 'Login | PowerMail Core')

@section('content')
    <section class="auth-card panel compact">
        <p class="eyebrow">PowerMail Core</p>
        <h1>Login</h1>
        <p class="lede" style="margin-bottom: 18px;">Admin dashboard access.</p>
        @if ($errors->any())
            <div class="alert error">
                <strong>Check the form.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="field" style="margin-top: 14px;">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <label class="field checkbox">
                <input name="remember" type="checkbox" value="1">
                Remember me
            </label>
            <div class="actions">
                <button type="submit">Log in</button>
            </div>
        </form>
    </section>
@endsection

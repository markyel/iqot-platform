@extends('layouts.auth')

@section('title', 'Вход')

@section('content')
    <h1 class="auth-title">Вход в систему</h1>
    <p class="auth-subtitle">Войдите в личный кабинет IQOT</p>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email" class="form-label">Email</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                class="form-input @error('email') error @enderror" 
                value="{{ old('email') }}" 
                placeholder="your@email.com"
                required 
                autofocus 
                autocomplete="username"
            >
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Пароль</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-input @error('password') error @enderror" 
                placeholder="••••••••"
                required 
                autocomplete="current-password"
            >
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-actions">
            <div class="form-checkbox">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Запомнить меня</label>
            </div>
            
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="form-link">
                    Забыли пароль?
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary">
            Войти
        </button>
    </form>
@endsection

@section('footer')
    <div class="auth-footer">
        Нет аккаунта? <a href="{{ route('register') }}">Зарегистрируйтесь</a>
    </div>
@endsection

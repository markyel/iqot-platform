@extends('layouts.auth')

@section('title', 'Регистрация')

@section('content')
    <h1 class="auth-title">Создать аккаунт</h1>
    <p class="auth-subtitle">Начните автоматизировать сбор КП уже сегодня</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="form-group">
            <label for="name" class="form-label">Имя</label>
            <input 
                type="text" 
                id="name" 
                name="name" 
                class="form-input @error('name') error @enderror" 
                value="{{ old('name') }}" 
                placeholder="Иван Петров"
                required 
                autofocus 
                autocomplete="name"
            >
            @error('name')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

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
                autocomplete="username"
            >
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="company" class="form-label">Компания</label>
                <input 
                    type="text" 
                    id="company" 
                    name="company" 
                    class="form-input @error('company') error @enderror" 
                    value="{{ old('company') }}" 
                    placeholder="ООО Компания"
                    autocomplete="organization"
                >
                @error('company')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Телефон</label>
                <input 
                    type="tel" 
                    id="phone" 
                    name="phone" 
                    class="form-input @error('phone') error @enderror" 
                    value="{{ old('phone') }}" 
                    placeholder="+7 (999) 123-45-67"
                    autocomplete="tel"
                >
                @error('phone')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Пароль</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-input @error('password') error @enderror" 
                placeholder="Минимум 8 символов"
                required 
                autocomplete="new-password"
            >
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation" class="form-label">Подтвердите пароль</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-input"
                placeholder="Повторите пароль"
                required
                autocomplete="new-password"
            >
        </div>

        <div class="form-group">
            <label for="promo_code" class="form-label">Промокод (необязательно)</label>
            <input
                type="text"
                id="promo_code"
                name="promo_code"
                class="form-input @error('promo_code') error @enderror"
                value="{{ old('promo_code', request('promo')) }}"
                placeholder="Введите промокод"
                style="text-transform: uppercase;"
                autocomplete="off"
            >
            @error('promo_code')
                <div class="form-error">{{ $message }}</div>
            @enderror
            <small class="form-help" style="display: block; margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-secondary);">
                Если у вас есть промокод, введите его для получения дополнительного баланса
            </small>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">
            Создать аккаунт
        </button>
    </form>
@endsection

@section('footer')
    <div class="auth-footer">
        Уже есть аккаунт? <a href="{{ route('login') }}">Войдите</a>
    </div>
@endsection

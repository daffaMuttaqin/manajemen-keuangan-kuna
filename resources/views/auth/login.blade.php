<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/png" href="{{ asset('images/logo-kuna.png') }}">


    <title>Kuna Finance - Sign In</title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-on-surface font-inter min-h-screen">

    <main class="min-h-screen flex items-center justify-center px-6 py-12">

        <section class="w-full max-w-md">

            <!-- Brand -->
            <div class="text-center mb-8">

                <div class="mx-auto mb-5 w-12 h-12 rounded bg-primary-container flex items-center justify-center">
                    <span class="text-2xl font-semibold text-on-primary-container">
                        K
                    </span>
                </div>

                <h1 class="text-xl font-semibold text-primary">
                    Kuna Patisserie
                </h1>

                <p class="text-sm text-on-surface-variant mt-1">
                    Financial Operations
                </p>

            </div>

            <!-- Login Card -->
            <div class="bg-surface-container-low border border-outline-variant rounded-lg p-6 sm:p-8">

                <div class="mb-7">

                    <h2 class="text-2xl font-semibold text-on-surface">
                        Welcome back
                    </h2>

                    <p class="text-sm text-on-surface-variant mt-2">
                        Sign in to continue to Kuna Finance.
                    </p>

                </div>

                <!-- Authentication error block -->
                @if ($errors->any())
                    <div class="mb-5 rounded border border-error/30 bg-error-container/20 px-3 py-3 text-sm text-error" role="alert">
                        <div class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-[18px]">
                                error
                            </span>

                            <span>
                                {{ $errors->first() }}
                            </span>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email -->
                    <div class="mb-5">

                        <label for="email" class="block text-sm font-medium text-on-surface mb-2">
                            Email
                        </label>

                        <div class="relative">

                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant">
                                mail
                            </span>

                            <input id="email"
                                   name="email"
                                   type="email"
                                   value="{{ old('email') }}"
                                   autocomplete="email"
                                   required
                                   autofocus
                                   placeholder="you@example.com"
                                   class="w-full h-11 bg-background border @error('email') border-error @else border-outline-variant @enderror text-on-surface rounded pl-10 pr-3 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary placeholder:text-on-surface-variant/50">

                        </div>

                        @error('email')
                            <p class="text-xs text-error mt-1.5">{{ $message }}</p>
                        @enderror

                    </div>

                    <!-- Password -->
                    <div class="mb-6">

                        <label for="password" class="block text-sm font-medium text-on-surface mb-2">
                            Password
                        </label>

                        <div class="relative">

                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant">
                                lock
                            </span>

                            <input id="password"
                                   name="password"
                                   type="password"
                                   autocomplete="current-password"
                                   required
                                   placeholder="Enter your password"
                                   class="w-full h-11 bg-background border @error('password') border-error @else border-outline-variant @enderror text-on-surface rounded pl-10 pr-11 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary placeholder:text-on-surface-variant/50">

                            <button type="button"
                                    id="togglePassword"
                                    aria-label="Show password"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center text-on-surface-variant hover:text-primary transition focus:outline-none focus:ring-1 focus:ring-primary rounded">
                                <span id="togglePasswordIcon" class="material-symbols-outlined text-[19px]">
                                    visibility
                                </span>
                            </button>

                        </div>

                        @error('password')
                            <p class="text-xs text-error mt-1.5">{{ $message }}</p>
                        @enderror

                    </div>

                    <!-- Submit -->
                    <button type="submit"
                            class="w-full h-11 bg-primary text-on-primary font-semibold rounded flex items-center justify-center gap-2 hover:bg-primary-container transition focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background">
                        <span class="material-symbols-outlined text-[18px]">
                            login
                        </span>

                        Sign In
                    </button>

                </form>

            </div>

            <!-- Footer -->
            <div class="text-center mt-6">

                <p class="text-xs text-on-surface-variant">
                    Kuna Patisserie Finance
                </p>

                <p class="text-xs text-on-surface-variant/60 mt-1">
                    Secure financial operations
                </p>

            </div>

        </section>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            if (toggleBtn && passwordInput && toggleIcon) {
                toggleBtn.addEventListener('click', function () {
                    const isPassword = passwordInput.type === 'password';
                    passwordInput.type = isPassword ? 'text' : 'password';
                    toggleIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
                    toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                });
            }
        });
    </script>
</body>
</html>

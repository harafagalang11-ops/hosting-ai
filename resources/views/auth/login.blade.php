<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Hosting AI Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: #111827;
        }

        .login-wrapper {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .brand-panel {
            background: #111827;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px;
            text-align: center;
        }

        .brand-content { max-width: 400px; }

        .brand-logo {
            width: 240px;
            max-width: 85%;
            height: auto;
            object-fit: contain;
            margin-bottom: 25px;
        }

        .brand-title {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .brand-description {
            color: #9ca3af;
            line-height: 1.7;
        }

        .form-panel {
            background: white;
            border-radius: 90px 0 0 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 55px;
        }

        .login-form-container {
            width: 100%;
            max-width: 480px;
        }

        .lock-icon {
            width: 42px;
            height: 42px;
            margin: 0 auto 12px;
            color: #1f2937;
        }

        .login-heading {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 28px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 9px;
        }

        .form-control {
            min-height: 48px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 12px;
            font-size: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,.06);
        }

        .form-control:focus {
            border-color: #64748b;
            box-shadow: 0 0 0 3px rgba(100,116,139,.15);
        }

        .password-wrapper { position: relative; }

        .password-wrapper .form-control { padding-right: 48px; }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
        }

        .remember-label { color: #374151; }

        .login-button {
            width: 100%;
            min-height: 48px;
            background: #111827;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 25px;
        }

        .login-button:hover {
            background: #273449;
            color: white;
        }

        .login-footer {
            text-align: center;
            color: #6b7280;
            margin-top: 25px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .login-wrapper { grid-template-columns: 1fr; }

            .brand-panel {
                min-height: 220px;
                padding: 30px 20px;
            }

            .brand-logo {
                width: 155px;
                margin-bottom: 12px;
            }

            .brand-title { font-size: 22px; }

            .brand-description {
                font-size: 13px;
                margin-bottom: 0;
            }

            .form-panel {
                border-radius: 35px 35px 0 0;
                margin-top: -25px;
                position: relative;
                padding: 45px 25px;
                min-height: 65vh;
            }

            .login-heading { font-size: 28px; }
        }
    </style>
</head>

<body>
<div class="login-wrapper">

    <section class="brand-panel">
        <div class="brand-content">
            <img src="{{ asset('images/hosting-ai-logo.png') }}"
                 alt="Hosting AI" class="brand-logo">

            <h1 class="brand-title">Hosting AI Agent</h1>

            <p class="brand-description">
                Sistem monitoring dan pengelolaan hosting
                yang lebih mudah, cerdas, dan terintegrasi.
            </p>
        </div>
    </section>

    <section class="form-panel">
        <div class="login-form-container">

            <div class="text-center">
                <svg class="lock-icon" xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M12 1a5 5 0 00-5 5v3H6a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V11a2 2 0 00-2-2h-1V6a5 5 0 00-5-5zm3 8V6a3 3 0 00-6 0v3h6z"
                        clip-rule="evenodd"/>
                </svg>
            </div>

            <h2 class="login-heading">Log In</h2>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ url('/login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Username/Email</label>
                    <input type="email" name="email" id="email"
                           class="form-control"
                           placeholder="Masukkan email"
                           value="{{ old('email') }}"
                           autocomplete="username" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password"
                               class="form-control"
                               placeholder="Masukkan password"
                               autocomplete="current-password" required>

                        <button type="button" class="password-toggle"
                                id="togglePassword"
                                aria-label="Tampilkan password">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 width="21" height="21" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-check mt-4">
                    <input type="checkbox" name="remember" value="1"
                           class="form-check-input" id="remember"
                           {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label remember-label"
                           for="remember">Ingat Saya</label>
                </div>

                <button type="submit" class="btn login-button">Login</button>
            </form>

            <div class="login-footer">
                Hosting AI Agent &copy; {{ date('Y') }}
            </div>
        </div>
    </section>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', function () {
        const show = passwordInput.type === 'password';
        passwordInput.type = show ? 'text' : 'password';
        this.setAttribute(
            'aria-label',
            show ? 'Sembunyikan password' : 'Tampilkan password'
        );
    });
</script>
</body>
</html>

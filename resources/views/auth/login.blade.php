<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Hosting AI Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f4f6fa;
            font-family: Arial, sans-serif;
        }
        .login-card {
            width: min(420px, 92%);
            border: 0;
            border-radius: 18px;
        }
    </style>
    <link rel="icon" type="image/png" href="{{ asset("favicon.png") }}">
</head>
<body>
    <div class="card login-card shadow p-4">
        <div class="text-center mb-4">
            <img src="{{ asset('images/hosting-ai-logo.png') }}"
                 alt="Hosting AI"
                 style="width:190px;max-width:100%;height:auto;object-fit:contain;">
            <h3 class="fw-bold mt-3">Hosting AI Agent</h3>
            <p class="text-secondary">Silakan masuk ke akun Anda</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
<form method="POST" action="/login">
            @csrf

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                    value="{{ old('email') }}" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Ingat saya</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                Login
            </button>
        </form>
    </div>
</body>
</html>

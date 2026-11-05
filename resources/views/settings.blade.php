<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengaturan - Hosting AI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset("favicon.png") }}">
    <link rel="stylesheet" href="{{ asset("css/app-layout.css") }}">
    <link rel="stylesheet" href="{{ asset("css/theme.css") }}">
</head>
<body style="background:#f4f6fa" class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex min-vh-100">
    @include('partials.sidebar')

    <main class="container py-5 content">
        <h2>Pengaturan</h2>
        <p class="text-muted">Atur tampilan dan informasi akun Hosting AI.</p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @php($user = auth()->user())

        <div class="card border-0 shadow-sm p-4 mb-4" style="max-width:650px">
            <h5 class="mb-3">Tampilan Aplikasi</h5>
            <form method="POST" action="/settings/theme">
                @csrf
                @method('PUT')

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="theme"
                           value="light" id="light"
                           {{ $user->theme === 'light' ? 'checked' : '' }}>
                    <label class="form-check-label" for="light">Mode Terang</label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="theme"
                           value="dark" id="dark"
                           {{ $user->theme === 'dark' ? 'checked' : '' }}>
                    <label class="form-check-label" for="dark">Mode Gelap</label>
                </div>

                <button class="btn btn-primary">Simpan Tema</button>
            </form>
        </div>

        <div class="card border-0 shadow-sm p-4" style="max-width:650px">
            <h5>Profil Pengguna</h5>
            <p class="text-muted">Ubah username dan foto profil akun Anda.</p>
            <div class="d-flex align-items-center gap-3">
                @if($user->avatar)
                    <img src="{{ asset('storage/'.$user->avatar) }}"
                         class="rounded-circle" width="65" height="65"
                         style="object-fit:cover">
                @else
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                         style="width:65px;height:65px;font-size:24px">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <strong>{{ $user->name }}</strong>
                    <div class="text-muted">{{ $user->email }}</div>
                </div>
            </div>
            <a href="/profile" class="btn btn-outline-primary mt-3 align-self-start">
                Edit Profil
            </a>
        </div>
    </main>
</div>
</body>
</html>

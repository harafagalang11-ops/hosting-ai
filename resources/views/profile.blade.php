<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil Pengguna - Hosting AI</title>
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
        <h2>Profil Pengguna</h2>
        <p class="text-muted">Kelola informasi akun Anda.</p>

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

        <div class="card border-0 shadow-sm p-4 mt-4" style="max-width:650px">
            <form method="POST" action="/profile" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Foto Profil</label><br>
                    @if($user->avatar)
                        <img src="{{ asset('storage/'.$user->avatar) }}"
                             class="rounded-circle mb-3"
                             width="100" height="100" style="object-fit:cover">
                    @else
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mb-3"
                             style="width:100px;height:100px;font-size:36px">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                    @endif
                    <input type="file" name="avatar" class="form-control" accept="image/*">
                    <small class="text-muted">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Username / Nama</label>
                    <input type="text" name="name" class="form-control"
                           value="{{ old('name', $user->name) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control"
                           value="{{ $user->email }}" disabled>
                </div>

                <button class="btn btn-primary">Simpan Perubahan</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>

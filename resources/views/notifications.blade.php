<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifikasi - Hosting AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')

    <main class="content flex-grow-1 p-4">
        <h2>Notifikasi</h2>
        <p class="text-secondary">Informasi gangguan, peringatan, dan pemulihan website.</p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3">
                    <small class="text-secondary">Belum dibaca</small>
                    <h3 class="mb-0">{{ $unreadCount }}</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3">
                    <small class="text-secondary">Total notifikasi</small>
                    <h3 class="mb-0">{{ $notifications->total() }}</h3>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm p-4">
            <h5 class="mb-3">Daftar Notifikasi</h5>

            @forelse($notifications as $notification)
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div>
                            <strong>{{ $notification->title }}</strong>
                            @if(!$notification->read_at)
                                <span class="badge bg-primary ms-2">Baru</span>
                            @endif
                            <div class="text-secondary small mt-1">
                                {{ $notification->website_name }} ·
                                {{ $notification->checked_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i') ?? '-' }} WIB
                            </div>
                        </div>
                        <span class="badge {{ $notification->event_type === 'gangguan' ? 'bg-danger' : ($notification->event_type === 'warning' ? 'bg-warning text-dark' : 'bg-success') }}">
                            {{ strtoupper($notification->event_type) }}
                        </span>
                    </div>

                    <p class="mt-3 mb-2" style="white-space: pre-line">{{ $notification->message }}</p>

                    <div class="small mb-3">
                        @if($notification->telegram_sent_at)
                            <span class="text-success"><i class="bi bi-telegram"></i> Telegram terkirim</span>
                        @elseif($notification->telegram_error)
                            <span class="text-danger"><i class="bi bi-exclamation-circle"></i> Telegram gagal: {{ $notification->telegram_error }}</span>
                        @else
                            <span class="text-secondary">Status Telegram belum tercatat</span>
                        @endif
                    </div>

                    <div class="d-flex gap-2">
                        @if(!$notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-outline-primary btn-sm">Tandai dibaca</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('notifications.destroy', $notification) }}"
                              onsubmit="return confirm('Hapus notifikasi ini?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm">Hapus</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="text-center text-secondary py-5">
                    <i class="bi bi-bell-slash fs-1"></i>
                    <p class="mt-2">Belum ada notifikasi.</p>
                </div>
            @endforelse

            {{ $notifications->links() }}
        </div>
    </main>
</div>
</body>
</html>

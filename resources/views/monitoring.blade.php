<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Hosting - Hosting AI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">

    <style>
        .page-heading {
            display:flex; justify-content:space-between; align-items:center;
            flex-wrap:wrap; gap:16px; margin-bottom:28px;
        }
        .page-heading h1 { font-size:30px; font-weight:750; margin:0 0 7px; }
        .page-heading p { color:var(--muted,#9ca3af); margin:0; }
        .monitor-card {
            background:var(--card-bg,#1f2937);
            border:1px solid var(--card-border,#374151);
            border-radius:17px; padding:24px; margin-bottom:22px;
            box-shadow:0 5px 18px rgba(0,0,0,.06);
        }
        .section-heading {
            display:flex; align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:12px; margin-bottom:20px;
        }
        .section-title { font-size:19px; font-weight:700; margin:0; }
        .section-description { color:var(--muted,#9ca3af); font-size:13px; margin:6px 0 0; }
        .section-icon {
            width:42px; height:42px; display:grid; place-items:center;
            border-radius:12px; background:rgba(37,99,235,.14);
            color:#60a5fa; font-size:19px;
        }
        .monitor-table { margin:0; color:var(--text,#e5e7eb); }
        .monitor-table th {
            color:var(--muted,#9ca3af); font-size:11px;
            text-transform:uppercase; letter-spacing:.7px;
            padding:13px 12px; border-bottom:1px solid var(--card-border,#374151);
        }
        .monitor-table td {
            padding:16px 12px; vertical-align:middle;
            border-color:var(--card-border,#374151);
            color:var(--text,#e5e7eb);
        }
        .site-name { font-weight:700; }
        .site-url { color:var(--muted,#9ca3af); font-size:13px; word-break:break-all; }
        .status-pill {
            display:inline-flex; align-items:center; gap:7px;
            padding:6px 12px; border-radius:30px; font-size:12px; font-weight:700;
        }
        .status-normal { background:rgba(22,163,74,.15); color:#22c55e; }
        .status-error { background:rgba(220,38,38,.15); color:#f87171; }
        .status-warning { background:rgba(245,158,11,.15); color:#fbbf24; }
        .status-unknown { background:rgba(107,114,128,.18); color:#9ca3af; }
        .btn-refresh {
            border:1px solid var(--card-border,#475569);
            color:var(--text,#e5e7eb); border-radius:9px;
            padding:8px 13px; font-size:13px; text-decoration:none;
        }
        .btn-refresh:hover { background:rgba(148,163,184,.12); color:var(--text,#fff); }
        .check-panel {
            display:flex; align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:20px;
        }
        .check-info { display:flex; align-items:center; gap:15px; }
        .check-button {
            border:0; border-radius:10px; padding:12px 19px;
            background:#2563eb; color:white; font-weight:650;
            transition:.2s;
        }
        .check-button:hover { background:#1d4ed8; transform:translateY(-1px); }
        .info-note {
            color:var(--muted,#9ca3af); font-size:12px;
            display:flex; align-items:flex-start; gap:8px; margin-top:17px;
        }
        .result-heading { font-size:16px; font-weight:700; margin:20px 0 12px; }
        .result-detail { color:#f87171; font-size:12px; padding-top:0 !important; }
        .empty-state { text-align:center; padding:25px; color:var(--muted,#9ca3af); }
        @media(max-width:700px) {
            .page-heading h1 { font-size:24px; }
            .monitor-card { padding:17px; }
            .check-panel { align-items:stretch; }
            .check-panel form, .check-button { width:100%; }
        }
    </style>
</head>

<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')

    <main class="content">
        <div class="page-heading">
            <div>
                <div class="text-primary small fw-bold mb-2">
                    <i class="bi bi-activity me-1"></i> HOSTING MANAGEMENT
                </div>
                <h1>Monitoring Hosting</h1>
                <p>Pantau ketersediaan website dan waktu respons secara berkala.</p>
            </div>
            <a href="/monitoring" class="btn btn-primary px-4 py-2">
                <i class="bi bi-arrow-clockwise me-2"></i>Muat Ulang
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <section class="monitor-card">
            <div class="section-heading">
                <div class="d-flex align-items-center gap-3">
                    <div class="section-icon"><i class="bi bi-cloud-check"></i></div>
                    <div>
                        <h2 class="section-title">Status Monitoring Website</h2>
                        <p class="section-description">Status terakhir website berdasarkan riwayat monitoring database.</p>
                    </div>
                </div>
                <span class="status-pill status-normal">
                    <i class="bi bi-shield-check"></i> Data Monitoring
                </span>
            </div>

            <div class="table-responsive">
                <table class="table monitor-table align-middle">
                    <thead>
                        <tr>
                            <th>Website</th>
                            <th>Alamat Website</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($monitoring as $site)
                            <tr>
                                <td><span class="site-name">{{ $site['name'] }}</span></td>
                                <td><span class="site-url">{{ $site['url'] }}</span></td>
                                <td>
                                    @if ($site['status'] === 'NORMAL')
                                        <span class="status-pill status-normal">
                                            <i class="bi bi-check-circle-fill"></i> Normal
                                        </span>
                                    @elseif ($site['status'] === 'WARNING')
                                        <span class="status-pill status-warning">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Warning
                                        </span>
                                    @elseif ($site['status'] === 'GANGGUAN')
                                        <span class="status-pill status-error">
                                            <i class="bi bi-exclamation-circle-fill"></i> Gangguan
                                        </span>
                                    @else
                                        <span class="status-pill status-unknown">
                                            <i class="bi bi-question-circle"></i> Tidak diketahui
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="empty-state">
                                    Belum ada website aktif atau data monitoring.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="info-note">
                <i class="bi bi-info-circle"></i>
                <span>Status website diambil dari data monitoring yang tersimpan di database.</span>
            </div>
        </section>

        <section class="monitor-card">
            <div class="check-panel">
                <div class="check-info">
                    <div class="section-icon"><i class="bi bi-globe2"></i></div>
                    <div>
                        <h2 class="section-title">Pemeriksaan Langsung</h2>
                        <p class="section-description">
                            Periksa koneksi website dan ukur waktu respons secara langsung.
                        </p>
                    </div>
                </div>

                <form method="POST" action="/monitoring/check">
                    @csrf
                    <button type="submit" class="check-button">
                        <i class="bi bi-arrow-repeat me-2"></i>Periksa Sekarang
                    </button>
                </form>
            </div>

            <div class="info-note">
                <i class="bi bi-info-circle"></i>
                <span>
                    Pemeriksaan menggunakan daftar website aktif dari database dan tidak mengubah konfigurasi hosting.
                </span>
            </div>

            @if ($checkResults)
                <hr class="my-4">
                <h3 class="result-heading">
                    <i class="bi bi-clipboard2-check me-2 text-primary"></i>
                    Hasil Pemeriksaan Terbaru
                </h3>

                <div class="table-responsive">
                    <table class="table monitor-table align-middle">
                        <thead>
                            <tr>
                                <th>Website</th>
                                <th>Status</th>
                                <th>HTTP</th>
                                <th>Response Time</th>
                                <th>Waktu Pemeriksaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($checkResults as $result)
                                <tr>
                                    <td class="site-name">{{ $result['name'] }}</td>
                                    <td>
                                        @if ($result['status'] === 'NORMAL')
                                            <span class="status-pill status-normal">
                                                <i class="bi bi-check-circle-fill"></i> Normal
                                            </span>
                                        @elseif ($result['status'] === 'WARNING')
                                            <span class="status-pill status-warning">
                                                <i class="bi bi-exclamation-triangle-fill"></i> Warning
                                            </span>
                                        @elseif ($result['status'] === 'GANGGUAN')
                                            <span class="status-pill status-error">
                                                <i class="bi bi-exclamation-circle-fill"></i> Gangguan
                                            </span>
                                        @else
                                            <span class="status-pill status-unknown">
                                                <i class="bi bi-question-circle"></i> Tidak diketahui
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $result['http_code'] ?? '-' }}</td>
                                    <td>{{ $result['response_time'] }} detik</td>
                                    <td>{{ $result['checked_at'] }}</td>
                                </tr>
                                @if ($result['status'] === 'GANGGUAN')
                                    <tr>
                                        <td colspan="5" class="result-detail">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            {{ $result['detail'] }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Hosting AI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">

    <style>
        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .page-heading h2 {
            font-weight: 750;
            letter-spacing: -1px;
            margin-bottom: 6px;
        }

        .dashboard-card {
            border: 1px solid var(--dashboard-border);
            border-radius: 16px;
            background: var(--dashboard-card);
            height: 100%;
            transition: transform .2s, box-shadow .2s;
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(15, 23, 42, .07);
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            font-size: 21px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 750;
            letter-spacing: -1px;
        }

        .section-card {
            background: var(--dashboard-card);
            border: 1px solid var(--dashboard-border);
            border-radius: 16px;
            padding: 22px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 30px;
            padding: 6px 11px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-normal {
            color: #15803d;
            background: #dcfce7;
        }

        .status-error {
            color: #b91c1c;
            background: #fee2e2;
        }
        .status-warning {
            color: #92400e;
            background: #fef3c7;
        }

        .status-unknown {
            color: #64748b;
            background: #e2e8f0;
        }

        .dashboard-table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--dashboard-text);
            --bs-table-border-color: var(--dashboard-border);
            vertical-align: middle;
        }

        .dashboard-table th {
            color: var(--dashboard-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 14px 12px;
        }

        .dashboard-table td {
            padding: 16px 12px;
        }

        .dashboard-url {
            max-width: 280px;
            overflow-wrap: anywhere;
            font-size: 13px;
            color: var(--dashboard-muted);
        }

        .assistant-panel {
            background: linear-gradient(120deg, #172554, #2563eb);
            border-radius: 18px;
            color: white;
            padding: 26px;
        }

        .assistant-panel .btn {
            background: white;
            color: #1d4ed8;
            border: 0;
            font-weight: 700;
        }

        .assistant-panel .btn:hover {
            background: #eff6ff;
        }

        :root {
            --dashboard-card: #ffffff;
            --dashboard-border: #e8edf4;
            --dashboard-text: #172033;
            --dashboard-muted: #64748b;
        }

        body.theme-dark {
            --dashboard-card: #1f2937;
            --dashboard-border: #374151;
            --dashboard-text: #f3f4f6;
            --dashboard-muted: #9ca3af;
        }

        body.theme-dark .status-normal {
            color: #86efac;
            background: #14532d;
        }

        body.theme-dark .status-error {
            color: #fca5a5;
            background: #7f1d1d;
        }
        body.theme-dark .status-warning {
            color: #fcd34d;
            background: #78350f;
        }

        body.theme-dark .status-unknown {
            color: #cbd5e1;
            background: #374151;
        }

        @media(max-width: 700px) {
            .stat-number { font-size: 27px; }
            .section-card { padding: 16px; }
            .assistant-panel { padding: 20px; }
        }
    </style>
</head>

<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')

    <main class="content">
        <div class="page-heading">
            <div>
                <div class="text-secondary small mb-2">
                    <i class="bi bi-grid-1x2-fill me-1"></i> OVERVIEW
                </div>
                <h2>Dashboard</h2>
                <p class="text-secondary mb-0">
                    Selamat datang di Hosting AI Monitoring Platform.
                </p>
            </div>

            <a href="/monitoring" class="btn btn-primary rounded-3 px-3 py-2">
                <i class="bi bi-activity me-2"></i> Lihat Monitoring
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-3">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-secondary fw-semibold">Total Website</span>
                        <div class="stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-globe2"></i>
                        </div>
                    </div>
                    <div class="stat-number">{{ $total }}</div>
                    <small class="text-secondary">Website terdaftar dalam monitoring</small>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-secondary fw-semibold">Website Normal</span>
                        <div class="stat-icon bg-success-subtle text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number text-success">{{ $normal }}</div>
                    <small class="text-secondary">Website dengan status normal</small>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-secondary fw-semibold">Website Warning</span>
                        <div class="stat-icon bg-warning-subtle text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-number" style="color: #d97706;">{{ $warning }}</div>
                    <small class="text-secondary">Website dengan respons lambat</small>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-secondary fw-semibold">Website Gangguan</span>
                        <div class="stat-icon bg-danger-subtle text-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-number text-danger">{{ $gangguan }}</div>
                    <small class="text-secondary">Website yang perlu diperiksa</small>
                </div>
            </div>
        </div>

        <div class="section-card mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Status Website</h5>
                    <p class="text-secondary small mb-0">Ringkasan status monitoring dari database Hosting AI.</p>
                </div>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
                    <i class="bi bi-shield-check me-1"></i> Monitoring
                </span>
            </div>

            <div class="table-responsive">
                <table class="table dashboard-table mb-0">
                    <thead>
                        <tr>
                            <th>Website</th>
                            <th>URL</th>
                            <th>Status</th>
                            <th>Skor Kesehatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($monitoring as $site)
                            <tr>
                                <td class="fw-semibold">{{ $site['name'] }}</td>
                                <td>
                                    <div class="dashboard-url">{{ $site['url'] }}</div>
                                </td>
                                <td>
                                    @if ($site['status'] === 'NORMAL')
                                        <span class="status-badge status-normal">
                                            <i class="bi bi-check-circle-fill"></i> Normal
                                        </span>
                                    @elseif ($site['status'] === 'WARNING')
                                        <span class="status-badge status-warning">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Warning
                                        </span>
                                    @elseif ($site['status'] === 'GANGGUAN')
                                        <span class="status-badge status-error">
                                            <i class="bi bi-exclamation-circle-fill"></i> Gangguan
                                        </span>
                                    @else
                                        <span class="status-badge status-unknown">
                                            <i class="bi bi-question-circle-fill"></i> Tidak diketahui
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @php($health = $healthScores[$site['name']] ?? null)
                                    @if ($health && $health['score'] !== null)
                                        <div class="fw-bold">{{ $health['score'] }}/100</div>
                                        <span class="badge {{ $health['score'] >= 85 ? 'bg-success' : ($health['score'] >= 65 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                            {{ $health['label'] }}
                                        </span>
                                        <div class="small text-secondary mt-1">
                                            {{ $health['incident_count'] }} gangguan/peringatan dalam 24 jam
                                        </div>
                                        <details class="small mt-2">
                                            <summary class="text-primary" style="cursor:pointer">Rincian perhitungan skor</summary>
                                            <div class="mt-2 text-secondary">
                                                <div>Status terbaru: {{ $health['status_score'] }} × 50% = {{ number_format($health['status_score'] * 0.5, 1) }}</div>
                                                <div>Response time: {{ $health['response_score'] }} × 30% = {{ number_format($health['response_score'] * 0.3, 1) }}</div>
                                                <div>Riwayat 24 jam: {{ $health['incident_score'] }} × 20% = {{ number_format($health['incident_score'] * 0.2, 1) }}</div>
                                            </div>
                                        </details>
                                    @else
                                        <span class="badge bg-secondary">Data Belum Cukup</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-4">
                                    Belum ada data monitoring.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="text-secondary small mt-3">
                <i class="bi bi-info-circle me-1"></i>
                Status website dibaca dari database monitoring Hosting AI.
            </div>
        </div>

        <div class="section-card mb-4" id="grafik-monitoring">
            <h5 class="fw-bold mb-1">Grafik Riwayat Response Time</h5>

            <div class="d-flex flex-wrap gap-2 my-3">
                <a href="{{ route('dashboard', ['range' => '24h']) . '#grafik-monitoring' }}"
                   class="btn btn-sm {{ ($chartRange ?? '24h') === '24h' ? 'btn-primary' : 'btn-outline-primary' }}">
                    24 Jam
                </a>
                <a href="{{ route('dashboard', ['range' => '7d']) . '#grafik-monitoring' }}"
                   class="btn btn-sm {{ ($chartRange ?? '24h') === '7d' ? 'btn-primary' : 'btn-outline-primary' }}">
                    7 Hari
                </a>
                <a href="{{ route('dashboard', ['range' => '30d']) . '#grafik-monitoring' }}"
                   class="btn btn-sm {{ ($chartRange ?? '24h') === '30d' ? 'btn-primary' : 'btn-outline-primary' }}">
                    30 Hari
                </a>
            </div>

            <p class="text-secondary small mb-3">
                Riwayat pemeriksaan sesuai rentang waktu yang dipilih.
            </p>
            <div class="row g-4">
                @foreach ($chartData as $website => $records)
                    <div class="col-lg-6">
                        <h6 class="fw-semibold">{{ $website }}</h6>
                        @if ($records->isNotEmpty())
                            <div style="height: 260px; position: relative;">
                                <canvas id="chart-{{ \Illuminate\Support\Str::slug($website) }}"></canvas>
                            </div>
                        @else
                            <p class="text-secondary small">Belum ada data response time.</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="text-secondary small mt-3 mb-0">
                Satuan waktu respons adalah detik. Data diambil dari riwayat pemeriksaan database.
            </p>
        </div>

        <div class="assistant-panel">
            <div class="row align-items-center g-3">
                <div class="col-md">
                    <div class="mb-2 opacity-75">
                        <i class="bi bi-stars me-1"></i> AI HOSTING ASSISTANT
                    </div>
                    <h4 class="fw-bold mb-2">Butuh bantuan mengelola hosting?</h4>
                    <p class="mb-0 opacity-75">
                        Gunakan AI Agent untuk bertanya, menganalisis, dan membantu
                        memahami masalah layanan hosting.
                    </p>
                </div>
                <div class="col-md-auto">
                    <a href="/chatbot" class="btn rounded-3 px-4 py-2">
                        Buka AI Chatbot <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartData = @json($chartData);

    Object.entries(chartData).forEach(([website, records]) => {
        const canvasId = 'chart-' + website.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        const canvas = document.getElementById(canvasId);

        if (!canvas || !records.length || typeof Chart === 'undefined') return;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: records.map(item => item.label),
                datasets: [{
                    label: 'Response Time (detik)',
                    data: records.map(item => item.response_time),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.12)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Detik'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Waktu pemeriksaan'
                        }
                    }
                }
            }
        });
    });
});
</script>

</body>
</html>

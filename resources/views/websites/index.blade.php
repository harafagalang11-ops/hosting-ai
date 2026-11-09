<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Website - Hosting AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    <style>
        .page-heading{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:28px}
        .page-heading h1{font-size:30px;font-weight:750;margin:0 0 7px}
        .page-heading p,.muted{color:var(--muted,#9ca3af)}
        .website-card{background:var(--card-bg,#1f2937);border:1px solid var(--card-border,#374151);border-radius:17px;padding:24px;margin-bottom:22px}
        .website-table{color:var(--text,#e5e7eb)}
        .website-table th{color:var(--muted,#9ca3af);font-size:11px;text-transform:uppercase}
        .website-table td,.website-table th{padding:15px 12px;border-color:var(--card-border,#374151);vertical-align:middle}
        .website-table td{color:var(--text,#e5e7eb)}
        .site-url{color:var(--muted,#9ca3af);font-size:13px;overflow-wrap:anywhere}
        .form-control{background:var(--card-bg,#1f2937);color:var(--text,#e5e7eb);border-color:var(--card-border,#475569)}
        .form-control:focus{background:var(--card-bg,#1f2937);color:var(--text,#e5e7eb)}
        .form-label{color:var(--text,#e5e7eb)}
        .status-active{color:#22c55e}.status-inactive{color:#f59e0b}
        @media(max-width:700px){.page-heading h1{font-size:24px}.website-card{padding:17px}}
    </style>
</head>
<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')
    <main class="content">
        <div class="page-heading">
            <div>
                <div class="text-primary small fw-bold mb-2"><i class="bi bi-globe2 me-1"></i> WEBSITE MANAGEMENT</div>
                <h1>Manajemen Website</h1>
                <p>Kelola website yang akan dipantau oleh Hosting AI.</p>
            </div>
            <span class="badge text-bg-primary fs-6">{{ $websites->count() }} Website</span>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <strong>Periksa kembali data:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <section class="website-card">
            <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Tambah Website</h5>
            <form method="POST" action="{{ route('websites.store') }}">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Nama Website</label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Website Perusahaan" value="{{ old('name') }}" required maxlength="255">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Hosting</label>
                        <select name="migration_endpoint_id" class="form-select">
                            <option value="">Belum ditentukan</option>
                            @foreach($hostingEndpoints as $endpoint)
                                <option value="{{ $endpoint->id }}" @selected(old('migration_endpoint_id') == $endpoint->id)>
                                    {{ $endpoint->name }} ({{ $endpoint->host }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">URL Website</label>
                        <input type="url" name="url" class="form-control" placeholder="https://contoh.com" value="{{ old('url') }}" required maxlength="2048">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Document Root Hosting (Opsional)</label>
                        <input type="text" name="document_root" class="form-control" placeholder="/home/username/public_html" value="{{ old('document_root') }}" maxlength="2048">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
                    </div>
                </div>
                <p class="muted small mt-3 mb-0">Hanya URL HTTP/HTTPS dengan domain publik. Penambahan belum menjalankan pemeriksaan website.</p>
            </form>
        </section>

        <section class="website-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="fw-bold mb-0">Daftar Website</h5>
                <span class="muted small">Riwayat tetap disimpan saat website dihapus.</span>
            </div>
            <div class="table-responsive">
                <table class="table website-table mb-0">
                    <thead><tr><th>Website</th><th>Status</th><th>Jumlah Riwayat</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse($websites as $website)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $website->name }}</div>
                                <div class="site-url">{{ $website->url }}</div>
                            </td>
                            <td>
                                @if($website->is_active)
                                    <span class="status-active"><i class="bi bi-check-circle-fill me-1"></i>Aktif</span>
                                @else
                                    <span class="status-inactive"><i class="bi bi-pause-circle-fill me-1"></i>Nonaktif</span>
                                @endif
                            </td>
                            <td>{{ number_format($website->monitoring_logs_count) }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('websites.edit',$website) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                                    <form method="POST" action="{{ route('websites.toggle',$website) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning" type="submit">{{ $website->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('websites.destroy',$website) }}" onsubmit="return confirm('Hapus website dari daftar? Riwayat monitoring tetap disimpan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center muted py-4">Belum ada website terdaftar.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if(isset($editing) && $editing)
            <section class="website-card" id="edit-website">
                <h5 class="fw-bold mb-3">Edit Website: {{ $editing->name }}</h5>
                <form method="POST" action="{{ route('websites.update',$editing) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Nama Website</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name',$editing->name) }}" required maxlength="255">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Hosting</label>
                            <select name="migration_endpoint_id" class="form-select">
                                <option value="">Belum ditentukan</option>
                                @foreach($hostingEndpoints as $endpoint)
                                    <option value="{{ $endpoint->id }}" @selected(old('migration_endpoint_id', $editing->migration_endpoint_id) == $endpoint->id)>
                                        {{ $endpoint->name }} ({{ $endpoint->host }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">URL Website</label>
                            <input type="url" name="url" class="form-control" value="{{ old('url',$editing->url) }}" required maxlength="2048">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Document Root Hosting (Opsional)</label>
                            <input type="text" name="document_root" class="form-control" placeholder="/home/username/public_html" value="{{ old('document_root', $editing->document_root) }}" maxlength="2048">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Simpan</button>
                            <a href="{{ route('websites.index') }}" class="btn btn-secondary">Batal</a>
                        </div>
                    </div>
                </form>
            </section>
        @endif
    </main>
</div>
</body>
</html>

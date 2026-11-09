<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Hosting | Hosting AI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">

    <style>
        .page-heading {
            margin-bottom: 24px;
        }

        .page-heading h1 {
            font-size: 28px;
            font-weight: 750;
            margin-bottom: 7px;
        }

        .page-heading p,
        .hosting-muted {
            color: var(--muted, #9ca3af);
        }

        .hosting-card {
            padding: 22px;
            border: 1px solid var(--card-border, #374151);
            border-radius: 15px;
            background: var(--card-bg, #1f2937);
        }

        .hosting-card h5 {
            font-weight: 700;
        }

        .hosting-form .form-label {
            font-size: 13px;
            font-weight: 600;
        }

        .hosting-form .form-control,
        .hosting-form .form-select {
            background-color: var(--input-bg, var(--card-bg, #1f2937));
            color: var(--text, #e5e7eb);
            border-color: var(--card-border, #374151);
        }

        .hosting-form .form-control::placeholder {
            color: var(--muted, #9ca3af);
        }

        .hosting-table {
            color: var(--text, #e5e7eb);
            vertical-align: middle;
        }

        .hosting-table th {
            color: var(--muted, #9ca3af);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .hosting-table td {
            font-size: 13px;
        }

        .hosting-type {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 20px;
            background: rgba(59, 130, 246, .12);
            color: #60a5fa;
            font-size: 11px;
            font-weight: 700;
        }

        @media(max-width: 700px) {
            .page-heading h1 {
                font-size: 24px;
            }

            .hosting-card {
                padding: 16px;
            }
        }
    </style>
</head>

<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')

    <main class="content">
        <div class="page-heading">
            <div class="text-primary small fw-bold mb-2">
                <i class="bi bi-hdd-network me-1"></i>
                HOSTING MANAGEMENT
            </div>
            <h1>Kelola Hosting</h1>
            <p>Daftarkan dan atur konfigurasi hosting yang akan digunakan untuk migrasi website.</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('connection_error'))
            <div class="alert alert-danger">{{ session('connection_error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Periksa kembali data berikut:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="hosting-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">
                        {{ $endpoint ? 'Edit Konfigurasi Hosting' : 'Tambah Hosting' }}
                    </h5>
                    <p class="hosting-muted small mb-0">
                        Masukkan informasi server yang ingin didaftarkan.
                    </p>
                </div>

                @if ($endpoint)
                    <a href="{{ route('migration-endpoints.index') }}"
                       class="btn btn-sm btn-outline-secondary">
                        Batal
                    </a>
                @endif
            </div>

            <form
                action="{{ $endpoint
                    ? route('migration-endpoints.update', $endpoint->id)
                    : route('migration-endpoints.store') }}"
                method="POST"
                class="hosting-form"
            >
                @csrf

                @if ($endpoint)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama Hosting</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            placeholder="Contoh: Hosting Lama"
                            value="{{ old('name', $endpoint?->name) }}"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Domain / Hostname</label>
                        <input
                            type="text"
                            name="host"
                            class="form-control"
                            placeholder="server.example.com"
                            value="{{ old('host', $endpoint?->host) }}"
                            maxlength="253"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Jenis Koneksi</label>
                        <select name="connection_type" id="connection_type" class="form-select" required>
                            <option value="">Pilih koneksi</option>
                            @foreach (['ssh' => 'SSH', 'sftp' => 'SFTP', 'cpanel_api' => 'cPanel API'] as $value => $label)
                                <option value="{{ $value }}"
                                    @selected(old('connection_type', $endpoint?->connection_type) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Port</label>
                        <input
                            type="number"
                            name="port"
                            class="form-control"
                            id="endpoint_port" placeholder="Port otomatis: 22 atau 2083"
                            value="{{ old('port', $endpoint?->port) }}"
                            min="1"
                            max="65535"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" id="username_label">Username Server</label>
                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            id="endpoint_username" placeholder="Username server"
                            value="{{ old('username', $endpoint?->username) }}"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label">
                            <span id="credential_label">Password / Credential</span>
                            @if ($endpoint)
                                <span class="hosting-muted fw-normal">(Kosongkan jika tidak ingin mengubah)</span>
                            @endif
                        </label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="{{ $endpoint ? 'Kosongkan jika tidak ingin mengganti credential' : 'Masukkan password atau API Token' }}"
                            autocomplete="new-password"
                            {{ $endpoint ? '' : 'required' }}
                        >
                    </div>

                    <div class="col-12" id="fingerprint_field">
                        <label class="form-label">SHA-256 Host Key Fingerprint</label>
                        <input
                            type="text"
                            name="host_key_fingerprint"
                            class="form-control"
                            placeholder="SHA256:..."
                            value="{{ old('host_key_fingerprint', $endpoint?->host_key_fingerprint) }}"
                            maxlength="50"
                            spellcheck="false"
                            autocomplete="off"
                        >
                        <small class="hosting-muted">
                            Masukkan fingerprint resmi dari administrator atau penyedia hosting.
                            Kosongkan saat edit untuk mempertahankan fingerprint tersimpan.
                            Koneksi SSH/SFTP tidak akan diuji sebelum fingerprint terverifikasi.
                        </small>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>
                            {{ $endpoint ? 'Simpan Perubahan' : 'Simpan Hosting' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="hosting-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">Daftar Hosting</h5>
                    <p class="hosting-muted small mb-0">
                        Konfigurasi hosting yang terdaftar pada akunmu.
                    </p>
                </div>
                <span class="badge text-bg-primary">
                    {{ $endpoints->count() }} Hosting
                </span>
            </div>

            @if ($endpoints->isEmpty())
                <div class="text-center py-4">
                    <i class="bi bi-hdd-network fs-1 hosting-muted"></i>
                    <p class="fw-semibold mt-2 mb-1">Belum ada hosting terdaftar</p>
                    <p class="hosting-muted small mb-0">
                        Tambahkan konfigurasi hosting melalui formulir di atas.
                    </p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table hosting-table">
                        <thead>
                            <tr>
                                <th>Nama Hosting</th>
                                <th>Host</th>
                                <th>Koneksi</th>
                                <th>Username</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($endpoints as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item->name }}</td>
                                    <td>{{ $item->host }}{{ $item->port ? ':' . $item->port : '' }}</td>
                                    <td>
                                        <span class="hosting-type">
                                            {{ strtoupper(str_replace('_', ' ', $item->connection_type)) }}
                                        </span>
                                    </td>
                                    <td>{{ $item->username }}</td>
                                    <td class="text-end">
                                        @if ($item->connection_type === 'sftp')
                                            <a
                                                href="{{ route('migration-endpoints.files', $item->id) }}"
                                                class="btn btn-sm btn-outline-info"
                                                title="Lihat File"
                                            >
                                                <i class="bi bi-folder2-open"></i>
                                            </a>
                                        @endif
                                        <form
                                            action="{{ route('migration-endpoints.test-connection', $item->id) }}"
                                            method="POST"
                                            class="d-inline"
                                        >
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-success"
                                                title="Tes Koneksi">
                                                <i class="bi bi-plug"></i>
                                            </button>
                                        </form>
                                        <a
                                            href="{{ route('migration-endpoints.edit', $item->id) }}"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form
                                            action="{{ route('migration-endpoints.destroy', $item->id) }}"
                                            method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Yakin ingin menghapus konfigurasi hosting ini?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="alert alert-info mt-3 small">
            <i class="bi bi-info-circle me-1"></i>
            Tes Koneksi memeriksa autentikasi SSH/SFTP atau akses cPanel API tanpa mengubah file maupun konfigurasi server. Migrasi ke server sungguhan belum diaktifkan.
            Credential disimpan dalam bentuk terenkripsi.
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeField = document.getElementById('connection_type');
    const portField = document.getElementById('endpoint_port');
    const usernameLabel = document.getElementById('username_label');
    const usernameField = document.getElementById('endpoint_username');
    const credentialLabel = document.getElementById('credential_label');
    const credentialField = document.querySelector('input[name="password"]');
    const fingerprintField = document.getElementById('fingerprint_field');

    if (!typeField || !portField) return;

    function defaultPort(type) {
        return type === 'cpanel_api' ? '2083' : '22';
    }

    let previousAutoPort = null;
    const initialDefault = defaultPort(typeField.value);

    if (!portField.value || portField.value === initialDefault) {
        previousAutoPort = initialDefault;
        if (!portField.value) portField.value = initialDefault;
    }

    function updateConnectionFields() {
        const isCpanel = typeField.value === 'cpanel_api';
        const nextPort = defaultPort(typeField.value);

        if (
            !portField.value ||
            (previousAutoPort !== null && portField.value === previousAutoPort)
        ) {
            portField.value = nextPort;
            previousAutoPort = nextPort;
        } else {
            previousAutoPort = null;
        }

        portField.placeholder = isCpanel ? '2083' : '22';

        usernameLabel.textContent = isCpanel
            ? 'Username cPanel'
            : 'Username Server';

        usernameField.placeholder = isCpanel
            ? 'Username akun cPanel'
            : 'Username server';

        credentialLabel.textContent = isCpanel
            ? 'API Token cPanel'
            : 'Password / Credential';

        credentialField.placeholder = isCpanel
            ? 'Masukkan API Token cPanel'
            : 'Masukkan password atau credential';

        fingerprintField.hidden = isCpanel;
    }

    typeField.addEventListener('change', updateConnectionFields);
    updateConnectionFields();
});
</script>

</body>
</html>

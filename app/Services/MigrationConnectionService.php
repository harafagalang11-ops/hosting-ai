<?php

namespace App\Services;

use App\Models\MigrationEndpoint;
use Illuminate\Support\Facades\Http;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use Throwable;

class MigrationConnectionService
{
    private PublicHostResolver $hostResolver;

    public function __construct(
        private MigrationSshConnectionFactory $connectionFactory,
        ?PublicHostResolver $hostResolver = null
    ) {
        $this->hostResolver = $hostResolver ?? new PublicHostResolver();
    }

    /**
     * Membuka sesi SSH/SFTP terautentikasi yang dapat digunakan
     * selama proses transfer. Pemanggil wajib menutup sesi.
     */
    public function openSession(MigrationEndpoint $endpoint): MigrationRemoteSession
    {
        if (! in_array($endpoint->connection_type, ['ssh', 'sftp'], true)) {
            throw new \RuntimeException('Sesi hanya mendukung koneksi SSH atau SFTP.');
        }

        $expectedFingerprint = trim((string) $endpoint->host_key_fingerprint);

        if ($expectedFingerprint === '') {
            throw new \RuntimeException('Fingerprint host belum diatur.');
        }

        $addresses = $this->hostResolver->resolve(trim((string) $endpoint->host));

        if ($addresses === []) {
            throw new \RuntimeException('Hostname tidak valid atau mengarah ke IP privat/IP khusus.');
        }

        $connection = null;

        try {
            $connection = $this->connectionFactory->create(
                $endpoint,
                $addresses[0]
            );

            $publicHostKey = $connection->getServerPublicHostKey();

            if (! is_string($publicHostKey)) {
                throw new \RuntimeException('Identitas host SSH tidak dapat diverifikasi.');
            }

            $actualFingerprint = $this->calculateFingerprint($publicHostKey);

            if (
                $actualFingerprint === null ||
                ! hash_equals($expectedFingerprint, $actualFingerprint)
            ) {
                throw new \RuntimeException('Fingerprint host tidak cocok. Autentikasi dibatalkan.');
            }

            if (! $connection->login(
                $endpoint->username,
                (string) $endpoint->credential
            )) {
                throw new \RuntimeException('Autentikasi SSH/SFTP gagal.');
            }

            if (
                $endpoint->connection_type === 'sftp' &&
                (
                    ! ($connection instanceof SFTP) ||
                    $connection->pwd() === false
                )
            ) {
                throw new \RuntimeException('Subsistem SFTP tidak dapat digunakan.');
            }

            return new MigrationRemoteSession($connection);
        } catch (Throwable $exception) {
            if ($connection instanceof SSH2) {
                $connection->disconnect();
            }

            throw new \RuntimeException(
                $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : 'Gagal membuka sesi SSH/SFTP.',
                0,
                $exception
            );
        }
    }

    public function test(MigrationEndpoint $endpoint): array
    {
        if ($endpoint->connection_type === 'cpanel_api') {
            return $this->testCpanelApi($endpoint);
        }

        if (! in_array($endpoint->connection_type, ['ssh', 'sftp'], true)) {
            return [
                'success' => false,
                'message' => 'Jenis koneksi hosting tidak didukung.',
            ];
        }

        $expectedFingerprint = trim((string) $endpoint->host_key_fingerprint);

        if ($expectedFingerprint === '') {
            return [
                'success' => false,
                'message' => 'Fingerprint host belum diatur. Masukkan fingerprint SHA-256 resmi dari administrator hosting sebelum menguji koneksi.',
            ];
        }

        $host = trim((string) $endpoint->host);
        $addresses = $this->hostResolver->resolve($host);

        if ($addresses === []) {
            return [
                'success' => false,
                'message' => 'Hostname tidak valid, tidak dapat di-resolve, atau mengarah ke IP privat/IP khusus.',
            ];
        }

        $connection = null;

        try {
            // Socket diarahkan langsung ke IP yang sudah divalidasi.
            $connection = $this->connectionFactory->create(
                $endpoint,
                $addresses[0]
            );

            $publicHostKey = $connection->getServerPublicHostKey();

            if (! is_string($publicHostKey)) {
                return [
                    'success' => false,
                    'message' => 'Identitas host SSH tidak dapat diverifikasi. Autentikasi dibatalkan.',
                ];
            }

            $actualFingerprint = $this->calculateFingerprint($publicHostKey);

            if (
                $actualFingerprint === null ||
                ! hash_equals($expectedFingerprint, $actualFingerprint)
            ) {
                return [
                    'success' => false,
                    'message' => 'Fingerprint host tidak cocok dengan fingerprint resmi yang tersimpan. Autentikasi dibatalkan.',
                ];
            }

            $authenticated = $connection->login(
                $endpoint->username,
                (string) $endpoint->credential
            );

            if (! $authenticated) {
                return [
                    'success' => false,
                    'message' => 'Autentikasi gagal. Periksa username dan password hosting.',
                ];
            }

            if (
                $endpoint->connection_type === 'sftp' &&
                (! ($connection instanceof SFTP) || $connection->pwd() === false)
            ) {
                return [
                    'success' => false,
                    'message' => 'Autentikasi berhasil, tetapi subsistem SFTP tidak dapat digunakan.',
                ];
            }

            return [
                'success' => true,
                'message' => 'Koneksi '.strtoupper($endpoint->connection_type).' berhasil. Fingerprint host dan autentikasi terverifikasi.',
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Koneksi gagal. Periksa hostname, port, firewall, dan layanan SSH/SFTP server.',
            ];
        } finally {
            if ($connection instanceof SSH2) {
                $connection->disconnect();
            }
        }
    }

    private function testCpanelApi(MigrationEndpoint $endpoint): array
    {
        $host = trim((string) $endpoint->host);
        $username = trim((string) $endpoint->username);
        $apiToken = trim((string) $endpoint->credential);

        if ($host === '' || $username === '' || $apiToken === '') {
            return [
                'success' => false,
                'message' => 'Hostname, username cPanel, dan API token wajib diisi.',
            ];
        }

        if (
            str_contains($host, '://') ||
            str_contains($host, '/') ||
            str_contains($host, '@') ||
            str_contains($host, ' ')
        ) {
            return [
                'success' => false,
                'message' => 'Masukkan hostname saja tanpa protokol atau path URL.',
            ];
        }

        $port = (int) ($endpoint->port ?: 2083);

        if ($port < 1 || $port > 65535) {
            return [
                'success' => false,
                'message' => 'Port cPanel API tidak valid.',
            ];
        }

        $addresses = $this->hostResolver->resolve($host);

        if ($addresses === []) {
            return [
                'success' => false,
                'message' => 'Hostname tidak valid, tidak dapat di-resolve, atau mengarah ke IP privat/IP khusus.',
            ];
        }

        $urlHost = str_contains($host, ':') ? '['.$host.']' : $host;
        $url = 'https://'.$urlHost.':'.$port.'/execute/Version/get_version';

        try {
            $options = [
                'allow_redirects' => false,
            ];

            // Untuk hostname, kunci resolusi DNS ke IP yang sudah divalidasi.
            // Hostname pada URL tetap dipertahankan untuk verifikasi TLS.
            if (filter_var($host, FILTER_VALIDATE_IP) === false) {
                $address = $addresses[0];

                if (str_contains($address, ':')) {
                    $address = '['.$address.']';
                }

                $options['curl'] = [
                    CURLOPT_RESOLVE => [
                        $host.':'.$port.':'.$address,
                    ],
                ];
            }

            $response = Http::withOptions($options)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => 'cpanel '.$username.':'.$apiToken,
                ])
                ->connectTimeout(5)
                ->timeout(10)
                ->get($url);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Server cPanel menolak permintaan API. Periksa hostname, port, username, dan API token.',
                ];
            }

            $payload = $response->json();

            $apiStatus = data_get($payload, 'result.status')
                ?? data_get($payload, 'status');

            if ((int) $apiStatus !== 1) {
                return [
                    'success' => false,
                    'message' => 'Autentikasi atau pemeriksaan API cPanel gagal. Periksa username dan API token.',
                ];
            }

            $version = data_get($payload, 'result.data.version')
                ?? data_get($payload, 'data.version');

            return [
                'success' => true,
                'message' => 'Koneksi cPanel API berhasil. Versi cPanel: '.($version ?: 'berhasil diverifikasi').'.',
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Koneksi cPanel API gagal. Periksa hostname, port HTTPS, sertifikat TLS, dan akses jaringan.',
            ];
        }
    }

    private function calculateFingerprint(string $publicHostKey): ?string
    {
        $parts = preg_split('/\s+/', trim($publicHostKey));

        if (! is_array($parts) || count($parts) !== 2) {
            return null;
        }

        $keyBlob = base64_decode($parts[1], true);

        if ($keyBlob === false || $keyBlob === '') {
            return null;
        }

        return 'SHA256:'.rtrim(
            base64_encode(hash('sha256', $keyBlob, true)),
            '='
        );
    }
}

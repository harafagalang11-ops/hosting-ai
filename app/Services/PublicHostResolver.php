<?php

namespace App\Services;

use Throwable;

class PublicHostResolver
{
    public function resolve(string $host): array
    {
        $host = trim($host);

        if ($host === '') {
            return [];
        }

        // Izinkan IP langsung hanya jika termasuk IP publik.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicAddress($host) ? [$host] : [];
        }

        if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return [];
        }

        $records = [];

        try {
            $ipv4 = @gethostbynamel($host);

            if (is_array($ipv4)) {
                $records = array_merge($records, $ipv4);
            }

            $dnsRecords = @dns_get_record($host, DNS_A | DNS_AAAA);

            if (is_array($dnsRecords)) {
                foreach ($dnsRecords as $record) {
                    if (! empty($record['ip'])) {
                        $records[] = $record['ip'];
                    }

                    if (! empty($record['ipv6'])) {
                        $records[] = $record['ipv6'];
                    }
                }
            }
        } catch (Throwable $exception) {
            return [];
        }

        $records = array_values(array_unique($records));

        if ($records === []) {
            return [];
        }

        foreach ($records as $address) {
            if (! $this->isPublicAddress($address)) {
                return [];
            }
        }

        return $records;
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}

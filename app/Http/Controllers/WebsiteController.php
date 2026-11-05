<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebsiteController extends Controller
{
    public function index()
    {
        $websites = Website::withCount('monitoringLogs')
            ->orderBy('name')
            ->get();

        return view('websites.index', compact('websites'));
    }

    public function edit(Website $website)
    {
        $websites = Website::withCount('monitoringLogs')
            ->orderBy('name')
            ->get();

        $editing = $website;

        return view('websites.index', compact('websites', 'editing'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        Website::create($data);

        return redirect()->route('websites.index')
            ->with('success', 'Website berhasil ditambahkan.');
    }

    public function update(Request $request, Website $website)
    {
        $data = $this->validatedData($request, $website);

        $website->update($data);

        return redirect()->route('websites.index')
            ->with('success', 'Website berhasil diperbarui.');
    }

    public function toggle(Website $website)
    {
        $website->update(['is_active' => ! $website->is_active]);

        return back()->with('success', 'Status website berhasil diperbarui.');
    }

    public function destroy(Website $website)
    {
        $website->delete();

        return redirect()->route('websites.index')
            ->with('success', 'Website dinonaktifkan dan dihapus dari daftar aktif. Riwayat monitoring tetap disimpan.');
    }

    private function validatedData(Request $request, ?Website $website = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => [
                'required',
                'string',
                'max:2048',
                'url',
                Rule::unique('websites', 'url')->ignore($website?->id),
            ],
        ]);

        $parts = parse_url($data['url']);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));

        if (! in_array($scheme, ['http', 'https'], true) ||
            $host === '' ||
            in_array($host, ['localhost'], true) ||
            str_ends_with($host, '.localhost') ||
            str_ends_with($host, '.local') ||
            str_ends_with($host, '.internal') ||
            str_ends_with($host, '.test')) {
            throw ValidationException::withMessages([
                'url' => 'Gunakan URL HTTP/HTTPS dengan domain publik yang valid.',
            ]);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $publicIp = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ($publicIp === false) {
                throw ValidationException::withMessages([
                    'url' => 'Alamat IP lokal atau khusus tidak diizinkan.',
                ]);
            }
        }

        return $data;
    }
}

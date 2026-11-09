<?php

namespace App\Http\Controllers;

use App\Models\MonitoringLog;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    private function ownedLogs()
    {
        return MonitoringLog::whereHas('website', function ($query) {
            $query->withTrashed()
                ->where('user_id', auth()->id());
        });
    }

    public function destroy($id)
    {
        $this->ownedLogs()->findOrFail($id)->delete();

        return redirect('/riwayat')
            ->with('success', 'Riwayat monitoring berhasil dihapus.');
    }

    public function destroyAll(Request $request)
    {
        $this->ownedLogs()->delete();

        return redirect('/riwayat')
            ->with('success', 'Seluruh riwayat monitoring milik Anda berhasil dihapus.');
    }
}

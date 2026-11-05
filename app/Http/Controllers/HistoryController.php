<?php

namespace App\Http\Controllers;

use App\Models\MonitoringLog;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function destroy($id)
    {
        $log = MonitoringLog::findOrFail($id);
        $log->delete();

        return redirect('/riwayat')->with(
            'success',
            'Riwayat monitoring berhasil dihapus.'
        );
    }

    public function destroyAll(Request $request)
    {
        MonitoringLog::query()->delete();

        return redirect('/riwayat')->with(
            'success',
            'Seluruh riwayat monitoring berhasil dihapus.'
        );
    }
}

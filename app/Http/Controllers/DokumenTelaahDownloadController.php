<?php

namespace App\Http\Controllers;

use App\Models\DokumenTelaah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DokumenTelaahDownloadController extends Controller
{
    /**
     * Unduh file tanggapan reviewer — HANYA petugas unit (prd §6.1).
     *
     * Pemilik proposal sengaja tidak diberi jalan masuk sama sekali: tidak ada
     * cabang "kecuali peneliti" yang bisa lolos kalau suatu saat kondisinya
     * dilonggarkan. Kerahasiaannya berhenti di pintu ini.
     */
    public function __invoke(Request $request, DokumenTelaah $dokumen)
    {
        abort_unless(
            $request->user()->canAny(['antrian-cru.read', 'kaji-etik.read', 'antrian-reviewer.read']),
            403,
        );

        abort_unless(Storage::disk('dokumen')->exists($dokumen->path), 404);

        return Storage::disk('dokumen')->download($dokumen->path, $dokumen->nama_asli);
    }
}

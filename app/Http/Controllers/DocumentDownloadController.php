<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\ProposalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentDownloadController extends Controller
{
    /**
     * Unduh dokumen proposal dengan otorisasi:
     * - pemilik proposal atau petugas unit (permission read antrian),
     * - khusus izin_final: TERKUNCI sampai survey kepuasan terisi (prd Tahap 4).
     *
     * Tidak ada lagi penyaringan berkas rahasia di sini: file tanggapan reviewer
     * tidak pernah masuk tabel ini (lihat DokumenTelaahDownloadController).
     *
     * `?baca=1` menyajikan berkas inline (Content-Disposition: inline) alih-alih
     * memaksa unduh, supaya halaman reviewer bisa menampilkannya di dalam iframe
     * tanpa membuka tab baru. Otorisasinya SATU jalur dengan unduhan biasa —
     * mode baca hanya mengubah cara berkas disajikan, bukan siapa yang boleh.
     */
    public function __invoke(Request $request, ProposalDocument $document)
    {
        $user = $request->user();
        $proposal = $document->proposal;

        abort_unless(
            $proposal->user_id === $user->id
            || $user->canAny(['antrian-cru.read', 'kaji-etik.read', 'antrian-reviewer.read']),
            403,
        );

        if ($document->jenis === DocumentType::IzinFinal
            && $proposal->user_id === $user->id
            && ! $proposal->sudahIsiSurvey()) {
            abort(403, 'Surat izin final terkunci — isi survey kepuasan terlebih dahulu.');
        }

        abort_unless(Storage::disk('dokumen')->exists($document->path), 404);

        if ($request->boolean('baca')) {
            return Storage::disk('dokumen')->response($document->path, $document->nama_asli, [
                'Content-Disposition' => 'inline; filename="'.addslashes($document->nama_asli).'"',
            ]);
        }

        return Storage::disk('dokumen')->download($document->path, $document->nama_asli);
    }
}

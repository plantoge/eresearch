<?php

namespace App\Http\Controllers;

use App\Enums\KelengkapanDokumen;
use App\Models\Proposal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Cetak Formulir Pengajuan Etik sebagai PDF.
 *
 * Berkasnya dirakit saat diminta, bukan disimpan sebagai dokumen: isinya adalah
 * jawaban yang masih bisa dikoreksi peneliti sampai KEPK menerima berkasnya, dan
 * PDF tersimpan akan diam-diam menjadi versi lain dari data yang sama.
 */
class FormEtikPdfController extends Controller
{
    public function __invoke(Request $request, Proposal $proposal)
    {
        abort_unless($proposal->bolehDilihatOleh($request->user()), 403);

        $form = $proposal->formEtik;

        // 404, bukan halaman kosong: formulir yang belum diisi memang belum ada.
        abort_unless($form, 404, 'Formulir etik belum diisi.');

        $pdf = Pdf::loadView('pdf.formulir-etik', [
            'proposal' => $proposal,
            'form' => $form,
            'butir' => KelengkapanDokumen::cases(),
        ])->setPaper('a4');

        return $pdf->stream("formulir-etik-{$proposal->kode}.pdf");
    }
}

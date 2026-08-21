<?php

namespace App\Http\Controllers;

use App\Enums\BagianLembarInformasi;
use App\Models\Proposal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Cetak Informed Consent: Lembar Informasi yang diisi peneliti, diikuti Lembar
 * Persetujuan **kosong**.
 *
 * Lembar Persetujuan sengaja tidak berisi apa pun selain judul penelitian —
 * itulah lembar yang dibawa peneliti ke lapangan untuk ditandatangani subjek
 * dengan pena. Aplikasi tidak pernah memegang identitas subjek.
 */
class InformedConsentPdfController extends Controller
{
    public function __invoke(Request $request, Proposal $proposal)
    {
        abort_unless($proposal->bolehDilihatOleh($request->user()), 403);

        $consent = $proposal->informedConsent;

        abort_unless($consent, 404, 'Formulir informed consent belum diisi.');

        $pdf = Pdf::loadView('pdf.informed-consent', [
            'proposal' => $proposal,
            'consent' => $consent,
            'bagian' => BagianLembarInformasi::cases(),
        ])->setPaper('a4');

        return $pdf->stream("informed-consent-{$proposal->kode}.pdf");
    }
}

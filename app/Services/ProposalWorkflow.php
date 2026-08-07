<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\ProposalStatus;
use App\Enums\Unit;
use App\Models\DokumenTelaah;
use App\Models\PenugasanReviewer;
use App\Models\Proposal;
use App\Models\ProposalDocument;
use App\Models\ProposalStatusHistory;
use App\Models\TelaahReviewer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan status proposal (prd §7b).
 * Transisi dijaga canGoTo(); setiap perpindahan tercatat di
 * proposal_status_history.
 */
class ProposalWorkflow
{
    /** Subfolder disk `dokumen` untuk berkas rahasia telaah reviewer. */
    protected const FOLDER_TELAAH = 'tanggapan_reviewer';

    /**
     * Buat proposal baru dengan status awal Menunggu Verifikasi Berkas.
     */
    public function ajukan(array $data): Proposal
    {
        return DB::transaction(function () use ($data) {
            [$tahun, $nomor, $kode] = $this->generateKode();

            $status = ProposalStatus::MenungguVerifikasiBerkas;

            $proposal = Proposal::create([
                ...$data,
                'tahun' => $tahun,
                'nomor' => $nomor,
                'kode' => $kode,
                'user_id' => $data['user_id'] ?? Auth::id(),
                'status' => $status,
                'unit_sekarang' => $status->unit(),
            ]);

            $this->catatHistory($proposal, null, $status, 'Pengajuan proposal');

            return $proposal;
        });
    }

    /**
     * Pindahkan status. Abort 403 bila transisi tidak sah (cegah loncat).
     */
    public function transition(Proposal $proposal, ProposalStatus $ke, ?string $catatan = null): Proposal
    {
        $dari = $proposal->status;

        abort_unless($dari->canGoTo($ke), 403, "Transisi tidak sah: {$dari->value} → {$ke->value}");

        return DB::transaction(function () use ($proposal, $dari, $ke, $catatan) {
            $proposal->status = $ke;
            $proposal->unit_sekarang = $ke->unit();
            $proposal->save();

            $this->catatHistory($proposal, $dari, $ke, $catatan);

            return $proposal;
        });
    }

    /**
     * Simpan file dokumen; versi bertambah otomatis per jenis.
     */
    public function simpanDokumen(Proposal $proposal, DocumentType $jenis, UploadedFile $file): ProposalDocument
    {
        $versi = (int) $proposal->documents()
            ->where('jenis', $jenis->value)
            ->max('versi') + 1;

        $path = $file->store("proposal/{$proposal->id}/{$jenis->value}", 'dokumen');

        return ProposalDocument::create([
            'proposal_id' => $proposal->id,
            'jenis' => $jenis->value,
            'path' => $path,
            'nama_asli' => $file->getClientOriginalName(),
            'versi' => $versi,
            'uploaded_by' => Auth::id(),
        ]);
    }

    /**
     * Simpan file tanggapan reviewer ke tabel terpisah.
     *
     * Sengaja BUKAN lewat simpanDokumen(): berkas ini rahasia dari peneliti, dan
     * menaruhnya di proposal_documents membuat kerahasiaannya bergantung pada
     * penyaringan yang harus diingat di setiap query baru.
     */
    public function simpanDokumenTelaah(Proposal $proposal, UploadedFile $file, ?TelaahReviewer $telaah = null): DokumenTelaah
    {
        $versi = (int) $proposal->dokumenTelaah()->max('versi') + 1;

        $path = $file->store("proposal/{$proposal->id}/".self::FOLDER_TELAAH, 'dokumen');

        return DokumenTelaah::create([
            'proposal_id' => $proposal->id,
            'telaah_id' => $telaah?->id,
            'path' => $path,
            'nama_asli' => $file->getClientOriginalName(),
            'versi' => $versi,
            'uploaded_by' => Auth::id(),
        ]);
    }

    /**
     * KEPK menunjuk >=1 reviewer → proposal masuk antrian reviewer.
     *
     * @param  string[]  $reviewerIds
     */
    public function tugaskanReviewer(Proposal $proposal, array $reviewerIds, ?string $catatan = null): void
    {
        abort_unless($proposal->status === ProposalStatus::MenungguPenunjukanReviewer, 403, 'Belum saatnya penunjukan reviewer');
        abort_if($reviewerIds === [], 422, 'Pilih minimal satu reviewer');

        DB::transaction(function () use ($proposal, $reviewerIds, $catatan) {
            foreach ($reviewerIds as $id) {
                $a = PenugasanReviewer::withTrashed()
                    ->firstOrNew(['proposal_id' => $proposal->id, 'reviewer_id' => $id]);
                $a->status = PenugasanReviewer::MENUNGGU;
                $a->deleted_at = null;
                $a->save();
            }

            $this->transition($proposal, ProposalStatus::MenungguReviewReviewer,
                $catatan ?: 'Reviewer ditugaskan oleh KEPK');
        });
    }

    /**
     * Reviewer merespons (jawaban ke KEPK, bukan ke peneliti):
     * catat komentar+keputusan per ronde, update status penugasan.
     * Bila SEMUA reviewer sudah ACC → otomatis "Disetujui Reviewer" (bola KEPK).
     */
    public function reviewerMerespons(Proposal $proposal, string $keputusan, ?string $komentar = null, ?UploadedFile $fileTanggapan = null): void
    {
        abort_unless($proposal->status === ProposalStatus::MenungguReviewReviewer, 403, 'Proposal tidak sedang direview');
        abort_unless(in_array($keputusan, ['approve', 'revise'], true), 422);

        $assignment = $proposal->penugasanReviewer()
            ->where('reviewer_id', Auth::id())
            ->first();

        abort_unless($assignment, 403, 'Anda tidak ditugaskan pada proposal ini');

        DB::transaction(function () use ($proposal, $assignment, $keputusan, $komentar, $fileTanggapan) {
            $ronde = (int) $proposal->telaahReviewer()
                ->where('reviewer_id', Auth::id())
                ->max('ronde') + 1;

            $telaah = TelaahReviewer::create([
                'proposal_id' => $proposal->id,
                'unit' => Unit::Reviewer->value,
                'reviewer_id' => Auth::id(),
                'keputusan' => $keputusan,
                'komentar' => $komentar,
                'ronde' => $ronde,
            ]);

            if ($fileTanggapan) {
                $this->simpanDokumenTelaah($proposal, $fileTanggapan, $telaah);
            }

            $assignment->update([
                'status' => $keputusan === 'approve'
                    ? PenugasanReviewer::ACC
                    : PenugasanReviewer::REVISI,
            ]);

            if ($keputusan === 'approve' && $proposal->semuaReviewerAcc()) {
                $this->transition($proposal, ProposalStatus::DisetujuiReviewer, 'Semua reviewer ACC');
            }
        });
    }

    /** Peneliti kirim revisi etik → semua penugasan kembali "menunggu" (ronde baru). */
    public function resetPenugasanReviewer(Proposal $proposal): void
    {
        $proposal->penugasanReviewer()->update(['status' => PenugasanReviewer::MENUNGGU]);
    }

    /**
     * Kode proposal format RSPISS-YYYY-### (D6), nomor increment per tahun.
     * Lock baris tahun berjalan agar bebas race.
     *
     * @return array{0:int,1:int,2:string}
     */
    public function generateKode(): array
    {
        $tahun = (int) now()->year;

        // PG melarang FOR UPDATE + agregat; pakai advisory lock per tahun
        // (rilis otomatis saat transaksi selesai). Unique(tahun,nomor) tetap
        // jadi jaring pengaman terakhir.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('select pg_advisory_xact_lock(?)', [$tahun]);
        }

        $nomor = (int) Proposal::withTrashed()
            ->where('tahun', $tahun)
            ->max('nomor') + 1;

        $kode = sprintf('RSPISS-%d-%03d', $tahun, $nomor);

        return [$tahun, $nomor, $kode];
    }

    protected function catatHistory(Proposal $proposal, ?ProposalStatus $dari, ProposalStatus $ke, ?string $catatan): void
    {
        ProposalStatusHistory::create([
            'proposal_id' => $proposal->id,
            'from_status' => $dari?->value,
            'to_status' => $ke->value,
            'unit' => $ke->unit()?->value,
            'actor_id' => Auth::id(),
            'catatan' => $catatan,
        ]);
    }
}

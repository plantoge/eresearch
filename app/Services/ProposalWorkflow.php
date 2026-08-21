<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\ProposalStatus;
use App\Enums\TipeProposal;
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
     *
     * `$tipe` sengaja parameter tersendiri, bukan key di `$data`: ia ikut
     * tercetak di nomor proposal yang bersifat permanen, jadi pemanggil harus
     * gagal keras kalau lupa mengisinya — bukan diam-diam jatuh ke Internal.
     */
    public function ajukan(array $data, TipeProposal $tipe): Proposal
    {
        return DB::transaction(function () use ($data, $tipe) {
            [$tahun, $bulan, $nomor, $kode] = $this->generateKode($tipe);

            $status = ProposalStatus::MenungguVerifikasiBerkas;

            $proposal = Proposal::create([
                ...$data,
                'tipe_proposal' => $tipe,
                'tahun' => $tahun,
                'bulan' => $bulan,
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
     * Satu-satunya tempat nomor proposal dirakit — dipakai juga oleh seeder,
     * supaya format tidak pernah ditulis ulang di dua tempat.
     *
     * Bentuknya 10 digit rapat: tipe(2) + tahun(2) + bulan(2) + urut(4).
     * Contoh: 0126080001 = internal, Agustus 2026, urutan ke-1.
     */
    public static function formatKode(TipeProposal $tipe, int $tahun, int $bulan, int $nomor): string
    {
        return sprintf('%s%02d%02d%04d', $tipe->value, $tahun % 100, $bulan, $nomor);
    }

    /**
     * Terbitkan nomor proposal (D6). Deret `nomor` increment **per tahun** dan
     * dipakai bersama oleh internal & eksternal — bulan hanya menandai kapan
     * nomor terbit, bukan pembatas deret. Lock tahun berjalan agar bebas race.
     *
     * @return array{0:int,1:int,2:int,3:string} [tahun, bulan, nomor, kode]
     */
    public function generateKode(TipeProposal $tipe): array
    {
        $sekarang = now();
        $tahun = (int) $sekarang->year;
        $bulan = (int) $sekarang->month;

        // PG melarang FOR UPDATE + agregat; pakai advisory lock per tahun
        // (rilis otomatis saat transaksi selesai). Unique(tahun,nomor) tetap
        // jadi jaring pengaman terakhir.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('select pg_advisory_xact_lock(?)', [$tahun]);
        }

        $nomor = (int) Proposal::withTrashed()
            ->where('tahun', $tahun)
            ->max('nomor') + 1;

        return [$tahun, $bulan, $nomor, self::formatKode($tipe, $tahun, $bulan, $nomor)];
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

<?php

namespace App\Livewire\Proposal;

use App\Enums\DocumentType;
use App\Enums\JenisTelaah;
use App\Enums\KeputusanEtik;
use App\Enums\ProposalStatus;
use App\Enums\StatusPembayaran;
use App\Enums\TujuanPembayaran;
use App\Enums\Unit;
use App\Models\BerkasPenelitian;
use App\Models\DokumenTelaah;
use App\Models\InformasiKontak;
use App\Models\IzinPenelitian;
use App\Models\MasterAspek;
use App\Models\MasterSkala;
use App\Models\Proposal;
use App\Models\ProposalDocument;
use App\Models\Respon;
use App\Models\TelaahReviewer;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class Show extends Component
{
    use Toast, WithFileUploads;

    public Proposal $proposal;

    public string $catatan = '';

    // Presentasi (CRU) — disimpan di berkas kerja CRU, bukan di tabel proposal
    public string $tanggal_presentasi = '';

    public string $kategori_presentasi = '';

    public string $media_presentasi = '';

    // Protokol etik (KEPK) — penomoran & telaah milik KEPK sendiri
    public string $nomor_protokol = '';

    public string $jenis_telaah = '';

    public string $tanggal_sidang = '';

    public string $nomor_ec = '';

    // Upload generik per aksi
    public $fileUpload;          // satu file (surat tanggapan/penolakan/izin/bukti bayar/revisi proposal)

    public $fileEtik = [];       // form_kaji_etik, informed_consent, pks, kerahasiaan_data

    public $fileLaporan;

    public $fileRawData;

    public $fileBayarCru;

    public $fileBayarKepk;

    // Penunjukan reviewer (KEPK)
    public array $reviewerTerpilih = [];

    // Survey
    public array $jawabanSurvey = []; // pertanyaan_id => skala_id

    public string $saran = '';

    public function mount(Proposal $proposal)
    {
        $user = auth()->user();

        abort_unless(
            $proposal->user_id === $user->id
                || $user->canAny(['antrian-cru.read', 'kaji-etik.read'])
                || ($user->can('antrian-reviewer.read')
                    && $proposal->penugasanReviewer()->where('reviewer_id', $user->id)->exists()),
            403,
        );

        $this->proposal = $proposal;

        // Prefill dari berkas kerja masing-masing unit supaya form menampilkan
        // nilai yang sudah tersimpan, bukan kosong.
        if ($berkas = $proposal->berkasPenelitian) {
            $this->tanggal_presentasi = $berkas->tanggal_presentasi?->format('Y-m-d\TH:i') ?? '';
            $this->kategori_presentasi = $berkas->kategori_presentasi ?? '';
            $this->media_presentasi = $berkas->media_presentasi ?? '';
        }

        if ($protokol = $proposal->protokolEtik) {
            $this->nomor_protokol = $protokol->nomor_protokol ?? '';
            $this->jenis_telaah = $protokol->jenis_telaah?->value ?? '';
            $this->tanggal_sidang = $protokol->tanggal_sidang?->format('Y-m-d\TH:i') ?? '';
            $this->nomor_ec = $protokol->nomor_ec ?? '';
        }
    }

    protected function pemilik(): bool
    {
        return $this->proposal->user_id === auth()->id();
    }

    protected function pindah(ProposalStatus $ke, ?string $catatan = null): void
    {
        app(ProposalWorkflow::class)->transition($this->proposal, $ke, $catatan ?: null);
        $this->proposal->refresh();
        $this->reset('catatan', 'fileUpload', 'fileEtik', 'fileLaporan', 'fileRawData', 'fileBayarCru', 'fileBayarKepk');
        $this->success("Status: {$ke->value}");
    }

    protected function simpanFile(DocumentType $jenis, $file): ProposalDocument
    {
        return app(ProposalWorkflow::class)->simpanDokumen($this->proposal, $jenis, $file);
    }

    // ============ Aksi Peneliti ============

    /** Perbaiki proposal (T1): re-upload → Menunggu Verifikasi Revisi. */
    public function kirimRevisi()
    {
        abort_unless($this->pemilik(), 403);
        $this->validate(['fileUpload' => 'required|'.DocumentType::Proposal->aturanValidasi()]);

        $this->simpanFile(DocumentType::Proposal, $this->fileUpload);
        $this->pindah(ProposalStatus::MenungguVerifikasiRevisi, $this->catatan);
    }

    /** Lengkapi 4 berkas etik (T2) → diarahkan ke KEPK untuk penunjukan reviewer. */
    public function kirimBerkasEtik()
    {
        abort_unless($this->pemilik(), 403);

        $rules = [];
        foreach (DocumentType::wajibTahap2() as $jenis) {
            $rules["fileEtik.{$jenis->value}"] = 'required|'.$jenis->aturanValidasi();
        }
        $this->validate($rules);

        foreach (DocumentType::wajibTahap2() as $jenis) {
            $this->simpanFile($jenis, $this->fileEtik[$jenis->value]);
        }

        // Berkas etik masuk → KEPK punya sesuatu untuk dikerjakan, jadi berkas
        // kerjanya dibuat di sini (bukan saat pengajuan, saat KEPK belum terlibat).
        $this->proposal->protokolEtik()->firstOrCreate([]);

        $this->pindah(ProposalStatus::MenungguPenunjukanReviewer, $this->catatan);
    }

    /** Perbaiki berkas etik sesuai komentar reviewer (loop, opsional per berkas). */
    public function kirimRevisiEtik()
    {
        abort_unless($this->pemilik(), 403);

        $adaFile = false;
        foreach (DocumentType::wajibTahap2() as $jenis) {
            if (! empty($this->fileEtik[$jenis->value])) {
                $this->validate(["fileEtik.{$jenis->value}" => $jenis->aturanValidasi()]);
                $this->simpanFile($jenis, $this->fileEtik[$jenis->value]);
                $adaFile = true;
            }
        }

        if (! $adaFile) {
            $this->addError('fileEtik', 'Unggah minimal satu berkas revisi.');

            return;
        }

        // Ronde baru: semua reviewer kembali "menunggu"
        app(ProposalWorkflow::class)->resetPenugasanReviewer($this->proposal);
        $this->pindah(ProposalStatus::MenungguReviewReviewer, $this->catatan);
    }

    /** Upload bukti bayar CRU + KEPK (T3, dua pembayaran terpisah). */
    public function kirimBuktiBayar()
    {
        abort_unless($this->pemilik(), 403);
        $this->validate([
            'fileBayarCru' => 'required|'.DocumentType::BuktiBayarCru->aturanValidasi(),
            'fileBayarKepk' => 'required|'.DocumentType::BuktiBayarKepk->aturanValidasi(),
        ]);

        $file = [
            TujuanPembayaran::Cru->value => $this->fileBayarCru,
            TujuanPembayaran::Kepk->value => $this->fileBayarKepk,
        ];

        foreach (TujuanPembayaran::cases() as $tujuan) {
            $dokumen = $this->simpanFile($tujuan->jenisDokumen(), $file[$tujuan->value]);

            // Kirim ulang setelah ditolak memakai baris yang sama (partial unique
            // per proposal+tujuan) — riwayat buktinya ada di versi dokumen.
            $this->proposal->pembayaran()->updateOrCreate(
                ['tujuan' => $tujuan->value],
                [
                    'status' => StatusPembayaran::Menunggu->value,
                    'dokumen_id' => $dokumen->id,
                    'diverifikasi_oleh' => null,
                    'diverifikasi_pada' => null,
                    'catatan' => null,
                ],
            );
        }

        $this->pindah(ProposalStatus::MenungguVerifikasiPembayaran);
    }

    /** Upload laporan + raw data (T4) → Menunggu Verifikasi Akhir. */
    public function kirimLaporan()
    {
        abort_unless($this->pemilik(), 403);
        $this->validate([
            'fileLaporan' => 'required|'.DocumentType::LaporanPenelitian->aturanValidasi(),
            'fileRawData' => 'required|'.DocumentType::RawData->aturanValidasi(),
        ]);

        $this->simpanFile(DocumentType::LaporanPenelitian, $this->fileLaporan);
        $this->simpanFile(DocumentType::RawData, $this->fileRawData);
        $this->pindah(ProposalStatus::MenungguVerifikasiAkhir);
    }

    /** Isi survey kepuasan (gate) → Selesai; izin final terbuka. */
    public function kirimSurvey()
    {
        abort_unless($this->pemilik(), 403);
        abort_unless($this->proposal->status === ProposalStatus::MenungguSurveyKepuasan, 403);

        $wajib = MasterAspek::where('status_aktif', true)->with('pertanyaan')->get()
            ->flatMap->pertanyaan->where('status_aktif', true)->where('is_required', true);

        foreach ($wajib as $p) {
            if (empty($this->jawabanSurvey[$p->id])) {
                $this->addError('jawabanSurvey', 'Semua pertanyaan wajib dijawab.');

                return;
            }
        }

        $user = auth()->user();

        $respon = Respon::create([
            'proposal_id' => $this->proposal->id,
            'responden_id' => $user->id,
            'responden' => $user->name,
            'jenis_responden' => 'peneliti',
            'saran' => $this->saran,
        ]);

        $skala = MasterSkala::pluck('nama_skala', 'id');
        $teks = $wajib->pluck('pertanyaan', 'id');

        foreach ($this->jawabanSurvey as $pertanyaanId => $skalaId) {
            $respon->jawaban()->create([
                'master_pertanyaan_id' => $pertanyaanId,
                'master_skala_id' => $skalaId,
                'pertanyaan' => $teks[$pertanyaanId] ?? null,
                'jawaban' => $skala[$skalaId] ?? null,
            ]);
        }

        $this->pindah(ProposalStatus::Selesai, 'Survey kepuasan diisi');
    }

    // ============ Aksi CRU ============

    public function mintaRevisi()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);

        if ($this->fileUpload) {
            $this->validate(['fileUpload' => DocumentType::SuratTanggapan->aturanValidasi()]);
            $this->simpanFile(DocumentType::SuratTanggapan, $this->fileUpload);
        }

        $this->catatVerifikasiCru();
        $this->pindah(ProposalStatus::PerluRevisiProposal, $this->catatan);
    }

    public function mintaPresentasi()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);
        $this->validate([
            'tanggal_presentasi' => 'required|date',
            'kategori_presentasi' => 'required|string',
            'media_presentasi' => 'required|string',
        ]);

        $this->berkasCru()->update([
            'tanggal_presentasi' => $this->tanggal_presentasi,
            'kategori_presentasi' => $this->kategori_presentasi,
            'media_presentasi' => $this->media_presentasi,
        ]);

        $this->pindah(ProposalStatus::MenungguPresentasi, $this->catatan);
    }

    public function tolak()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);
        $this->validate(['fileUpload' => 'required|'.DocumentType::SuratPenolakan->aturanValidasi()]);

        $this->simpanFile(DocumentType::SuratPenolakan, $this->fileUpload);
        $this->catatVerifikasiCru();
        $this->pindah(ProposalStatus::Ditolak, $this->catatan);
    }

    public function loloskan()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);
        $this->catatVerifikasiCru();
        $this->pindah(ProposalStatus::MenungguKelengkapanBerkasEtik, $this->catatan ?: 'Lolos ke KEPK');
    }

    /** Verifikasi bukti bayar + terbit draft izin → Pelaksanaan Penelitian. */
    public function terbitkanDraftIzin()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);
        $this->validate(['fileUpload' => 'required|'.DocumentType::IzinDraft->aturanValidasi()]);

        $this->simpanFile(DocumentType::IzinDraft, $this->fileUpload);

        $this->proposal->pembayaran()->update([
            'status' => StatusPembayaran::Terverifikasi->value,
            'diverifikasi_oleh' => auth()->id(),
            'diverifikasi_pada' => now(),
            'catatan' => null,
        ]);

        $this->izinCru()->update([
            'tanggal_terbit_draft' => now(),
            'diterbitkan_oleh' => auth()->id(),
        ]);

        $this->pindah(ProposalStatus::PelaksanaanPenelitian, $this->catatan);
    }

    /** D4: bukti bayar tidak sah → kembali Menunggu Pembayaran. */
    public function tolakBuktiBayar()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);

        $this->proposal->pembayaran()->update([
            'status' => StatusPembayaran::Ditolak->value,
            'diverifikasi_oleh' => auth()->id(),
            'diverifikasi_pada' => now(),
            'catatan' => $this->catatan ?: 'Bukti pembayaran ditolak',
        ]);

        $this->pindah(ProposalStatus::MenungguPembayaran, $this->catatan ?: 'Bukti pembayaran ditolak');
    }

    /** Terbit izin final (unduh terkunci survey) → Menunggu Survey Kepuasan. */
    public function terbitkanIzinFinal()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);
        $this->validate(['fileUpload' => 'required|'.DocumentType::IzinFinal->aturanValidasi()]);

        $this->simpanFile(DocumentType::IzinFinal, $this->fileUpload);

        $this->izinCru()->update([
            'tanggal_terbit_final' => now(),
            'diterbitkan_oleh' => auth()->id(),
        ]);

        $this->pindah(ProposalStatus::MenungguSurveyKepuasan, $this->catatan);
    }

    /** D4: laporan/raw data kurang → kembali Pelaksanaan Penelitian. */
    public function tolakLaporan()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);
        $this->pindah(ProposalStatus::PelaksanaanPenelitian, $this->catatan ?: 'Laporan perlu diperbaiki');
    }

    public function batalkan()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);
        $this->pindah(ProposalStatus::Dibatalkan, $this->catatan ?: 'Dibatalkan');
    }

    // ============ Aksi Reviewer (jawaban ke KEPK, bukan ke peneliti) ============

    public function reviewerMintaRevisi()
    {
        abort_unless(auth()->user()->can('antrian-reviewer.update'), 403);
        $this->validate(['catatan' => 'required|string'], [], ['catatan' => 'komentar']);

        if ($this->fileUpload) {
            $this->validate(['fileUpload' => DokumenTelaah::ATURAN_VALIDASI]);
        }

        app(ProposalWorkflow::class)->reviewerMerespons($this->proposal, 'revise', $this->catatan, $this->fileUpload);
        $this->proposal->refresh();
        $this->reset('catatan', 'fileUpload');
        $this->success('Tanggapan revisi terkirim ke KEPK.');
    }

    public function reviewerAcc()
    {
        abort_unless(auth()->user()->can('antrian-reviewer.update'), 403);

        if ($this->fileUpload) {
            $this->validate(['fileUpload' => DokumenTelaah::ATURAN_VALIDASI]);
        }

        app(ProposalWorkflow::class)->reviewerMerespons($this->proposal, 'approve', $this->catatan, $this->fileUpload);
        $this->proposal->refresh();
        $this->reset('catatan', 'fileUpload');
        $this->success('ACC terkirim ke KEPK.');
    }

    // ============ Aksi KEPK ============

    /** KEPK menunjuk >=1 reviewer. */
    public function tugaskanReviewer()
    {
        abort_unless(auth()->user()->can('kaji-etik.update'), 403);
        $this->validate(['reviewerTerpilih' => 'required|array|min:1'], [], ['reviewerTerpilih' => 'reviewer']);

        $this->simpanProtokolEtik();

        app(ProposalWorkflow::class)->tugaskanReviewer($this->proposal, $this->reviewerTerpilih, $this->catatan);
        $this->proposal->refresh();
        $this->reset('catatan', 'reviewerTerpilih');
        $this->success('Reviewer ditugaskan.');
    }

    /**
     * KEPK meneruskan masukan reviewer ke peneliti (identitas reviewer tetap rahasia).
     * Hanya bisa bila sudah ADA reviewer yang meminta revisi — keputusan KEPK
     * mengikuti hasil review, bukan inisiatif sendiri.
     */
    public function kepkTeruskanRevisi()
    {
        abort_unless(auth()->user()->can('kaji-etik.update'), 403);
        abort_unless(
            $this->proposal->penugasanReviewer()->where('status', 'revisi')->exists(),
            403,
            'Belum ada reviewer yang meminta revisi',
        );
        $this->validate(['catatan' => 'required|string'], [], ['catatan' => 'catatan untuk peneliti']);

        // Surat tanggapan resmi KEPK untuk peneliti (opsional, terlihat peneliti)
        if ($this->fileUpload) {
            $this->validate(['fileUpload' => DocumentType::SuratTanggapan->aturanValidasi()]);
            $this->simpanFile(DocumentType::SuratTanggapan, $this->fileUpload);
        }

        $this->pindah(ProposalStatus::PerluRevisiReviewer, $this->catatan);
    }

    /** KEPK loloskan ke pembayaran — hanya bila SEMUA reviewer ACC. */
    public function kepkLanjut()
    {
        abort_unless(auth()->user()->can('kaji-etik.update'), 403);
        abort_unless($this->proposal->semuaReviewerAcc(), 403, 'Belum semua reviewer memberikan ACC');

        $this->simpanProtokolEtik([
            'keputusan' => KeputusanEtik::Layak->value,
            'nomor_ec' => $this->nomor_ec ?: null,
            'tanggal_terbit_ec' => now(),
        ]);

        $this->pindah(ProposalStatus::MenungguPembayaran, $this->catatan ?: 'Lanjut ke pembayaran');
    }

    public function kepkTolak()
    {
        abort_unless(auth()->user()->can('kaji-etik.update'), 403);
        $this->validate(['catatan' => 'required|string'], [], ['catatan' => 'alasan penolakan']);

        TelaahReviewer::create([
            'proposal_id' => $this->proposal->id,
            'unit' => Unit::KajiEtik->value,
            'reviewer_id' => auth()->id(),
            'keputusan' => 'reject',
            'komentar' => $this->catatan,
            'ronde' => (int) $this->proposal->telaahReviewer()->max('ronde') + 1,
        ]);

        $this->simpanProtokolEtik(['keputusan' => KeputusanEtik::TidakLayak->value]);

        $this->pindah(ProposalStatus::DitolakKajiEtik, $this->catatan);
    }

    // ============ Berkas kerja per unit ============

    /** Berkas kerja CRU, dibuat saat CRU pertama kali menyentuh proposal. */
    protected function berkasCru(): BerkasPenelitian
    {
        return $this->proposal->berkasPenelitian()->firstOrCreate([]);
    }

    protected function izinCru(): IzinPenelitian
    {
        return $this->proposal->izinPenelitian()->firstOrCreate([]);
    }

    /** Jejak siapa memverifikasi berkas Tahap 1 dan kapan. */
    protected function catatVerifikasiCru(): void
    {
        $this->berkasCru()->update([
            'catatan_verifikasi' => $this->catatan ?: null,
            'diverifikasi_oleh' => auth()->id(),
            'diverifikasi_pada' => now(),
        ]);
    }

    protected function simpanProtokolEtik(array $tambahan = []): void
    {
        $this->proposal->protokolEtik()->firstOrCreate([])->update([
            'nomor_protokol' => $this->nomor_protokol ?: null,
            'jenis_telaah' => $this->jenis_telaah ?: null,
            'tanggal_sidang' => $this->tanggal_sidang ?: null,
            ...$tambahan,
        ]);
    }

    public function render()
    {
        $s = $this->proposal->status;
        $user = auth()->user();

        // Kerahasiaan: komentar reviewer TIDAK terlihat oleh peneliti;
        // KEPK yang meneruskan intinya lewat catatan status.
        $bolehLihatReview = $user->canAny(['antrian-cru.read', 'kaji-etik.read', 'antrian-reviewer.read']);

        // Tidak perlu disaring lagi — berkas rahasia telaah tidak ada di tabel ini.
        $dokumen = $this->proposal->documents()
            ->orderBy('jenis')->orderByDesc('versi')->get()->groupBy('jenis');

        $dokumenTelaah = $bolehLihatReview
            ? $this->proposal->dokumenTelaah()->orderByDesc('versi')->get()
            : collect();

        $history = $this->proposal->statusHistory()->with('actor')->get();

        $reviews = $bolehLihatReview
            ? $this->proposal->telaahReviewer()->with('reviewer')->latest('created_at')->get()
            : collect();

        $assignments = $this->proposal->penugasanReviewer()->with('reviewer')->get();

        $reviewerOptions = $s === ProposalStatus::MenungguPenunjukanReviewer && $user->can('kaji-etik.update')
            ? User::role('reviewer')->orderBy('name')->get(['id', 'name'])
            : collect();

        $penugasanSaya = $this->proposal->penugasanReviewer()
            ->where('reviewer_id', $user->id)->first();

        $aspekSurvey = $s === ProposalStatus::MenungguSurveyKepuasan && $this->pemilik()
            ? MasterAspek::where('status_aktif', true)->orderBy('urutan')
                ->with(['pertanyaan' => fn ($q) => $q->where('status_aktif', true)->orderBy('urutan')])->get()
            : collect();

        $skalaSurvey = MasterSkala::orderBy('urutan')->get();
        $kontak = InformasiKontak::query()->first();
        $isCru = $user->can('antrian-cru.update');
        $isKepk = $user->can('kaji-etik.update');
        $isReviewer = $user->can('antrian-reviewer.update');
        $isPemilik = $this->pemilik();

        return view('livewire.proposal.show', [
            'dokumen' => $dokumen,
            'dokumenTelaah' => $dokumenTelaah,
            'berkasCru' => $this->proposal->berkasPenelitian,
            'protokolEtik' => $this->proposal->protokolEtik,
            'pembayaran' => $isCru || $isPemilik ? $this->proposal->pembayaran()->get() : collect(),
            'opsiJenisTelaah' => JenisTelaah::cases(),
            'history' => $history,
            'reviews' => $reviews,
            'bolehLihatReview' => $bolehLihatReview,
            'assignments' => $assignments,
            'reviewerOptions' => $reviewerOptions,
            'penugasanSaya' => $penugasanSaya,
            'aspekSurvey' => $aspekSurvey,
            'skalaSurvey' => $skalaSurvey,
            'kontak' => $kontak,
            'isCru' => $isCru,
            'isKepk' => $isKepk,
            'isReviewer' => $isReviewer,
            'isPemilik' => $isPemilik,
        ])->title($this->proposal->kode);
    }
}

<?php

namespace App\Livewire\Proposal;

use App\Enums\BentukKerjasama;
use App\Enums\DocumentType;
use App\Enums\JenisTelaah;
use App\Enums\KelengkapanDokumen;
use App\Enums\KeputusanEtik;
use App\Enums\ProposalStatus;
use App\Enums\StatusPembayaran;
use App\Enums\TujuanPembayaran;
use App\Enums\Unit;
use App\Models\BerkasPenelitian;
use App\Models\DokumenTelaah;
use App\Models\FormEtik;
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
use Illuminate\Validation\Rule;
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

    public $fileEtik = [];       // informed_consent, kerahasiaan_data

    /**
     * Poin B formulir etik: huruf butir => dicentang. Butir yang tidak dicentang
     * boleh absen dari array — checkbox HTML memang tidak mengirim apa pun saat
     * kosong, dan absennya sudah berarti "tidak dilampirkan".
     */
    public array $kelengkapan = [];

    /** Modal baca formulir etik di kartu Dokumen. */
    public bool $modalFormEtik = false;

    /**
     * Poin C formulir etik. Jawaban ya/tidak disimpan sebagai '1'/'0' — BUKAN
     * bool — supaya "belum dijawab" ('') bisa dibedakan dari "tidak" dan
     * ditangkap `required`; bool tidak punya nilai ketiga untuk itu.
     */
    public array $formEtik = [
        'multisenter' => '',
        'senter_utama' => '',
        'senter_satelit' => '',
        'kerjasama' => '',
        'jumlah_negara' => '',
        'peneliti_asing' => '',
        'pernah_diajukan' => '',
        'disetujui_komisi_lain' => '',
        'sampel_ke_luar_negeri' => '',
        'negara_tujuan' => '',
        'registrasi_bpom' => '',
    ];

    public $filePks;             // diunggah CRU, terpisah dari berkas etik peneliti

    public $fileProposal;        // proposal ikut diperbaiki saat KEPK mengembalikan berkas

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

        abort_unless($proposal->bolehDilihatOleh($user), 403);

        $this->proposal = $proposal;

        // Prefill dari berkas kerja masing-masing unit supaya form menampilkan
        // nilai yang sudah tersimpan, bukan kosong.
        if ($berkas = $proposal->berkasPenelitian) {
            $this->tanggal_presentasi = $berkas->tanggal_presentasi?->format('Y-m-d\TH:i') ?? '';
            $this->kategori_presentasi = $berkas->kategori_presentasi ?? '';
            $this->media_presentasi = $berkas->media_presentasi ?? '';
        }

        if ($form = $proposal->formEtik) {
            $this->isiUlangFormEtik($form);
        }

        if ($protokol = $proposal->protokolEtik) {
            $this->nomor_protokol = $protokol->nomor_protokol ?? '';
            $this->jenis_telaah = $protokol->jenis_telaah?->value ?? '';
            $this->tanggal_sidang = $protokol->tanggal_sidang?->format('Y-m-d\TH:i') ?? '';
            $this->nomor_ec = $protokol->nomor_ec ?? '';
        }
    }

    /**
     * Formulir etik proposal ini sudah pernah diisi?
     *
     * Jalur perbaikan memakainya untuk memutuskan apakah Poin B & C ikut
     * divalidasi: proposal lama yang dulu mengunggah PDF `form_kaji_etik` tidak
     * punya jawaban apa pun, dan menuntutnya di sini akan mengunci berkasnya di
     * layar perbaikan tanpa jalan keluar.
     */
    protected function formEtikSudahDiisi(): bool
    {
        return $this->proposal->formEtik !== null;
    }

    /** Muat jawaban formulir etik yang tersimpan ke properti form. */
    protected function isiUlangFormEtik(FormEtik $form): void
    {
        $this->kelengkapan = $form->kelengkapan ?? [];

        $this->formEtik = [
            'multisenter' => self::keForm($form->multisenter),
            'senter_utama' => $form->senter_utama ?? '',
            'senter_satelit' => $form->senter_satelit ?? '',
            'kerjasama' => $form->kerjasama?->value ?? '',
            'jumlah_negara' => $form->jumlah_negara ?? '',
            'peneliti_asing' => self::keForm($form->peneliti_asing),
            'pernah_diajukan' => self::keForm($form->pernah_diajukan),
            'disetujui_komisi_lain' => self::keForm($form->disetujui_komisi_lain),
            'sampel_ke_luar_negeri' => self::keForm($form->sampel_ke_luar_negeri),
            'negara_tujuan' => $form->negara_tujuan ?? '',
            'registrasi_bpom' => $form->registrasi_bpom ?? '',
        ];
    }

    /** bool database => '1'/'0'/'' (belum dijawab) yang dipakai radio di form. */
    protected static function keForm(?bool $nilai): string
    {
        return $nilai === null ? '' : ($nilai ? '1' : '0');
    }

    /** Jawaban Poin C bernilai "ya"? Dipakai untuk syarat lanjutan. */
    protected function ya(string $kunci): bool
    {
        return ($this->formEtik[$kunci] ?? '') === '1';
    }

    /**
     * Aturan Poin B & C.
     *
     * Tidak ada butir Poin B yang wajib dicentang: sebagian butir memang hanya
     * berlaku pada penelitian tertentu, dan mewajibkannya hanya akan melatih
     * peneliti mencentang tanpa membaca. Yang ditegakkan adalah Poin C — di situ
     * setiap pertanyaan berlaku untuk semua penelitian.
     */
    protected function aturanFormEtik(): array
    {
        return [
            'kelengkapan' => 'array',
            'kelengkapan.*' => 'boolean',

            'formEtik.multisenter' => 'required|boolean',
            'formEtik.senter_utama' => [Rule::requiredIf(fn () => $this->ya('multisenter')), 'nullable', 'string', 'max:255'],
            'formEtik.senter_satelit' => 'nullable|string|max:255',

            'formEtik.kerjasama' => ['required', Rule::enum(BentukKerjasama::class)],
            'formEtik.jumlah_negara' => [
                Rule::requiredIf(fn () => ($this->formEtik['kerjasama'] ?? '') === BentukKerjasama::Internasional->value),
                'nullable', 'string', 'max:255',
            ],
            'formEtik.peneliti_asing' => 'required|boolean',

            'formEtik.pernah_diajukan' => 'required|boolean',
            'formEtik.disetujui_komisi_lain' => [Rule::requiredIf(fn () => $this->ya('pernah_diajukan')), 'nullable', 'boolean'],

            'formEtik.sampel_ke_luar_negeri' => 'required|boolean',
            'formEtik.negara_tujuan' => [Rule::requiredIf(fn () => $this->ya('sampel_ke_luar_negeri')), 'nullable', 'string', 'max:255'],

            'formEtik.registrasi_bpom' => 'nullable|string',
        ];
    }

    /**
     * Simpan Poin B & C.
     *
     * Jawaban lanjutan yang syaratnya tidak lagi berlaku dikosongkan, bukan
     * dibiarkan: menyimpan "senter utama" pada penelitian yang baru saja diubah
     * jadi bukan-multisenter membuat KEPK membaca dua jawaban yang bertentangan.
     */
    protected function simpanFormEtik(): void
    {
        $f = $this->formEtik;
        $multisenter = $f['multisenter'] === '1';
        $internasional = $f['kerjasama'] === BentukKerjasama::Internasional->value;
        $pernahDiajukan = $f['pernah_diajukan'] === '1';
        $keLuarNegeri = $f['sampel_ke_luar_negeri'] === '1';

        $kelengkapan = [];
        foreach (KelengkapanDokumen::cases() as $butir) {
            $kelengkapan[$butir->value] = (bool) ($this->kelengkapan[$butir->value] ?? false);
        }

        $this->proposal->formEtik()->updateOrCreate(
            ['proposal_id' => $this->proposal->id],
            [
                'kelengkapan' => $kelengkapan,
                'multisenter' => $multisenter,
                'senter_utama' => $multisenter ? ($f['senter_utama'] ?: null) : null,
                'senter_satelit' => $multisenter ? ($f['senter_satelit'] ?: null) : null,
                'kerjasama' => $f['kerjasama'],
                'jumlah_negara' => $internasional ? ($f['jumlah_negara'] ?: null) : null,
                'peneliti_asing' => $f['peneliti_asing'] === '1',
                'pernah_diajukan' => $pernahDiajukan,
                'disetujui_komisi_lain' => $pernahDiajukan ? ($f['disetujui_komisi_lain'] === '1') : null,
                'sampel_ke_luar_negeri' => $keLuarNegeri,
                'negara_tujuan' => $keLuarNegeri ? ($f['negara_tujuan'] ?: null) : null,
                'registrasi_bpom' => $f['registrasi_bpom'] ?: null,
                'dikirim_pada' => now(),
            ],
        );

        $this->proposal->unsetRelation('formEtik');
    }

    protected function pemilik(): bool
    {
        return $this->proposal->user_id === auth()->id();
    }

    protected function pindah(ProposalStatus $ke, ?string $catatan = null): void
    {
        app(ProposalWorkflow::class)->transition($this->proposal, $ke, $catatan ?: null);
        $this->proposal->refresh();
        $this->reset('catatan', 'fileUpload', 'fileEtik', 'fileProposal', 'fileLaporan', 'fileRawData', 'fileBayarCru', 'fileBayarKepk');
        $this->success("Status: {$ke->value}");
    }

    protected function simpanFile(DocumentType $jenis, $file): ProposalDocument
    {
        return app(ProposalWorkflow::class)->simpanDokumen($this->proposal, $jenis, $file);
    }

    /**
     * Simpan lampiran surat tanggapan bila petugas melampirkannya.
     *
     * Satu kartu aksi punya SATU input berkas tapi beberapa tombol. Dulu hanya
     * sebagian tombol yang membaca `$fileUpload`, sehingga menekan tombol lain
     * membuang berkas yang sudah dilampirkan tanpa pesan apa pun — status tetap
     * berpindah dan dokumennya tidak pernah muncul. Dipanggil dari SEMUA aksi
     * yang kartunya memuat input berkas, supaya tidak ada lagi tombol yang
     * diam-diam membuang lampiran.
     */
    protected function simpanLampiranOpsional(): void
    {
        if (! $this->fileUpload) {
            return;
        }

        $this->validate(['fileUpload' => DocumentType::SuratTanggapan->aturanValidasi()]);
        $this->simpanFile(DocumentType::SuratTanggapan, $this->fileUpload);
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

    /**
     * Lengkapi berkas etik (T2) → diarahkan ke KEPK untuk penunjukan reviewer.
     *
     * Formulir Pengajuan Etik diisi di sini sebagai data (Poin B & C), bukan
     * diunggah sebagai PDF seperti sebelumnya — lihat App\Models\FormEtik.
     */
    public function kirimBerkasEtik()
    {
        abort_unless($this->pemilik(), 403);

        $rules = $this->aturanFormEtik();
        foreach (DocumentType::wajibTahap2() as $jenis) {
            $rules["fileEtik.{$jenis->value}"] = 'required|'.$jenis->aturanValidasi();
        }
        $this->validate($rules);

        foreach (DocumentType::wajibTahap2() as $jenis) {
            $this->simpanFile($jenis, $this->fileEtik[$jenis->value]);
        }

        $this->simpanFormEtik();

        // Berkas etik masuk → KEPK punya sesuatu untuk dikerjakan, jadi berkas
        // kerjanya dibuat di sini (bukan saat pengajuan, saat KEPK belum terlibat).
        $this->proposal->protokolEtik()->firstOrCreate([]);

        $this->pindah(ProposalStatus::MenungguPenunjukanReviewer, $this->catatan);
    }

    /**
     * Unggah ulang berkas etik yang diperbaiki — opsional per berkas.
     * Dipakai dua jalur perbaikan yang berbeda maksudnya (kelengkapan vs telaah),
     * jadi aturannya ditaruh di satu tempat supaya tidak melenceng sendiri-sendiri.
     *
     * Pesan error sengaja diserahkan ke pemanggil: syarat "minimal satu" berbeda
     * antar jalur — yang satu boleh dipenuhi oleh berkas proposal.
     *
     * @return bool ada berkas etik yang benar-benar diunggah
     */
    protected function unggahUlangBerkasEtik(): bool
    {
        $ada = false;

        foreach (DocumentType::wajibTahap2() as $jenis) {
            if (! empty($this->fileEtik[$jenis->value])) {
                $this->validate(["fileEtik.{$jenis->value}" => $jenis->aturanValidasi()]);
                $this->simpanFile($jenis, $this->fileEtik[$jenis->value]);
                $ada = true;
            }
        }

        return $ada;
    }

    /**
     * Perbaiki berkas yang dikembalikan KEPK — belum ada reviewer terlibat.
     *
     * Proposal ikut bisa diunggah ulang di sini: tim KEPK menelaah proposalnya juga,
     * bukan hanya keempat berkas etik, jadi koreksi mereka bisa menyangkut proposal.
     */
    public function kirimPerbaikanBerkasEtik()
    {
        abort_unless($this->pemilik(), 403);

        $adaFormEtik = $this->formEtikSudahDiisi();

        if ($adaFormEtik) {
            $this->validate($this->aturanFormEtik());
        }

        $adaProposal = false;

        if ($this->fileProposal) {
            $this->validate(['fileProposal' => DocumentType::Proposal->aturanValidasi()]);
            $this->simpanFile(DocumentType::Proposal, $this->fileProposal);
            $adaProposal = true;
        }

        $adaEtik = $this->unggahUlangBerkasEtik();

        if (! $adaProposal && ! $adaEtik) {
            $this->addError('fileEtik', 'Unggah minimal satu berkas — proposal atau berkas etik.');

            return;
        }

        if ($adaFormEtik) {
            $this->simpanFormEtik();
        }

        // Sengaja TIDAK memanggil resetPenugasanReviewer(): di titik ini KEPK belum
        // menunjuk siapa pun, jadi tidak ada penugasan yang perlu di-reset.
        $this->pindah(ProposalStatus::MenungguPenunjukanReviewer, $this->catatan);
    }

    /** Perbaiki berkas etik sesuai komentar reviewer (loop, opsional per berkas). */
    public function kirimRevisiEtik()
    {
        abort_unless($this->pemilik(), 403);

        $adaFormEtik = $this->formEtikSudahDiisi();

        if ($adaFormEtik) {
            $this->validate($this->aturanFormEtik());
        }

        if (! $this->unggahUlangBerkasEtik()) {
            $this->addError('fileEtik', 'Unggah minimal satu berkas revisi.');

            return;
        }

        if ($adaFormEtik) {
            $this->simpanFormEtik();
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

        $this->simpanLampiranOpsional();

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

        $this->simpanLampiranOpsional();

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
        $this->simpanLampiranOpsional();
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

    /**
     * CRU mengunggah Perjanjian Kerjasama, kapan saja dan tanpa mengubah status.
     *
     * PKS sengaja lepas dari alur: penerbitannya lama dan sering baru selesai
     * setelah penelitiannya rampung, jadi mengikatnya ke suatu tahap akan
     * membekukan proposal karena menunggu berkas yang belum tentu ada.
     */
    public function unggahPks()
    {
        abort_unless(auth()->user()->can('antrian-cru.update'), 403);
        $this->validate(['filePks' => 'required|'.DocumentType::Pks->aturanValidasi()]);

        $this->simpanFile(DocumentType::Pks, $this->filePks);

        $this->proposal->refresh();
        $this->reset('filePks');
        $this->success('PKS tersimpan.');
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
     * KEPK mengembalikan berkas etik yang kurang/salah SEBELUM reviewer dilibatkan.
     *
     * Beda maksud dari kepkTeruskanRevisi(): yang itu meneruskan masukan reviewer,
     * yang ini soal kelengkapan berkas — dan tanpa jalur ini, satu berkas salah
     * memaksa KEPK memilih antara meneruskan berkas cacat atau menolak permanen.
     */
    public function kepkMintaRevisiBerkas()
    {
        abort_unless(auth()->user()->can('kaji-etik.update'), 403);
        $this->validate(['catatan' => 'required|string'], [], ['catatan' => 'catatan perbaikan berkas']);

        $this->simpanLampiranOpsional();

        $this->pindah(ProposalStatus::PerluRevisiBerkasEtik, $this->catatan);
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
        $this->simpanLampiranOpsional();

        $this->pindah(ProposalStatus::PerluRevisiReviewer, $this->catatan);
    }

    /** KEPK loloskan ke pembayaran — hanya bila SEMUA reviewer ACC. */
    public function kepkLanjut()
    {
        abort_unless(auth()->user()->can('kaji-etik.update'), 403);
        abort_unless($this->proposal->semuaReviewerAcc(), 403, 'Belum semua reviewer memberikan ACC');

        $this->simpanLampiranOpsional();

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

        $this->simpanLampiranOpsional();

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
            'formulirEtik' => $this->proposal->formEtik,
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

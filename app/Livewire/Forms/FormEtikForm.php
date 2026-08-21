<?php

namespace App\Livewire\Forms;

use App\Enums\BentukKerjasama;
use App\Enums\KelengkapanDokumen;
use App\Livewire\Forms\Concerns\JawabanYaTidak;
use App\Models\FormEtik;
use App\Models\Proposal;
use App\Support\TandaTangan;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Formulir Pengajuan Etik (Poin B & C) — aturan, prefill, dan penyimpanannya.
 *
 * Dipisah dari Proposal\Show karena komponen itu memegang seluruh papan aksi
 * proposal (CRU, KEPK, reviewer, survey); menaruh dua formulir panjang di sana
 * membuat satu berkas yang tidak muat dibaca sekali duduk. Di sini isian etik
 * bisa dibaca, diubah, dan diuji tanpa menyentuh alur unit lain.
 */
class FormEtikForm extends Form
{
    use JawabanYaTidak;

    /**
     * Poin B: huruf butir => dicentang. Butir yang tidak dicentang boleh absen —
     * checkbox HTML memang tidak mengirim apa pun saat kosong, dan absennya sudah
     * berarti "tidak dilampirkan".
     */
    public array $kelengkapan = [];

    /**
     * Jawaban ya/tidak Poin C disimpan sebagai '1'/'0' — BUKAN bool — supaya
     * "belum dijawab" ('') bisa dibedakan dari "tidak" dan ditangkap `required`;
     * bool tidak punya nilai ketiga untuk itu.
     */
    public string $multisenter = '';

    public string $senter_utama = '';

    public string $senter_satelit = '';

    public string $kerjasama = '';

    public string $jumlah_negara = '';

    public string $peneliti_asing = '';

    public string $pernah_diajukan = '';

    public string $disetujui_komisi_lain = '';

    public string $sampel_ke_luar_negeri = '';

    public string $negara_tujuan = '';

    public string $registrasi_bpom = '';

    /** Data URL PNG dari kanvas tanda tangan. */
    public string $tanda_tangan = '';

    /**
     * Tidak ada butir Poin B yang wajib dicentang: sebagian butir memang hanya
     * berlaku pada penelitian tertentu, dan mewajibkannya hanya akan melatih
     * peneliti mencentang tanpa membaca. Yang ditegakkan adalah Poin C — di situ
     * setiap pertanyaan berlaku untuk semua penelitian.
     */
    public function rules(): array
    {
        return [
            'kelengkapan' => 'array',
            'kelengkapan.*' => 'boolean',

            'multisenter' => 'required|boolean',
            'senter_utama' => [Rule::requiredIf(fn () => $this->ya('multisenter')), 'nullable', 'string', 'max:255'],
            'senter_satelit' => 'nullable|string|max:255',

            'kerjasama' => ['required', Rule::enum(BentukKerjasama::class)],
            'jumlah_negara' => [
                Rule::requiredIf(fn () => $this->kerjasama === BentukKerjasama::Internasional->value),
                'nullable', 'string', 'max:255',
            ],
            'peneliti_asing' => 'required|boolean',

            'pernah_diajukan' => 'required|boolean',
            'disetujui_komisi_lain' => [Rule::requiredIf(fn () => $this->ya('pernah_diajukan')), 'nullable', 'boolean'],

            'sampel_ke_luar_negeri' => 'required|boolean',
            'negara_tujuan' => [Rule::requiredIf(fn () => $this->ya('sampel_ke_luar_negeri')), 'nullable', 'string', 'max:255'],

            'registrasi_bpom' => 'nullable|string',

            'tanda_tangan' => TandaTangan::ATURAN,
        ];
    }

    public function validationAttributes(): array
    {
        return ['tanda_tangan' => 'tanda tangan'];
    }

    /**
     * Aturan untuk simpan sementara: hanya bentuk yang diperiksa, bukan
     * kelengkapan. Draf memang setengah jadi — itu gunanya. Tanda tangan tetap
     * diperiksa bentuknya supaya sampah dari klien tidak menetap di kolom lalu
     * muncul sebagai gambar rusak saat dicetak.
     */
    public function aturanDraf(): array
    {
        return [
            'kelengkapan' => 'array',
            'kelengkapan.*' => 'boolean',
            'multisenter' => 'nullable|in:,0,1',
            'senter_utama' => 'nullable|string|max:255',
            'senter_satelit' => 'nullable|string|max:255',
            'kerjasama' => ['nullable', Rule::in(['', ...array_column(BentukKerjasama::cases(), 'value')])],
            'jumlah_negara' => 'nullable|string|max:255',
            'peneliti_asing' => 'nullable|in:,0,1',
            'pernah_diajukan' => 'nullable|in:,0,1',
            'disetujui_komisi_lain' => 'nullable|in:,0,1',
            'sampel_ke_luar_negeri' => 'nullable|in:,0,1',
            'negara_tujuan' => 'nullable|string|max:255',
            'registrasi_bpom' => 'nullable|string',
            'tanda_tangan' => $this->tanda_tangan === ''
                ? ['nullable', 'string']
                : TandaTangan::ATURAN,
        ];
    }

    /** Muat jawaban yang sudah tersimpan supaya form tidak tampil kosong lagi. */
    public function isiDari(FormEtik $form): void
    {
        $this->kelengkapan = $form->kelengkapan ?? [];

        $this->multisenter = self::keForm($form->multisenter);
        $this->senter_utama = $form->senter_utama ?? '';
        $this->senter_satelit = $form->senter_satelit ?? '';
        $this->kerjasama = $form->kerjasama?->value ?? '';
        $this->jumlah_negara = $form->jumlah_negara ?? '';
        $this->peneliti_asing = self::keForm($form->peneliti_asing);
        $this->pernah_diajukan = self::keForm($form->pernah_diajukan);
        $this->disetujui_komisi_lain = self::keForm($form->disetujui_komisi_lain);
        $this->sampel_ke_luar_negeri = self::keForm($form->sampel_ke_luar_negeri);
        $this->negara_tujuan = $form->negara_tujuan ?? '';
        $this->registrasi_bpom = $form->registrasi_bpom ?? '';
        $this->tanda_tangan = $form->tanda_tangan ?? '';
    }

    /**
     * Simpan Poin B & C.
     *
     * Jawaban lanjutan yang syaratnya tidak lagi berlaku dikosongkan, bukan
     * dibiarkan: menyimpan "senter utama" pada penelitian yang baru saja diubah
     * jadi bukan-multisenter membuat KEPK membaca dua jawaban yang bertentangan.
     *
     * @param  bool  $final  true saat dikirim ke KEPK; false saat disimpan sebagai draf
     */
    public function simpan(Proposal $proposal, bool $final = true): FormEtik
    {
        $multisenter = $this->ya('multisenter');
        $internasional = $this->kerjasama === BentukKerjasama::Internasional->value;
        $pernahDiajukan = $this->ya('pernah_diajukan');
        $keLuarNegeri = $this->ya('sampel_ke_luar_negeri');

        $kelengkapan = [];
        foreach (KelengkapanDokumen::cases() as $butir) {
            $kelengkapan[$butir->value] = (bool) ($this->kelengkapan[$butir->value] ?? false);
        }

        return $proposal->formEtik()->updateOrCreate(
            ['proposal_id' => $proposal->id],
            [
                'kelengkapan' => $kelengkapan,
                'multisenter' => $this->keDatabase('multisenter'),
                'senter_utama' => $multisenter ? ($this->senter_utama ?: null) : null,
                'senter_satelit' => $multisenter ? ($this->senter_satelit ?: null) : null,
                'kerjasama' => $this->kerjasama ?: null,
                'jumlah_negara' => $internasional ? ($this->jumlah_negara ?: null) : null,
                'peneliti_asing' => $this->keDatabase('peneliti_asing'),
                'pernah_diajukan' => $this->keDatabase('pernah_diajukan'),
                'disetujui_komisi_lain' => $pernahDiajukan ? $this->ya('disetujui_komisi_lain') : null,
                'sampel_ke_luar_negeri' => $this->keDatabase('sampel_ke_luar_negeri'),
                'negara_tujuan' => $keLuarNegeri ? ($this->negara_tujuan ?: null) : null,
                'registrasi_bpom' => $this->registrasi_bpom ?: null,
                'tanda_tangan' => $this->tanda_tangan ?: null,
                'dikirim_pada' => $final ? now() : null,
            ],
        );
    }
}

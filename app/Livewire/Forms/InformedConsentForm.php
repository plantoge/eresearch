<?php

namespace App\Livewire\Forms;

use App\Enums\BagianLembarInformasi;
use App\Livewire\Forms\Concerns\JawabanYaTidak;
use App\Models\InformedConsent;
use App\Models\Proposal;
use App\Support\TandaTangan;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Lembar Informasi Informed Consent — aturan, prefill, dan penyimpanannya.
 *
 * Seluruh isinya bersyarat pada satu pertanyaan: apakah penelitian ini merekrut
 * partisipan secara langsung. Penelitian yang hanya membaca rekam medis tidak
 * pernah menghubungi siapa pun, dan memaksanya menulis empat belas paragraf
 * penjelasan untuk subjek yang tidak akan pernah membacanya hanya menghasilkan
 * karangan — bukan perlindungan.
 */
class InformedConsentForm extends Form
{
    use JawabanYaTidak;

    /** '1'/'0'/'' — lihat JawabanYaTidak untuk alasan bentuk stringnya. */
    public string $merekrut_partisipan = '';

    public string $alasan_tanpa_consent = '';

    public string $peran_peneliti = '';

    public string $maksud_penelitian = '';

    /** Bagian naratif: nilai enum BagianLembarInformasi => teks. */
    public array $lembar = [];

    /** Data URL PNG dari kanvas tanda tangan. */
    public string $tanda_tangan = '';

    public function rules(): array
    {
        $merekrut = fn () => $this->ya('merekrut_partisipan');

        $aturan = [
            'merekrut_partisipan' => 'required|boolean',
            'alasan_tanpa_consent' => [
                Rule::requiredIf(fn () => $this->terjawab('merekrut_partisipan') && ! $this->ya('merekrut_partisipan')),
                'nullable', 'string',
            ],
            'peran_peneliti' => [Rule::requiredIf($merekrut), 'nullable', 'string', 'max:255'],
            'maksud_penelitian' => [Rule::requiredIf($merekrut), 'nullable', 'string'],
            'lembar' => 'array',
            'tanda_tangan' => $merekrut() ? TandaTangan::ATURAN : ['nullable', 'string'],
        ];

        foreach (BagianLembarInformasi::cases() as $bagian) {
            $aturan["lembar.{$bagian->value}"] = [Rule::requiredIf($merekrut), 'nullable', 'string'];
        }

        return $aturan;
    }

    public function validationAttributes(): array
    {
        $nama = ['tanda_tangan' => 'tanda tangan'];

        foreach (BagianLembarInformasi::cases() as $bagian) {
            $nama["lembar.{$bagian->value}"] = strtolower($bagian->label());
        }

        return $nama;
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
            'merekrut_partisipan' => 'nullable|in:,0,1',
            'alasan_tanpa_consent' => 'nullable|string',
            'peran_peneliti' => 'nullable|string|max:255',
            'maksud_penelitian' => 'nullable|string',
            'lembar' => 'array',
            'lembar.*' => 'nullable|string',
            'tanda_tangan' => $this->tanda_tangan === ''
                ? ['nullable', 'string']
                : TandaTangan::ATURAN,
        ];
    }

    public function isiDari(InformedConsent $consent): void
    {
        $this->merekrut_partisipan = self::keForm($consent->merekrut_partisipan);
        $this->alasan_tanpa_consent = $consent->alasan_tanpa_consent ?? '';
        $this->peran_peneliti = $consent->peran_peneliti ?? '';
        $this->maksud_penelitian = $consent->maksud_penelitian ?? '';
        $this->lembar = $consent->lembar_informasi ?? [];
        $this->tanda_tangan = $consent->tanda_tangan ?? '';
    }

    /**
     * @param  bool  $final  true saat dikirim ke KEPK; false saat disimpan sebagai draf
     */
    public function simpan(Proposal $proposal, bool $final = true): InformedConsent
    {
        $merekrut = $this->ya('merekrut_partisipan');

        // Jawaban yang syaratnya tidak lagi berlaku dikosongkan: Lembar Informasi
        // yang tertinggal pada penelitian yang baru saja dinyatakan tidak merekrut
        // partisipan akan ikut tercetak dan membantah jawabannya sendiri.
        $lembar = [];
        if ($merekrut) {
            foreach (BagianLembarInformasi::cases() as $bagian) {
                $lembar[$bagian->value] = trim((string) ($this->lembar[$bagian->value] ?? ''));
            }
        }

        return $proposal->informedConsent()->updateOrCreate(
            ['proposal_id' => $proposal->id],
            [
                'merekrut_partisipan' => $this->keDatabase('merekrut_partisipan'),
                'alasan_tanpa_consent' => $merekrut ? null : ($this->alasan_tanpa_consent ?: null),
                'peran_peneliti' => $merekrut ? ($this->peran_peneliti ?: null) : null,
                'maksud_penelitian' => $merekrut ? ($this->maksud_penelitian ?: null) : null,
                'lembar_informasi' => $lembar,
                'tanda_tangan' => $merekrut ? ($this->tanda_tangan ?: null) : null,
                'dikirim_pada' => $final ? now() : null,
            ],
        );
    }
}

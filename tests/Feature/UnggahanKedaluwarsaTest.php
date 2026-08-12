<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\DokumenTelaah;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

/**
 * Berkas sementara Livewire (`livewire-tmp/…`) bisa hilang sebelum form
 * disubmit: masa berlakunya habis (`livewire.temporary_file_upload.max_upload_time`,
 * 5 menit), unggahannya terputus, atau pembersih 24 jam menyapunya.
 *
 * Yang membuat ini berbahaya: `TemporaryUploadedFile::isValid()` di-hardcode
 * `true`, sehingga aturan `file` SELALU lolos. Aturan berikutnya (`mimes`, `max`)
 * lalu memanggil `getMimeType()`/`getSize()` yang membaca disk — dan Flysystem
 * MELEMPAR `UnableToRetrieveMetadata` alih-alih mengembalikan gagal. Akibatnya
 * pengguna dapat layar error 500, bukan pesan validasi.
 *
 * `bail` tidak menolong: ia hanya melewati aturan setelah ada KEGAGALAN,
 * sedangkan di sini aturannya melempar, bukan gagal.
 *
 * Terjadi di produksi 12 Agustus 2026 pada /proposal/baru.
 */
class UnggahanKedaluwarsaTest extends TestCase
{
    /** Berkas sementara yang catatannya ada tapi berkas fisiknya sudah hilang. */
    protected function berkasHilang(): TemporaryUploadedFile
    {
        $berkas = new TemporaryUploadedFile('livewire-tmp', 'local');
        $berkas->delete();

        return $berkas;
    }

    public function test_berkas_sementara_yang_hilang_benar_benar_tidak_ada(): void
    {
        // Menjaga premis tes lain di kelas ini: kalau suatu saat Livewire
        // mematerialisasi berkasnya sendiri, tes di bawah jadi tidak bermakna.
        $this->assertFalse($this->berkasHilang()->exists());
    }

    public function test_validasi_menolak_berkas_yang_hilang_tanpa_melempar(): void
    {
        $validator = Validator::make(
            ['berkas' => $this->berkasHilang()],
            ['berkas' => DocumentType::Proposal->aturanValidasi()],
        );

        // Yang diuji: GAGAL dengan rapi, bukan melempar UnableToRetrieveMetadata.
        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('unggah ulang', strtolower($validator->errors()->first('berkas')));
    }

    public function test_validasi_berkas_telaah_juga_menolak_berkas_yang_hilang(): void
    {
        $validator = Validator::make(
            ['berkas' => $this->berkasHilang()],
            ['berkas' => DokumenTelaah::ATURAN_VALIDASI],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_berkas_normal_tetap_lolos(): void
    {
        Storage::disk('local')->put('livewire-tmp/berkas-uji.pdf', '%PDF-1.4 isi uji');
        $berkas = new TemporaryUploadedFile('berkas-uji.pdf', 'local');

        $this->assertTrue($berkas->exists(), 'prasyarat: berkasnya memang ada');

        $validator = Validator::make(['berkas' => $berkas], ['berkas' => 'berkas_ada']);

        $this->assertFalse($validator->fails(), 'Berkas yang benar-benar ada tidak boleh ditolak');

        Storage::disk('local')->delete('livewire-tmp/berkas-uji.pdf');
    }
}

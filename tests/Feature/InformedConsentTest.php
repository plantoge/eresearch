<?php

namespace Tests\Feature;

use App\Enums\BagianLembarInformasi;
use App\Enums\BentukKerjasama;
use App\Enums\DocumentType;
use App\Enums\JenisPenelitian;
use App\Enums\ProposalStatus as S;
use App\Enums\TipeProposal;
use App\Livewire\Proposal\Show;
use App\Models\InformedConsent;
use App\Models\Menu;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Formulir Informed Consent sebagai isian terstruktur, menggantikan unggahan
 * PDF `informed_consent`.
 *
 * Lembar Informasi diisi peneliti; Lembar Persetujuan TIDAK — ia ditandatangani
 * subjek penelitian di lapangan, jadi aplikasi hanya mencetaknya kosong sebagai
 * templat. Yang ditelaah KEPK memang templatnya, bukan persetujuan yang sudah
 * terkumpul.
 */
class InformedConsentTest extends TestCase
{
    use RefreshDatabase;

    protected User $peneliti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('dokumen');

        $this->peneliti = User::factory()->create();
        $this->peneliti->assignRole('peneliti');
        $this->actingAs($this->peneliti);
    }

    /**
     * Data URL PNG seperti yang dikirim kanvas x-mary-signature.
     *
     * PNG sungguhan, bukan string ber-awalan benar — lihat alasannya di
     * FormEtikTest::tandaTangan().
     */
    protected function tandaTangan(): string
    {
        return 'data:image/png;base64,'.FormEtikTest::PNG_1X1;
    }

    protected function proposalTahapEtik(): Proposal
    {
        $wf = app(ProposalWorkflow::class);

        $p = $wf->ajukan([
            'peneliti_utama' => 'Dr. Uji',
            'judul_penelitian' => 'Penelitian Uji',
            'lokasi_penelitian' => 'RSPI Prof. Dr. Sulianti Saroso',
            'jenis_penelitian' => JenisPenelitian::NonExperimental->value,
            'user_id' => $this->peneliti->id,
        ], TipeProposal::Internal);

        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik] as $ke) {
            $wf->transition($p, $ke);
        }

        return $p->fresh();
    }

    /**
     * Kartu tahap 2 terisi lengkap dan sah — formulir etik, informed consent,
     * dan berkas wajib. Penelitian yang merekrut partisipan secara langsung.
     */
    protected function kartuLengkap(?Proposal $p = null): Testable
    {
        $c = Livewire::test(Show::class, ['proposal' => $p ?? $this->proposalTahapEtik()])
            ->set('formEtik.multisenter', '0')
            ->set('formEtik.kerjasama', BentukKerjasama::Bukan->value)
            ->set('formEtik.peneliti_asing', '0')
            ->set('formEtik.pernah_diajukan', '0')
            ->set('formEtik.sampel_ke_luar_negeri', '0')
            ->set('formEtik.tanda_tangan', $this->tandaTangan())
            ->set('informedConsent.merekrut_partisipan', '1')
            ->set('informedConsent.peran_peneliti', 'dokter spesialis')
            ->set('informedConsent.maksud_penelitian', 'mengetahui pola resistensi antibiotik')
            ->set('informedConsent.tanda_tangan', $this->tandaTangan());

        foreach (BagianLembarInformasi::cases() as $bagian) {
            $c->set("informedConsent.lembar.{$bagian->value}", 'Penjelasan '.$bagian->label());
        }

        foreach (DocumentType::wajibTahap2() as $jenis) {
            $c->set("fileEtik.{$jenis->value}", UploadedFile::fake()->create("{$jenis->value}.pdf", 50, 'application/pdf'));
        }

        return $c;
    }

    /**
     * Informed consent kini isian, bukan unggahan — kalau ia tetap dituntut
     * sebagai berkas, peneliti diminta mengunggah hal yang baru saja ia isi.
     */
    public function test_informed_consent_bukan_lagi_berkas_wajib(): void
    {
        $this->assertSame([DocumentType::KerahasiaanData], DocumentType::wajibTahap2());
    }

    public function test_kirim_tanpa_menjawab_rekrutmen_partisipan_ditolak(): void
    {
        $p = $this->proposalTahapEtik();

        $this->kartuLengkap($p)
            ->set('informedConsent.merekrut_partisipan', '')
            ->call('kirimBerkasEtik')
            ->assertHasErrors(['informedConsent.merekrut_partisipan' => 'required']);

        $this->assertSame(S::MenungguKelengkapanBerkasEtik, $p->fresh()->status);
        $this->assertDatabaseCount('rspi.kepk_informed_consent', 0);
    }

    public function test_lembar_informasi_wajib_bila_merekrut_partisipan(): void
    {
        $c = $this->kartuLengkap();

        foreach (BagianLembarInformasi::cases() as $bagian) {
            $c->set("informedConsent.lembar.{$bagian->value}", '');
        }

        $c->call('kirimBerkasEtik')->assertHasErrors([
            'informedConsent.lembar.'.BagianLembarInformasi::Tujuan->value => 'required',
            'informedConsent.lembar.'.BagianLembarInformasi::Kontak->value => 'required',
        ]);
    }

    public function test_tanda_tangan_wajib_bila_merekrut_partisipan(): void
    {
        $this->kartuLengkap()
            ->set('informedConsent.tanda_tangan', '')
            ->call('kirimBerkasEtik')
            ->assertHasErrors(['informedConsent.tanda_tangan' => 'required']);
    }

    public function test_alasan_wajib_bila_tidak_merekrut_partisipan(): void
    {
        $this->kartuLengkap()
            ->set('informedConsent.merekrut_partisipan', '0')
            ->call('kirimBerkasEtik')
            ->assertHasErrors(['informedConsent.alasan_tanpa_consent' => 'required']);
    }

    /**
     * Inti dari sifat bersyaratnya: penelitian rekam medis retrospektif tidak
     * pernah menghubungi subjek, jadi memaksanya menulis Lembar Informasi hanya
     * menghasilkan karangan yang tidak berguna bagi siapa pun.
     */
    public function test_penelitian_tanpa_rekrutmen_lolos_tanpa_lembar_informasi(): void
    {
        $p = $this->proposalTahapEtik();

        $c = $this->kartuLengkap($p)
            ->set('informedConsent.merekrut_partisipan', '0')
            ->set('informedConsent.alasan_tanpa_consent', 'Data rekam medis retrospektif, tanpa kontak subjek')
            ->set('informedConsent.tanda_tangan', '');

        foreach (BagianLembarInformasi::cases() as $bagian) {
            $c->set("informedConsent.lembar.{$bagian->value}", '');
        }

        $c->call('kirimBerkasEtik')->assertHasNoErrors();

        $ic = InformedConsent::sole();

        $this->assertFalse($ic->merekrut_partisipan);
        $this->assertSame('Data rekam medis retrospektif, tanpa kontak subjek', $ic->alasan_tanpa_consent);
        $this->assertSame(S::MenungguPenunjukanReviewer, $p->fresh()->status);
    }

    public function test_lembar_informasi_tersimpan_saat_dikirim(): void
    {
        $p = $this->proposalTahapEtik();

        $this->kartuLengkap($p)->call('kirimBerkasEtik')->assertHasNoErrors();

        $ic = InformedConsent::sole();

        $this->assertTrue($ic->merekrut_partisipan);
        $this->assertSame('dokter spesialis', $ic->peran_peneliti);
        $this->assertSame(
            'Penjelasan '.BagianLembarInformasi::Prosedur->label(),
            $ic->bagian(BagianLembarInformasi::Prosedur),
        );
        $this->assertSame($this->tandaTangan(), $ic->tanda_tangan);
        $this->assertNotNull($ic->dikirim_pada);
    }

    /**
     * Lembar Informasi 14 paragraf; tanpa simpan sementara, satu sesi yang putus
     * membuang seluruh pekerjaan mengetiknya.
     */
    public function test_draf_tersimpan_tanpa_memindahkan_status(): void
    {
        $p = $this->proposalTahapEtik();

        Livewire::test(Show::class, ['proposal' => $p])
            ->set('informedConsent.merekrut_partisipan', '1')
            ->set('informedConsent.lembar.'.BagianLembarInformasi::Tujuan->value, 'Baru separuh diketik')
            ->call('simpanDrafInformedConsent')
            ->assertHasNoErrors();

        $ic = InformedConsent::sole();

        $this->assertSame('Baru separuh diketik', $ic->bagian(BagianLembarInformasi::Tujuan));
        $this->assertNull($ic->dikirim_pada, 'draf belum dikirim');
        $this->assertSame(S::MenungguKelengkapanBerkasEtik, $p->fresh()->status);
    }

    public function test_draf_terisi_ulang_saat_halaman_dibuka(): void
    {
        $p = $this->proposalTahapEtik();

        Livewire::test(Show::class, ['proposal' => $p])
            ->set('informedConsent.merekrut_partisipan', '1')
            ->set('informedConsent.peran_peneliti', 'perawat')
            ->call('simpanDrafInformedConsent');

        Livewire::test(Show::class, ['proposal' => $p->fresh()])
            ->assertSet('informedConsent.merekrut_partisipan', '1')
            ->assertSet('informedConsent.peran_peneliti', 'perawat');
    }

    /**
     * Dua kanvas tanda tangan di satu halaman harus punya id berbeda.
     *
     * x-mary-signature menurunkan id kanvasnya dari `md5(serialize($this))` atas
     * argumen KONSTRUKTOR saja — `wire:model` tidak ikut, karena bag atribut baru
     * dipasang Blade setelah konstruksi. Dua pemanggilan dengan height/hint yang
     * sama karena itu menghasilkan id yang sama persis, dan
     * `document.getElementById()` pada komponen kedua menemukan kanvas PERTAMA:
     * kanvas kedua tidak pernah dipasangi SignaturePad dan diam saat digambar.
     */
    public function test_kanvas_tanda_tangan_tidak_berbagi_id(): void
    {
        $html = Livewire::test(Show::class, ['proposal' => $this->proposalTahapEtik()])
            ->set('informedConsent.merekrut_partisipan', '1')
            ->html();

        preg_match_all('/<canvas id="([^"]+)"/', $html, $cocok);

        $this->assertCount(2, $cocok[1], 'harus ada dua kanvas tanda tangan di tahap 2');
        $this->assertSame(
            array_values(array_unique($cocok[1])),
            $cocok[1],
            'id kanvas bertabrakan: '.implode(' | ', $cocok[1]),
        );
    }

    // ================= Dibaca & dicetak =================

    protected function petugasKepk(): User
    {
        Menu::firstOrCreate(['slug' => 'kaji-etik'], ['nama' => 'Kaji Etik', 'route' => 'antrian.kepk']);
        Role::findByName('kepk')->givePermissionTo(['kaji-etik.read', 'kaji-etik.update']);

        $kepk = User::factory()->create();
        $kepk->assignRole('kepk');

        return $kepk;
    }

    protected function proposalBerinformedConsent(): Proposal
    {
        $p = $this->proposalTahapEtik();
        $this->kartuLengkap($p)->call('kirimBerkasEtik')->assertHasNoErrors();

        return $p->fresh();
    }

    public function test_informed_consent_terbaca_di_kartu_dokumen(): void
    {
        Livewire::test(Show::class, ['proposal' => $this->proposalBerinformedConsent()])
            ->assertSee('Formulir Informed Consent')
            ->assertSee(BagianLembarInformasi::Tujuan->label());
    }

    public function test_pemilik_bisa_mengunduh_pdf_informed_consent(): void
    {
        $respons = $this->get(route('informed-consent.pdf', $this->proposalBerinformedConsent()));

        $respons->assertOk();
        $this->assertSame('application/pdf', $respons->headers->get('content-type'));
    }

    public function test_kepk_bisa_mengunduh_pdf_informed_consent(): void
    {
        $p = $this->proposalBerinformedConsent();

        $this->actingAs($this->petugasKepk())
            ->get(route('informed-consent.pdf', $p))
            ->assertOk();
    }

    public function test_orang_lain_tidak_bisa_mengunduh_pdf_informed_consent(): void
    {
        $p = $this->proposalBerinformedConsent();

        $lain = User::factory()->create();
        $lain->assignRole('peneliti');

        $this->actingAs($lain)
            ->get(route('informed-consent.pdf', $p))
            ->assertForbidden();
    }

    public function test_pdf_informed_consent_tidak_ada_bila_belum_diisi(): void
    {
        $this->get(route('informed-consent.pdf', $this->proposalTahapEtik()))
            ->assertNotFound();
    }

    /**
     * Lembar Persetujuan ikut tercetak dengan judul terisi tapi kolom subjek
     * kosong — itulah yang dibawa peneliti ke lapangan untuk ditandatangani.
     */
    public function test_lembar_persetujuan_tercetak_kosong_dengan_judul_terisi(): void
    {
        $p = $this->proposalBerinformedConsent();

        $this->view('pdf.informed-consent', [
            'proposal' => $p,
            'consent' => $p->informedConsent,
            'bagian' => BagianLembarInformasi::cases(),
        ])
            // Huruf kapitalnya datang dari CSS `text-transform`, jadi yang ada di
            // HTML tetap bentuk judul biasa.
            ->assertSee('Lembar Persetujuan Untuk Ikut Serta Dalam Penelitian')
            ->assertSee($p->judul_penelitian)
            ->assertSee('Nama Peserta')
            ->assertSee('Nama Saksi');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BentukKerjasama;
use App\Enums\DocumentType;
use App\Enums\JenisPenelitian;
use App\Enums\KelengkapanDokumen;
use App\Enums\ProposalStatus as S;
use App\Enums\TipeProposal;
use App\Livewire\Proposal\Create;
use App\Livewire\Proposal\Show;
use App\Models\FormEtik;
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
 * Formulir Pengajuan Etik KEPK (Poin A/B/C) sebagai isian terstruktur, bukan
 * unggahan PDF.
 *
 * Poin A menumpang data pengajuan yang sudah ada; tiga keterangan yang belum
 * pernah ditanyakan (sponsor, jenis penelitian, lokasi) ditanyakan sejak
 * pengajuan awal supaya formulir etiknya tidak menanyakan ulang identitas.
 */
class FormEtikTest extends TestCase
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

    // ================= Poin A: informasi umum di pengajuan awal =================

    /** Form pengajuan tahap 1 yang sudah terisi lengkap dan sah. */
    protected function formPengajuan(): Testable
    {
        return Livewire::test(Create::class)
            ->set('tipe_proposal', TipeProposal::Internal->value)
            ->set('peneliti_utama', 'Dr. Uji')
            ->set('judul_penelitian', 'Penelitian Uji')
            ->set('jenis_penelitian', JenisPenelitian::NonExperimental->value)
            ->set('lokasi_penelitian', 'RSPI Prof. Dr. Sulianti Saroso')
            ->set('surat_pengantar', UploadedFile::fake()->create('pengantar.pdf', 50, 'application/pdf'))
            ->set('proposal_penelitian', UploadedFile::fake()->create('proposal.pdf', 50, 'application/pdf'));
    }

    public function test_pengajuan_tanpa_jenis_penelitian_ditolak(): void
    {
        $this->formPengajuan()
            ->set('jenis_penelitian', '')
            ->call('simpan')
            ->assertHasErrors(['jenis_penelitian' => 'required']);

        $this->assertDatabaseCount('rspi.proposal', 0);
    }

    public function test_pengajuan_tanpa_lokasi_penelitian_ditolak(): void
    {
        $this->formPengajuan()
            ->set('lokasi_penelitian', '')
            ->call('simpan')
            ->assertHasErrors(['lokasi_penelitian' => 'required']);

        $this->assertDatabaseCount('rspi.proposal', 0);
    }

    public function test_informasi_umum_tambahan_tersimpan_di_proposal(): void
    {
        $this->formPengajuan()
            ->set('sponsor', 'Hibah Kemenkes 2026')
            ->call('simpan')
            ->assertHasNoErrors();

        $p = Proposal::sole();

        $this->assertSame('Hibah Kemenkes 2026', $p->sponsor);
        $this->assertSame(JenisPenelitian::NonExperimental, $p->jenis_penelitian);
        $this->assertSame('RSPI Prof. Dr. Sulianti Saroso', $p->lokasi_penelitian);
    }

    /** Sponsor sering memang tidak ada — pengajuan tanpa sponsor harus tetap lolos. */
    public function test_sponsor_boleh_kosong(): void
    {
        $this->formPengajuan()
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertNull(Proposal::sole()->sponsor);
    }

    // ================= Poin B & C: formulir etik tahap 2 =================

    /** Proposal milik peneliti yang berhenti tepat di tahap melengkapi berkas etik. */
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

    /** Kartu tahap 2 terisi lengkap dan sah: checklist B, jawaban C, dan berkas wajib. */
    protected function kartuEtik(?Proposal $p = null): Testable
    {
        $c = Livewire::test(Show::class, ['proposal' => $p ?? $this->proposalTahapEtik()])
            ->set('formEtik.kelengkapan', ['a' => true, 'b' => true, 'c' => true, 'f' => true])
            ->set('formEtik.multisenter', '0')
            ->set('formEtik.kerjasama', BentukKerjasama::Bukan->value)
            ->set('formEtik.peneliti_asing', '0')
            ->set('formEtik.pernah_diajukan', '0')
            ->set('formEtik.sampel_ke_luar_negeri', '0')
            ->set('formEtik.registrasi_bpom', 'Tidak, bukan produk yang diregistrasi')
            ->set('formEtik.tanda_tangan', $this->tandaTangan())
            // Informed consent ikut divalidasi saat kirim, tapi bukan yang diuji
            // di berkas ini — jawaban tersingkat yang sah sudah cukup.
            // Lihat InformedConsentTest untuk pengujiannya sendiri.
            ->set('informedConsent.merekrut_partisipan', '0')
            ->set('informedConsent.alasan_tanpa_consent', 'Data sekunder, tanpa kontak subjek');

        foreach (DocumentType::wajibTahap2() as $jenis) {
            $c->set("fileEtik.{$jenis->value}", UploadedFile::fake()->create("{$jenis->value}.pdf", 50, 'application/pdf'));
        }

        return $c;
    }

    /** Data URL PNG seperti yang dikirim kanvas x-mary-signature. */
    protected function tandaTangan(): string
    {
        return 'data:image/png;base64,'.base64_encode('goresan-uji');
    }

    public function test_kirim_berkas_etik_tanpa_tanda_tangan_ditolak(): void
    {
        $this->kartuEtik()
            ->set('formEtik.tanda_tangan', '')
            ->call('kirimBerkasEtik')
            ->assertHasErrors(['formEtik.tanda_tangan' => 'required']);

        $this->assertDatabaseCount('rspi.kepk_form_etik', 0);
    }

    /**
     * Kanvas tanda tangan mengirim string dari klien, jadi `required` saja tidak
     * cukup: tanpa pemeriksaan bentuk, sampah tersimpan lalu muncul sebagai
     * gambar rusak di PDF resmi.
     */
    public function test_tanda_tangan_yang_bukan_gambar_png_ditolak(): void
    {
        $this->kartuEtik()
            ->set('formEtik.tanda_tangan', 'bukan-gambar')
            ->call('kirimBerkasEtik')
            ->assertHasErrors(['formEtik.tanda_tangan' => 'regex']);

        $this->assertDatabaseCount('rspi.kepk_form_etik', 0);
    }

    public function test_tanda_tangan_tersimpan_dan_terisi_ulang(): void
    {
        $p = $this->proposalTahapEtik();

        $this->kartuEtik($p)->call('kirimBerkasEtik')->assertHasNoErrors();

        $this->assertSame($this->tandaTangan(), FormEtik::sole()->tanda_tangan);

        Livewire::test(Show::class, ['proposal' => $p->fresh()])
            ->assertSet('formEtik.tanda_tangan', $this->tandaTangan());
    }

    /**
     * Formulir etik menggantikan unggahan PDF-nya, jadi form_kaji_etik tidak
     * boleh lagi ikut dituntut sebagai berkas wajib tahap 2 — kalau ikut,
     * peneliti diminta mengunggah hal yang baru saja ia isi.
     */
    public function test_form_kaji_etik_bukan_lagi_berkas_wajib(): void
    {
        $this->assertNotContains(DocumentType::FormKajiEtik, DocumentType::wajibTahap2());
    }

    public function test_kirim_berkas_etik_tanpa_menjawab_informasi_lain_ditolak(): void
    {
        $p = $this->proposalTahapEtik();

        $c = Livewire::test(Show::class, ['proposal' => $p]);

        foreach (DocumentType::wajibTahap2() as $jenis) {
            $c->set("fileEtik.{$jenis->value}", UploadedFile::fake()->create('x.pdf', 50, 'application/pdf'));
        }

        $c->call('kirimBerkasEtik')->assertHasErrors([
            'formEtik.multisenter' => 'required',
            'formEtik.kerjasama' => 'required',
            'formEtik.pernah_diajukan' => 'required',
            'formEtik.sampel_ke_luar_negeri' => 'required',
        ]);

        $this->assertSame(S::MenungguKelengkapanBerkasEtik, $p->fresh()->status);
        $this->assertDatabaseCount('rspi.kepk_form_etik', 0);
    }

    public function test_formulir_etik_tersimpan_saat_dikirim_ke_kepk(): void
    {
        $p = $this->proposalTahapEtik();

        $this->kartuEtik($p)->call('kirimBerkasEtik')->assertHasNoErrors();

        $form = FormEtik::sole();

        $this->assertSame($p->id, $form->proposal_id);
        $this->assertFalse($form->multisenter);
        $this->assertSame(BentukKerjasama::Bukan, $form->kerjasama);
        $this->assertSame('Tidak, bukan produk yang diregistrasi', $form->registrasi_bpom);
        $this->assertNotNull($form->dikirim_pada);
        $this->assertSame(S::MenungguPenunjukanReviewer, $p->fresh()->status);
    }

    public function test_checklist_kelengkapan_tersimpan_apa_adanya(): void
    {
        $this->kartuEtik()->call('kirimBerkasEtik')->assertHasNoErrors();

        $form = FormEtik::sole();

        $this->assertTrue($form->dicentang(KelengkapanDokumen::A));
        $this->assertTrue($form->dicentang(KelengkapanDokumen::F));
        // Butir yang tidak dicentang berarti "tidak dilampirkan", bukan belum dijawab.
        $this->assertFalse($form->dicentang(KelengkapanDokumen::E));
        $this->assertFalse($form->dicentang(KelengkapanDokumen::J));
    }

    public function test_senter_utama_wajib_bila_penelitian_multisenter(): void
    {
        $this->kartuEtik()
            ->set('formEtik.multisenter', '1')
            ->call('kirimBerkasEtik')
            ->assertHasErrors(['formEtik.senter_utama' => 'required']);

        $this->assertDatabaseCount('rspi.kepk_form_etik', 0);
    }

    public function test_jumlah_negara_wajib_bila_kerjasama_internasional(): void
    {
        $this->kartuEtik()
            ->set('formEtik.kerjasama', BentukKerjasama::Internasional->value)
            ->call('kirimBerkasEtik')
            ->assertHasErrors(['formEtik.jumlah_negara' => 'required']);
    }

    public function test_hasil_kaji_komisi_lain_wajib_bila_pernah_diajukan(): void
    {
        $this->kartuEtik()
            ->set('formEtik.pernah_diajukan', '1')
            ->call('kirimBerkasEtik')
            ->assertHasErrors(['formEtik.disetujui_komisi_lain' => 'required']);
    }

    public function test_negara_tujuan_wajib_bila_sampel_dikirim_ke_luar_negeri(): void
    {
        $this->kartuEtik()
            ->set('formEtik.sampel_ke_luar_negeri', '1')
            ->call('kirimBerkasEtik')
            ->assertHasErrors(['formEtik.negara_tujuan' => 'required']);
    }

    // ================= Menyunting ulang formulir di jalur perbaikan =================

    /** Proposal yang formulir etiknya sudah masuk, lalu dikembalikan KEPK. */
    protected function proposalDikembalikanKepk(): Proposal
    {
        $p = $this->proposalTahapEtik();
        $this->kartuEtik($p)->call('kirimBerkasEtik')->assertHasNoErrors();

        app(ProposalWorkflow::class)->transition($p->fresh(), S::PerluRevisiBerkasEtik, 'Jawaban Poin C keliru');

        return $p->fresh();
    }

    /**
     * Formulir etik ikut bisa dikoreksi saat KEPK mengembalikan berkas — kalau
     * tidak, satu jawaban Poin C yang keliru hanya bisa diperbaiki dengan
     * mengulang seluruh pengajuan.
     */
    public function test_formulir_etik_bisa_diperbaiki_saat_dikembalikan_kepk(): void
    {
        $p = $this->proposalDikembalikanKepk();

        Livewire::test(Show::class, ['proposal' => $p])
            ->set('formEtik.multisenter', '1')
            ->set('formEtik.senter_utama', 'RSPI Prof. Dr. Sulianti Saroso')
            ->set('fileEtik.'.DocumentType::KerahasiaanData->value,
                UploadedFile::fake()->create('ic-revisi.pdf', 50, 'application/pdf'))
            ->call('kirimPerbaikanBerkasEtik')
            ->assertHasNoErrors();

        $form = FormEtik::sole();

        $this->assertTrue($form->multisenter);
        $this->assertSame('RSPI Prof. Dr. Sulianti Saroso', $form->senter_utama);
        $this->assertSame(S::MenungguPenunjukanReviewer, $p->fresh()->status);
    }

    /** Jawaban yang sudah tersimpan muncul kembali di form, bukan kosong lagi. */
    public function test_jawaban_tersimpan_terisi_ulang_saat_halaman_dibuka(): void
    {
        $p = $this->proposalDikembalikanKepk();

        Livewire::test(Show::class, ['proposal' => $p])
            ->assertSet('formEtik.kerjasama', BentukKerjasama::Bukan->value)
            ->assertSet('formEtik.sampel_ke_luar_negeri', '0')
            ->assertSet('formEtik.kelengkapan.a', true)
            ->assertSet('formEtik.kelengkapan.e', false);
    }

    /**
     * Jawaban lanjutan yang syaratnya tidak lagi berlaku ikut dikosongkan —
     * tanpa ini KEPK membaca "bukan multisenter" bersama nama senter utama.
     */
    public function test_jawaban_lanjutan_dikosongkan_saat_syaratnya_gugur(): void
    {
        $p = $this->proposalTahapEtik();

        $this->kartuEtik($p)
            ->set('formEtik.multisenter', '1')
            ->set('formEtik.senter_utama', 'RSPI Sulianti Saroso')
            ->call('kirimBerkasEtik')
            ->assertHasNoErrors();

        app(ProposalWorkflow::class)->transition($p->fresh(), S::PerluRevisiBerkasEtik, 'Perbaiki');

        Livewire::test(Show::class, ['proposal' => $p->fresh()])
            ->set('formEtik.multisenter', '0')
            ->set('fileEtik.'.DocumentType::KerahasiaanData->value,
                UploadedFile::fake()->create('ic-revisi.pdf', 50, 'application/pdf'))
            ->call('kirimPerbaikanBerkasEtik')
            ->assertHasNoErrors();

        $this->assertNull(FormEtik::sole()->senter_utama);
    }

    /**
     * Jalur revisi dari reviewer juga membawa formulirnya.
     *
     * Komentar reviewer bisa menyangkut jawaban Poin C sama seperti berkasnya,
     * jadi kartu revisi harus bisa mengoreksi keduanya dalam satu kali kirim.
     */
    public function test_revisi_dari_reviewer_ikut_menyimpan_perubahan_formulir(): void
    {
        $p = $this->proposalTahapEtik();
        $this->kartuEtik($p)->call('kirimBerkasEtik')->assertHasNoErrors();

        $wf = app(ProposalWorkflow::class);
        foreach ([S::MenungguReviewReviewer, S::PerluRevisiReviewer] as $ke) {
            $wf->transition($p->fresh(), $ke);
        }

        Livewire::test(Show::class, ['proposal' => $p->fresh()])
            ->set('formEtik.sampel_ke_luar_negeri', '1')
            ->set('formEtik.negara_tujuan', 'Singapura')
            ->set('fileEtik.'.DocumentType::KerahasiaanData->value,
                UploadedFile::fake()->create('kerahasiaan-revisi.pdf', 50, 'application/pdf'))
            ->call('kirimRevisiEtik')
            ->assertHasNoErrors();

        $form = FormEtik::sole();

        $this->assertTrue($form->sampel_ke_luar_negeri);
        $this->assertSame('Singapura', $form->negara_tujuan);
        $this->assertSame(S::MenungguReviewReviewer, $p->fresh()->status);
    }

    // ================= Dibaca di kartu Dokumen =================

    protected function petugasKepk(): User
    {
        Menu::firstOrCreate(['slug' => 'kaji-etik'], ['nama' => 'Kaji Etik', 'route' => 'antrian.kepk']);
        Role::findByName('kepk')->givePermissionTo(['kaji-etik.read', 'kaji-etik.update']);

        $kepk = User::factory()->create();
        $kepk->assignRole('kepk');

        return $kepk;
    }

    /** Proposal yang formulir etiknya sudah terisi dan terkirim. */
    protected function proposalBerformulir(): Proposal
    {
        $p = $this->proposalTahapEtik();
        $this->kartuEtik($p)->call('kirimBerkasEtik')->assertHasNoErrors();

        return $p->fresh();
    }

    public function test_formulir_etik_terbaca_di_kartu_dokumen(): void
    {
        $p = $this->proposalBerformulir();

        Livewire::test(Show::class, ['proposal' => $p])
            ->assertSee('Formulir Pengajuan Etik')
            ->assertSee(KelengkapanDokumen::A->label())      // isi Poin B
            ->assertSee('Bukan kerja sama');                 // isi Poin C
    }

    public function test_tanda_tangan_ikut_terbaca_di_kartu_dokumen(): void
    {
        Livewire::test(Show::class, ['proposal' => $this->proposalBerformulir()])
            ->assertSee($this->tandaTangan());
    }

    /** Proposal yang formulirnya belum diisi tidak menampilkan baris kosong. */
    public function test_kartu_dokumen_tidak_menyebut_formulir_yang_belum_diisi(): void
    {
        Livewire::test(Show::class, ['proposal' => $this->proposalTahapEtik()])
            ->assertDontSee('Formulir Pengajuan Etik terisi');
    }

    public function test_pemilik_bisa_mengunduh_pdf_formulir_etik(): void
    {
        $p = $this->proposalBerformulir();

        $respons = $this->get(route('formulir-etik.pdf', $p));

        $respons->assertOk();
        $this->assertSame('application/pdf', $respons->headers->get('content-type'));
    }

    public function test_kepk_bisa_mengunduh_pdf_formulir_etik(): void
    {
        $p = $this->proposalBerformulir();

        $this->actingAs($this->petugasKepk())
            ->get(route('formulir-etik.pdf', $p))
            ->assertOk();
    }

    public function test_orang_lain_tidak_bisa_mengunduh_pdf_formulir_etik(): void
    {
        $p = $this->proposalBerformulir();

        $lain = User::factory()->create();
        $lain->assignRole('peneliti');

        $this->actingAs($lain)
            ->get(route('formulir-etik.pdf', $p))
            ->assertForbidden();
    }

    /** Tidak ada PDF untuk formulir yang belum pernah diisi. */
    public function test_pdf_formulir_etik_tidak_ada_bila_belum_diisi(): void
    {
        $this->get(route('formulir-etik.pdf', $this->proposalTahapEtik()))
            ->assertNotFound();
    }

    /** Peneliti lain tidak boleh membuka — apalagi mengisi — proposal orang. */
    public function test_peneliti_lain_tidak_bisa_membuka_formulir_etik(): void
    {
        $p = $this->proposalTahapEtik();

        $lain = User::factory()->create();
        $lain->assignRole('peneliti');

        $this->actingAs($lain)
            ->get(route('proposal.show', $p))
            ->assertForbidden();
    }
}

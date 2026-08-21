<?php

namespace Tests\Feature;

use App\Enums\BentukKerjasama;
use App\Enums\DocumentType;
use App\Enums\ProposalStatus as S;
use App\Enums\TipeProposal;
use App\Livewire\Proposal\Show;
use App\Models\Menu;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * PKS diunggah CRU, lepas dari alur.
 *
 * Penerbitan PKS lama dan sering baru selesai setelah penelitiannya rampung.
 * Sebagai syarat Tahap 2 (kondisi sebelumnya) ia membekukan proposal karena
 * menunggu berkas yang belum tentu ada — jadi sekarang tidak menahan apa pun.
 */
class PksCruTest extends TestCase
{
    use RefreshDatabase;

    protected ProposalWorkflow $wf;

    protected User $peneliti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('dokumen');

        $this->wf = app(ProposalWorkflow::class);
        $this->peneliti = User::factory()->create();
        $this->peneliti->assignRole('peneliti');
    }

    protected function buatProposal(): Proposal
    {
        $this->actingAs($this->peneliti);

        return $this->wf->ajukan([
            'peneliti_utama' => 'Dr. Uji',
            'judul_penelitian' => 'Penelitian Uji',
            'user_id' => $this->peneliti->id,
        ], TipeProposal::Internal);
    }

    protected function petugasCru(): User
    {
        Menu::firstOrCreate(['slug' => 'antrian-cru'], ['nama' => 'Antrian CRU', 'route' => 'antrian.cru']);
        Role::findByName('cru')->givePermissionTo(['antrian-cru.read', 'antrian-cru.update']);

        $cru = User::factory()->create();
        $cru->assignRole('cru');

        return $cru;
    }

    protected function pdf(string $nama = 'pks.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 100, 'application/pdf');
    }

    // ===== PKS bukan lagi syarat Tahap 2 =====

    public function test_pks_bukan_lagi_berkas_wajib_tahap_2(): void
    {
        $wajib = DocumentType::wajibTahap2();

        $this->assertNotContains(DocumentType::Pks, $wajib);
        $this->assertSame(
            [DocumentType::InformedConsent, DocumentType::KerahasiaanData],
            $wajib,
        );
    }

    public function test_pks_ditandai_dokumen_milik_admin(): void
    {
        $this->assertTrue(DocumentType::Pks->milikAdmin());
    }

    /** Hint di layar diturunkan dari aturan validasinya, jadi tidak bisa melenceng. */
    public function test_hint_unggah_mengikuti_aturan_validasi(): void
    {
        $this->assertSame('PDF · maks 10 MB', DocumentType::Pks->hintUnggah());
        $this->assertSame('JPG/JPEG/PDF · maks 5 MB', DocumentType::BuktiBayarCru->hintUnggah());
        $this->assertSame('XLS/XLSX · maks 20 MB', DocumentType::RawData->hintUnggah());
    }

    public function test_peneliti_lolos_tahap_2_tanpa_pks(): void
    {
        $p = $this->buatProposal();
        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik] as $ke) {
            $this->wf->transition($p, $ke);
        }

        $komponen = Livewire::actingAs($this->peneliti)->test(Show::class, ['proposal' => $p]);

        foreach (DocumentType::wajibTahap2() as $jenis) {
            $komponen->set('fileEtik.'.$jenis->value, $this->pdf("{$jenis->value}.pdf"));
        }

        // Poin C formulir etik — wajib dijawab sejak formulir jadi isian
        // terstruktur; isinya tidak relevan bagi PKS, yang diuji di sini hanya
        // bahwa PKS tidak ikut menghalangi.
        $komponen->set('formEtik.multisenter', '0')
            ->set('formEtik.kerjasama', BentukKerjasama::Bukan->value)
            ->set('formEtik.peneliti_asing', '0')
            ->set('formEtik.pernah_diajukan', '0')
            ->set('formEtik.sampel_ke_luar_negeri', '0');

        $komponen->call('kirimBerkasEtik')->assertHasNoErrors();

        $this->assertSame(S::MenungguPenunjukanReviewer, $p->fresh()->status);
        $this->assertSame(0, $p->documents()->where('jenis', DocumentType::Pks->value)->count());
    }

    // ===== CRU yang mengunggah, kapan saja =====

    public function test_cru_unggah_pks_tanpa_mengubah_status(): void
    {
        $p = $this->buatProposal();
        $sebelum = $p->status;

        Livewire::actingAs($this->petugasCru())
            ->test(Show::class, ['proposal' => $p])
            ->set('filePks', $this->pdf())
            ->call('unggahPks')
            ->assertHasNoErrors();

        $this->assertSame($sebelum, $p->fresh()->status);
        $this->assertSame(1, $p->documents()->where('jenis', DocumentType::Pks->value)->count());
    }

    /** Inti alasan pemindahan: PKS boleh menyusul setelah penelitian rampung. */
    public function test_cru_masih_bisa_unggah_pks_setelah_selesai(): void
    {
        $p = $this->buatProposal();

        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik, S::MenungguPenunjukanReviewer,
            S::MenungguReviewReviewer, S::DisetujuiReviewer, S::MenungguPembayaran,
            S::MenungguVerifikasiPembayaran, S::PelaksanaanPenelitian, S::MenungguVerifikasiAkhir,
            S::MenungguSurveyKepuasan, S::Selesai] as $ke) {
            $this->wf->transition($p, $ke);
        }

        $this->assertTrue($p->fresh()->status->isTerminal());

        Livewire::actingAs($this->petugasCru())
            ->test(Show::class, ['proposal' => $p])
            ->set('filePks', $this->pdf())
            ->call('unggahPks')
            ->assertHasNoErrors();

        $this->assertSame(1, $p->documents()->where('jenis', DocumentType::Pks->value)->count());
    }

    public function test_versi_pks_naik_saat_diunggah_ulang(): void
    {
        $p = $this->buatProposal();
        $cru = $this->petugasCru();

        foreach (['pks-draft.pdf', 'pks-final.pdf'] as $nama) {
            Livewire::actingAs($cru)
                ->test(Show::class, ['proposal' => $p])
                ->set('filePks', $this->pdf($nama))
                ->call('unggahPks');
        }

        $versi = $p->documents()->where('jenis', DocumentType::Pks->value)
            ->orderBy('versi')->pluck('versi')->all();

        $this->assertSame([1, 2], $versi);
    }

    public function test_berkas_wajib_kosong_ditolak(): void
    {
        $p = $this->buatProposal();

        Livewire::actingAs($this->petugasCru())
            ->test(Show::class, ['proposal' => $p])
            ->call('unggahPks')
            ->assertHasErrors('filePks');

        $this->assertSame(0, $p->documents()->where('jenis', DocumentType::Pks->value)->count());
    }

    public function test_peneliti_tidak_bisa_unggah_pks(): void
    {
        $p = $this->buatProposal();

        Livewire::actingAs($this->peneliti)
            ->test(Show::class, ['proposal' => $p])
            ->set('filePks', $this->pdf())
            ->call('unggahPks')
            ->assertForbidden();

        $this->assertSame(0, $p->documents()->where('jenis', DocumentType::Pks->value)->count());
    }
}

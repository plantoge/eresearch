<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\ProposalStatus as S;
use App\Livewire\Reviewer\TelaahSederhana;
use App\Models\Menu;
use App\Models\PenugasanReviewer;
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
 * Halaman kerja reviewer satu-layar (docs/fitur/fitur-halaman-reviewer.md).
 *
 * Alur yang diuji: cari → pilih proposal → baca dokumen → pilih keputusan →
 * tulis komentar → konfirmasi → simpan. Semua tanpa pindah halaman.
 */
class TelaahSederhanaTest extends TestCase
{
    use RefreshDatabase;

    protected ProposalWorkflow $wf;

    protected User $peneliti;

    protected User $kepk;

    protected User $rev1;

    protected User $rev2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('dokumen');
        $this->wf = app(ProposalWorkflow::class);

        $this->peneliti = User::factory()->create();
        $this->peneliti->assignRole('peneliti');
        $this->kepk = User::factory()->create();
        $this->kepk->assignRole('kepk');
        $this->rev1 = User::factory()->create();
        $this->rev1->assignRole('reviewer');
        $this->rev2 = User::factory()->create();
        $this->rev2->assignRole('reviewer');

        Menu::firstOrCreate(['slug' => 'antrian-reviewer'], ['nama' => 'Antrian Reviewer', 'route' => 'antrian.reviewer']);
        Role::findByName('reviewer')->givePermissionTo(['antrian-reviewer.read', 'antrian-reviewer.update']);
    }

    /** Proposal siap ditelaah, lengkap dengan dokumen yang bisa dibaca reviewer. */
    protected function proposalSiapReview(string $penelitiUtama = 'Budi Santoso'): Proposal
    {
        $this->actingAs($this->peneliti);
        $p = $this->wf->ajukan([
            'peneliti_utama' => $penelitiUtama, 'judul_penelitian' => 'Judul '.$penelitiUtama,
            'user_id' => $this->peneliti->id,
        ]);

        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik, S::MenungguPenunjukanReviewer] as $ke) {
            $this->wf->transition($p, $ke);
        }

        $this->actingAs($this->kepk);
        $this->wf->simpanDokumen($p, DocumentType::Proposal, UploadedFile::fake()->create('proposal.pdf', 100));
        $this->wf->simpanDokumen($p, DocumentType::FormKajiEtik, UploadedFile::fake()->create('form.pdf', 100));

        return $p;
    }

    // ===== Otorisasi & daftar =====

    public function test_reviewer_hanya_melihat_proposal_yang_ditugaskan_ke_dirinya(): void
    {
        $milikRev1 = $this->proposalSiapReview('Budi Santoso');
        $bukanMilikRev1 = $this->proposalSiapReview('Siti Aminah');

        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($milikRev1, [$this->rev1->id]);
        $this->wf->tugaskanReviewer($bukanMilikRev1, [$this->rev2->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Aminah');
    }

    public function test_pencarian_menyaring_berdasarkan_nama_peneliti(): void
    {
        $p1 = $this->proposalSiapReview('Budi Santoso');
        $p2 = $this->proposalSiapReview('Siti Aminah');

        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p1, [$this->rev1->id]);
        $this->wf->tugaskanReviewer($p2, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->set('cari', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Aminah');
    }

    public function test_reviewer_tak_ditugaskan_tidak_bisa_membuka_proposal_orang_lain(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev2->id]); // hanya rev2

        $this->actingAs($this->rev1); // tidak ditugaskan
        Livewire::test(TelaahSederhana::class)
            ->set('proposalIdTerbuka', $p->id)
            ->assertForbidden();
    }

    // ===== Ringkasan (PRD §8) =====

    public function test_ringkasan_menampilkan_total_perlu_dan_sudah_diperiksa(): void
    {
        $belum1 = $this->proposalSiapReview('Peneliti A');
        $belum2 = $this->proposalSiapReview('Peneliti B');
        $sudah = $this->proposalSiapReview('Peneliti C');

        $this->actingAs($this->kepk);
        foreach ([$belum1, $belum2, $sudah] as $p) {
            $this->wf->tugaskanReviewer($p, [$this->rev1->id]);
        }

        $this->actingAs($this->rev1);
        $this->wf->reviewerMerespons($sudah, 'approve', 'Bagus');

        Livewire::test(TelaahSederhana::class)
            ->assertViewHas('totalProposal', 3)
            ->assertViewHas('perluDiperiksa', 2)
            ->assertViewHas('sudahDiperiksa', 1);
    }

    public function test_ringkasan_terscope_ke_reviewer_yang_login(): void
    {
        $p1 = $this->proposalSiapReview('Peneliti A');
        $p2 = $this->proposalSiapReview('Peneliti B');

        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p1, [$this->rev1->id]);
        $this->wf->tugaskanReviewer($p2, [$this->rev2->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)->assertViewHas('totalProposal', 1);

        $this->actingAs($this->rev2);
        Livewire::test(TelaahSederhana::class)->assertViewHas('totalProposal', 1);
    }

    // ===== Bahasa status yang jelas (PRD §4.4 & §19) =====

    public function test_status_ditulis_dengan_bahasa_yang_jelas_bukan_istilah_internal(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->assertSee('Belum Diperiksa')
            ->assertDontSee('Menunggu Review Reviewer');
    }

    public function test_status_disetujui_setelah_reviewer_acc(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id, $this->rev2->id]);

        $this->actingAs($this->rev1);
        $this->wf->reviewerMerespons($p, 'approve', 'Setuju');

        Livewire::test(TelaahSederhana::class)->assertSee('Disetujui');
    }

    // ===== Detail proposal & dokumen (PRD §11 & §12) =====

    public function test_pilih_proposal_menampilkan_detail_dan_dokumen(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->assertSee('DETAIL PROPOSAL')
            ->assertSee($p->kode)                 // nomor proposal
            ->assertSee('Budi Santoso')           // peneliti
            ->assertSee('Proposal Penelitian')    // dokumen
            ->assertSee('Form Kaji Etik')
            ->assertSee('BACA DOKUMEN');
    }

    public function test_baca_dokumen_membuka_viewer_di_halaman_yang_sama(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);
        $dok = $p->dokumenTerakhir(DocumentType::Proposal);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->call('bacaDokumen', $dok->id)
            ->assertSet('dokumenDibaca', $dok->id)
            ->assertSeeHtml('<iframe');
    }

    public function test_dokumen_proposal_lain_tidak_bisa_dibaca(): void
    {
        $milik = $this->proposalSiapReview('Peneliti A');
        $asing = $this->proposalSiapReview('Peneliti B');

        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($milik, [$this->rev1->id]);
        $this->wf->tugaskanReviewer($asing, [$this->rev2->id]);

        $dokAsing = $asing->dokumenTerakhir(DocumentType::Proposal);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $milik->id)
            ->call('bacaDokumen', $dokAsing->id)
            ->assertForbidden();
    }

    // ===== Alur keputusan dua langkah (PRD §14, §16, §17) =====

    public function test_simpan_tanpa_memilih_keputusan_ditolak(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->call('mintaKonfirmasi')
            ->assertHasErrors(['keputusan'])
            ->assertSet('konfirmasiTampil', false);
    }

    public function test_perlu_revisi_wajib_isi_komentar(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->call('pilihKeputusan', 'revise')
            ->set('catatan', '')
            ->call('mintaKonfirmasi')
            ->assertHasErrors(['catatan'])
            ->assertSet('konfirmasiTampil', false);

        $this->assertDatabaseHas('rspi.kepk_penugasan_reviewer', [
            'proposal_id' => $p->id, 'status' => PenugasanReviewer::MENUNGGU,
        ]);
    }

    public function test_disetujui_boleh_tanpa_komentar(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->call('pilihKeputusan', 'approve')
            ->call('mintaKonfirmasi')
            ->assertHasNoErrors()
            ->assertSet('konfirmasiTampil', true);
    }

    public function test_simpan_review_disetujui_mengubah_status_penugasan(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->call('pilihKeputusan', 'approve')
            ->set('catatan', 'Sudah bagus')
            ->call('mintaKonfirmasi')
            ->call('simpanReview')
            ->assertSet('konfirmasiTampil', false);

        $this->assertSame(S::DisetujuiReviewer, $p->fresh()->status);
        $this->assertDatabaseHas('rspi.kepk_penugasan_reviewer', [
            'proposal_id' => $p->id, 'reviewer_id' => $this->rev1->id, 'status' => PenugasanReviewer::ACC,
        ]);
        $this->assertDatabaseHas('rspi.kepk_telaah_reviewer', [
            'proposal_id' => $p->id, 'reviewer_id' => $this->rev1->id,
            'keputusan' => 'approve', 'komentar' => 'Sudah bagus',
        ]);
    }

    public function test_simpan_review_perlu_revisi_mencatat_komentar(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->call('pilihKeputusan', 'revise')
            ->set('catatan', 'Metodologi perlu diperbaiki')
            ->call('mintaKonfirmasi')
            ->call('simpanReview');

        $this->assertDatabaseHas('rspi.kepk_telaah_reviewer', [
            'proposal_id' => $p->id, 'reviewer_id' => $this->rev1->id,
            'keputusan' => 'revise', 'komentar' => 'Metodologi perlu diperbaiki',
        ]);
        // Keputusan revisi TIDAK memindahkan status proposal — bolanya di KEPK.
        $this->assertSame(S::MenungguReviewReviewer, $p->fresh()->status);
    }

    public function test_batal_konfirmasi_tidak_menyimpan_dan_tidak_menghapus_komentar(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->call('pilihKeputusan', 'revise')
            ->set('catatan', 'Draf komentar saya')
            ->call('mintaKonfirmasi')
            ->call('batalKonfirmasi')
            ->assertSet('konfirmasiTampil', false)
            ->assertSet('catatan', 'Draf komentar saya'); // draf tidak hilang

        $this->assertDatabaseMissing('rspi.kepk_telaah_reviewer', ['proposal_id' => $p->id]);
    }

    // ===== Sesudah review (PRD §18) =====

    public function test_penugasan_sudah_direspons_ditampilkan_read_only_tanpa_form(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id, $this->rev2->id]);

        $this->actingAs($this->rev1);
        $this->wf->reviewerMerespons($p, 'approve', 'Bagus sekali');

        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->assertSee('Bagus sekali')
            ->assertDontSeeHtml('wire:click="mintaKonfirmasi"');
    }

    public function test_riwayat_multi_ronde_menampilkan_semua_ronde_reviewer_ini(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        $this->wf->reviewerMerespons($p, 'revise', 'Ronde satu perlu perbaikan');

        $this->actingAs($this->kepk);
        $this->wf->transition($p, S::PerluRevisiReviewer);
        $this->actingAs($this->peneliti);
        $this->wf->resetPenugasanReviewer($p);
        $this->wf->transition($p, S::MenungguReviewReviewer);

        $this->actingAs($this->rev1);
        $this->wf->reviewerMerespons($p, 'approve', 'Ronde dua sudah oke');

        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->assertSee('Ronde satu perlu perbaikan')
            ->assertSee('Ronde dua sudah oke');
    }

    public function test_komentar_reviewer_lain_tidak_pernah_terlihat(): void
    {
        $p = $this->proposalSiapReview();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id, $this->rev2->id]);

        $this->actingAs($this->rev2);
        $this->wf->reviewerMerespons($p, 'revise', 'Rahasia milik reviewer dua');

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->call('buka', $p->id)
            ->assertDontSee('Rahasia milik reviewer dua');
    }

    // ===== Layout halaman (dirender lewat HTTP, bukan Livewire::test) =====

    public function test_halaman_reviewer_render(): void
    {
        $this->actingAs($this->rev1)
            ->get(route('reviewer.telaah'))
            ->assertOk();
    }

    public function test_layout_reviewer_punya_pemilih_tema(): void
    {
        $this->actingAs($this->rev1)
            ->get(route('reviewer.telaah'))
            ->assertOk()
            ->assertSee('Tema')
            ->assertSee("localStorage.setItem('theme'", false);
    }

    public function test_layout_reviewer_tetap_tanpa_sidebar(): void
    {
        // Navigasi minimal tetap syarat PRD §4.5 walau pemilih tema ditambahkan.
        $this->actingAs($this->rev1)
            ->get(route('reviewer.telaah'))
            ->assertOk()
            ->assertSee('Keluar')
            ->assertDontSee('main-drawer', false);
    }

    // ===== Empty state (PRD §20) =====

    public function test_empty_state_saat_belum_ada_penugasan(): void
    {
        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)->assertSee('Belum ada proposal');
    }

    public function test_empty_state_saat_pencarian_tidak_menemukan_apa_pun(): void
    {
        $p = $this->proposalSiapReview('Budi Santoso');
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        Livewire::test(TelaahSederhana::class)
            ->set('cari', 'tidak-ada-yang-cocok')
            ->assertSee('Proposal tidak ditemukan');
    }
}

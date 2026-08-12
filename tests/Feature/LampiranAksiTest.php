<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\ProposalStatus as S;
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
 * Lampiran pada kartu aksi tidak boleh hilang diam-diam.
 *
 * Kartu "Aksi CRU" dan "Keputusan KEPK" masing-masing punya SATU input berkas
 * tapi beberapa tombol. Sebelumnya hanya sebagian tombol yang membaca
 * `$fileUpload`; menekan tombol lain membuat berkas yang sudah dilampirkan
 * terbuang tanpa pesan apa pun — status tetap berpindah, dokumen tidak muncul
 * di kartu Dokumen. Dilaporkan pengguna 12 Agustus 2026 untuk "Loloskan ke
 * KEPK" dan "Lanjut ke Pembayaran".
 *
 * Lampirannya tetap OPSIONAL: aksi harus tetap jalan tanpa berkas.
 */
class LampiranAksiTest extends TestCase
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

    protected function petugasCru(): User
    {
        Menu::firstOrCreate(['slug' => 'antrian-cru'], ['nama' => 'Antrian CRU', 'route' => 'antrian.cru']);
        Role::findByName('cru')->givePermissionTo(['antrian-cru.read', 'antrian-cru.update']);
        $u = User::factory()->create();
        $u->assignRole('cru');

        return $u;
    }

    protected function petugasKepk(): User
    {
        Menu::firstOrCreate(['slug' => 'kaji-etik'], ['nama' => 'Kaji Etik', 'route' => 'antrian.kepk']);
        Role::findByName('kepk')->givePermissionTo(['kaji-etik.read', 'kaji-etik.update']);
        $u = User::factory()->create();
        $u->assignRole('kepk');

        return $u;
    }

    protected function pdf(string $nama = 'tanggapan.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 100, 'application/pdf');
    }

    protected function buatProposal(): Proposal
    {
        $this->actingAs($this->peneliti);

        return $this->wf->ajukan([
            'peneliti_utama' => 'Dr. Uji', 'judul_penelitian' => 'Penelitian Uji',
            'user_id' => $this->peneliti->id,
        ]);
    }

    protected function jumlahSuratTanggapan(Proposal $p): int
    {
        return $p->documents()->where('jenis', DocumentType::SuratTanggapan->value)->count();
    }

    /** "Loloskan ke KEPK" hanya sah dari Menunggu Presentasi (lihat allowedNext). */
    protected function proposalSiapLolos(): Proposal
    {
        $p = $this->buatProposal();
        $this->actingAs($this->peneliti);
        $this->wf->transition($p, S::MenungguPresentasi);

        return $p->fresh();
    }

    // ===== Aksi CRU =====

    public function test_loloskan_menyimpan_lampiran_bila_ada(): void
    {
        $p = $this->proposalSiapLolos();

        $this->actingAs($this->petugasCru());
        Livewire::test(Show::class, ['proposal' => $p])
            ->set('fileUpload', $this->pdf())
            ->call('loloskan')
            ->assertHasNoErrors();

        $this->assertSame(S::MenungguKelengkapanBerkasEtik, $p->fresh()->status);
        $this->assertSame(1, $this->jumlahSuratTanggapan($p));
    }

    public function test_loloskan_tetap_jalan_tanpa_lampiran(): void
    {
        $p = $this->proposalSiapLolos();

        $this->actingAs($this->petugasCru());
        Livewire::test(Show::class, ['proposal' => $p])
            ->call('loloskan')
            ->assertHasNoErrors();

        $this->assertSame(S::MenungguKelengkapanBerkasEtik, $p->fresh()->status);
        $this->assertSame(0, $this->jumlahSuratTanggapan($p));
    }

    public function test_minta_presentasi_menyimpan_lampiran_bila_ada(): void
    {
        $p = $this->buatProposal();

        $this->actingAs($this->petugasCru());
        Livewire::test(Show::class, ['proposal' => $p])
            ->set('tanggal_presentasi', '2026-09-01T09:00')
            ->set('kategori_presentasi', 'Luring')
            ->set('media_presentasi', 'R. Rapat')
            ->set('fileUpload', $this->pdf())
            ->call('mintaPresentasi')
            ->assertHasNoErrors();

        $this->assertSame(S::MenungguPresentasi, $p->fresh()->status);
        $this->assertSame(1, $this->jumlahSuratTanggapan($p));
    }

    // ===== Aksi KEPK =====

    /** Proposal yang semua reviewernya sudah ACC — siap dilanjutkan ke pembayaran. */
    protected function proposalSemuaAcc(): Proposal
    {
        $p = $this->buatProposal();
        $kepk = $this->petugasKepk();

        $this->actingAs($this->peneliti);
        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik, S::MenungguPenunjukanReviewer] as $ke) {
            $this->wf->transition($p, $ke);
        }

        $rev = User::factory()->create();
        $rev->assignRole('reviewer');

        $this->actingAs($kepk);
        $this->wf->tugaskanReviewer($p, [$rev->id]);

        $this->actingAs($rev);
        $this->wf->reviewerMerespons($p, 'approve', 'OK');

        return $p->fresh();
    }

    public function test_kepk_lanjut_menyimpan_lampiran_bila_ada(): void
    {
        $p = $this->proposalSemuaAcc();

        $this->actingAs($this->petugasKepk());
        Livewire::test(Show::class, ['proposal' => $p])
            ->set('fileUpload', $this->pdf())
            ->call('kepkLanjut')
            ->assertHasNoErrors();

        $this->assertSame(S::MenungguPembayaran, $p->fresh()->status);
        $this->assertSame(1, $this->jumlahSuratTanggapan($p));
    }

    public function test_kepk_lanjut_tetap_jalan_tanpa_lampiran(): void
    {
        $p = $this->proposalSemuaAcc();

        $this->actingAs($this->petugasKepk());
        Livewire::test(Show::class, ['proposal' => $p])
            ->call('kepkLanjut')
            ->assertHasNoErrors();

        $this->assertSame(S::MenungguPembayaran, $p->fresh()->status);
        $this->assertSame(0, $this->jumlahSuratTanggapan($p));
    }

    public function test_kepk_tolak_menyimpan_lampiran_bila_ada(): void
    {
        $p = $this->proposalSemuaAcc();

        $this->actingAs($this->petugasKepk());
        Livewire::test(Show::class, ['proposal' => $p])
            ->set('catatan', 'Tidak layak secara etik')
            ->set('fileUpload', $this->pdf())
            ->call('kepkTolak')
            ->assertHasNoErrors();

        $this->assertSame(S::DitolakKajiEtik, $p->fresh()->status);
        $this->assertSame(1, $this->jumlahSuratTanggapan($p));
    }

    /** Lampiran yang bukan PDF harus ditolak, bukan diam-diam dilewati. */
    public function test_lampiran_bukan_pdf_ditolak_pada_loloskan(): void
    {
        $p = $this->proposalSiapLolos();

        $this->actingAs($this->petugasCru());
        Livewire::test(Show::class, ['proposal' => $p])
            ->set('fileUpload', UploadedFile::fake()->create('virus.exe', 10))
            ->call('loloskan')
            ->assertHasErrors(['fileUpload']);

        $this->assertSame(S::MenungguPresentasi, $p->fresh()->status);
        $this->assertSame(0, $this->jumlahSuratTanggapan($p));
    }
}

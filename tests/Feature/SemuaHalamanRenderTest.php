<?php

namespace Tests\Feature;

use App\Enums\ProposalStatus as S;
use App\Enums\TipeProposal;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Smoke test seluruh halaman.
 *
 * Ada karena pemisahan struktur CRU/KEPK menyentuh hampir semua model, sementara
 * Dashboard, Laporan, Audit Log, Antrian, dan daftar Proposal tidak punya tes sama
 * sekali — jadi salah nama tabel di sana tidak akan ketahuan sampai dibuka manusia.
 */
class SemuaHalamanRenderTest extends TestCase
{
    use RefreshDatabase;

    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->super = User::factory()->create();
        $this->super->assignRole('superadmin');
        $this->super->givePermissionTo(Permission::all());

        $this->actingAs($this->super);
    }

    public function test_semua_halaman_render(): void
    {
        $halaman = [
            '/dashboard', '/profile',
            '/proposal', '/proposal/baru',
            '/antrian/cru', '/antrian/kaji-etik', '/antrian/reviewer', '/reviewer/telaah',
            '/admin/users', '/admin/roles', '/admin/menus', '/admin/survey', '/admin/kontak',
            '/laporan', '/audit-log',
        ];

        foreach ($halaman as $url) {
            $this->assertSame(200, $this->get($url)->getStatusCode(), "Halaman {$url} tidak render");
        }
    }

    /**
     * Halaman Show merender panel yang berbeda per status — termasuk kartu yang
     * membaca berkas kerja CRU/KEPK yang baru (presentasi, pembayaran, protokol etik).
     */
    public function test_halaman_proposal_render_di_setiap_status(): void
    {
        $peneliti = User::where('email', 'peneliti@eproposal.test')->firstOrFail();

        $wf = app(ProposalWorkflow::class);
        $p = $wf->ajukan([
            'peneliti_utama' => 'Dr. Uji',
            'judul_penelitian' => 'Penelitian Uji',
            'user_id' => $peneliti->id,
        ], TipeProposal::Internal);

        $this->assertSame(200, $this->get("/proposal/{$p->id}")->getStatusCode(), 'status awal');

        $jalur = [
            S::MenungguPresentasi,
            S::MenungguKelengkapanBerkasEtik,
            S::MenungguPenunjukanReviewer,
            S::PerluRevisiBerkasEtik,        // KEPK kembalikan berkas
            S::MenungguPenunjukanReviewer,   // peneliti kirim ulang
            S::MenungguReviewReviewer,
            S::DisetujuiReviewer,
            S::MenungguPembayaran,
            S::MenungguVerifikasiPembayaran,
            S::PelaksanaanPenelitian,
            S::MenungguVerifikasiAkhir,
            S::MenungguSurveyKepuasan,
            S::Selesai,
        ];

        foreach ($jalur as $ke) {
            $wf->transition($p, $ke);

            $this->assertSame(
                200,
                $this->get("/proposal/{$p->id}")->getStatusCode(),
                "Halaman proposal gagal render pada status {$ke->value}",
            );
        }

        $this->assertInstanceOf(Proposal::class, $p->fresh());
    }
}

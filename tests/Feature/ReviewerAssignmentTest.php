<?php

namespace Tests\Feature;

use App\Enums\ProposalStatus as S;
use App\Enums\TipeProposal;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReviewerAssignmentTest extends TestCase
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
        $this->wf = app(ProposalWorkflow::class);

        $this->peneliti = User::factory()->create();
        $this->peneliti->assignRole('peneliti');
        $this->kepk = User::factory()->create();
        $this->kepk->assignRole('kepk');
        $this->rev1 = User::factory()->create();
        $this->rev1->assignRole('reviewer');
        $this->rev2 = User::factory()->create();
        $this->rev2->assignRole('reviewer');
    }

    /** Proposal siap ditunjuk reviewer (status Menunggu Penunjukan Reviewer). */
    protected function proposalSiapPenunjukan(): Proposal
    {
        $this->actingAs($this->peneliti);
        $p = $this->wf->ajukan([
            'peneliti_utama' => 'X', 'judul_penelitian' => 'Y', 'user_id' => $this->peneliti->id,
        ], TipeProposal::Internal);

        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik, S::MenungguPenunjukanReviewer] as $ke) {
            $this->wf->transition($p, $ke);
        }

        return $p;
    }

    public function test_kepk_menugaskan_dua_reviewer(): void
    {
        $p = $this->proposalSiapPenunjukan();

        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id, $this->rev2->id]);

        $this->assertSame(S::MenungguReviewReviewer, $p->fresh()->status);
        $this->assertSame(2, $p->penugasanReviewer()->count());
    }

    public function test_penugasan_minimal_satu_reviewer(): void
    {
        $p = $this->proposalSiapPenunjukan();

        $this->actingAs($this->kepk);
        $this->expectException(HttpException::class);
        $this->wf->tugaskanReviewer($p, []);
    }

    public function test_semua_acc_baru_otomatis_disetujui_reviewer(): void
    {
        $p = $this->proposalSiapPenunjukan();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id, $this->rev2->id]);

        // Reviewer 1 ACC → status TETAP menunggu reviewer lain
        $this->actingAs($this->rev1);
        $this->wf->reviewerMerespons($p, 'approve', 'OK');
        $this->assertSame(S::MenungguReviewReviewer, $p->fresh()->status);
        $this->assertFalse($p->semuaReviewerAcc());

        // Reviewer 2 ACC → otomatis Disetujui Reviewer (bola KEPK)
        $this->actingAs($this->rev2);
        $this->wf->reviewerMerespons($p, 'approve', 'Setuju');
        $this->assertSame(S::DisetujuiReviewer, $p->fresh()->status);
        $this->assertTrue($p->fresh()->semuaReviewerAcc());
    }

    public function test_respons_revisi_tidak_mengubah_status_dan_reset_saat_peneliti_kirim_ulang(): void
    {
        $p = $this->proposalSiapPenunjukan();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id, $this->rev2->id]);

        // Reviewer 1 minta revisi (jawaban ke KEPK, status tetap)
        $this->actingAs($this->rev1);
        $this->wf->reviewerMerespons($p, 'revise', 'Perbaiki metodologi');
        $this->assertSame(S::MenungguReviewReviewer, $p->fresh()->status);
        $this->assertDatabaseHas('rspi.kepk_penugasan_reviewer', [
            'proposal_id' => $p->id, 'reviewer_id' => $this->rev1->id, 'status' => 'revisi',
        ]);

        // KEPK meneruskan revisi ke peneliti
        $this->actingAs($this->kepk);
        $this->wf->transition($p, S::PerluRevisiReviewer, 'Rangkuman masukan reviewer');

        // Peneliti kirim ulang → penugasan reset ke "menunggu" (ronde baru)
        $this->actingAs($this->peneliti);
        $this->wf->resetPenugasanReviewer($p);
        $this->wf->transition($p, S::MenungguReviewReviewer);

        $this->assertSame(0, $p->penugasanReviewer()->where('status', '!=', 'menunggu')->count());
    }

    public function test_reviewer_tak_ditugaskan_tidak_boleh_merespons(): void
    {
        $p = $this->proposalSiapPenunjukan();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev2); // tidak ditugaskan
        $this->expectException(HttpException::class);
        $this->wf->reviewerMerespons($p, 'approve');
    }

    public function test_ronde_bertambah_per_respons_reviewer(): void
    {
        $p = $this->proposalSiapPenunjukan();
        $this->actingAs($this->kepk);
        $this->wf->tugaskanReviewer($p, [$this->rev1->id]);

        $this->actingAs($this->rev1);
        $this->wf->reviewerMerespons($p, 'revise', 'Ronde 1');

        $this->actingAs($this->kepk);
        $this->wf->transition($p, S::PerluRevisiReviewer);
        $this->actingAs($this->peneliti);
        $this->wf->resetPenugasanReviewer($p);
        $this->wf->transition($p, S::MenungguReviewReviewer);

        $this->actingAs($this->rev1);
        $this->wf->reviewerMerespons($p, 'approve', 'Ronde 2 OK');

        $this->assertSame([1, 2], $p->telaahReviewer()->where('reviewer_id', $this->rev1->id)
            ->orderBy('ronde')->pluck('ronde')->all());
        $this->assertSame(S::DisetujuiReviewer, $p->fresh()->status);
    }
}

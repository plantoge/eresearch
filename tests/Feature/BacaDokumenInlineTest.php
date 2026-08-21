<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\ProposalStatus as S;
use App\Enums\TipeProposal;
use App\Models\Menu;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Halaman reviewer menampilkan PDF di dalam iframe pada halaman yang sama
 * (docs/fitur/fitur-halaman-reviewer.md §13). Itu hanya mungkin bila berkas
 * disajikan `inline`; disposisi `attachment` akan memaksa unduhan dan iframe
 * tampil kosong.
 */
class BacaDokumenInlineTest extends TestCase
{
    use RefreshDatabase;

    protected ProposalWorkflow $wf;

    protected User $peneliti;

    protected User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('dokumen');
        $this->wf = app(ProposalWorkflow::class);

        Menu::firstOrCreate(['slug' => 'antrian-reviewer'], ['nama' => 'Antrian Reviewer', 'route' => 'antrian.reviewer']);
        Role::findByName('reviewer')->givePermissionTo(['antrian-reviewer.read', 'antrian-reviewer.update']);

        $this->peneliti = User::factory()->create();
        $this->peneliti->assignRole('peneliti');
        $this->reviewer = User::factory()->create();
        $this->reviewer->assignRole('reviewer');
    }

    protected function proposalDenganDokumen(): Proposal
    {
        $this->actingAs($this->peneliti);
        $p = $this->wf->ajukan([
            'peneliti_utama' => 'Budi', 'judul_penelitian' => 'Judul', 'user_id' => $this->peneliti->id,
        ], TipeProposal::Internal);
        $this->wf->simpanDokumen($p, DocumentType::Proposal, UploadedFile::fake()->create('proposal.pdf', 100));

        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik, S::MenungguPenunjukanReviewer] as $ke) {
            $this->wf->transition($p, $ke);
        }

        return $p;
    }

    public function test_mode_baca_menyajikan_dokumen_inline_bukan_unduhan(): void
    {
        $p = $this->proposalDenganDokumen();
        $doc = $p->dokumenTerakhir(DocumentType::Proposal);

        $this->actingAs($this->reviewer)
            ->get(route('dokumen.download', ['document' => $doc, 'baca' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename="proposal.pdf"');
    }

    public function test_tanpa_mode_baca_tetap_memaksa_unduhan(): void
    {
        $p = $this->proposalDenganDokumen();
        $doc = $p->dokumenTerakhir(DocumentType::Proposal);

        $response = $this->actingAs($this->reviewer)
            ->get(route('dokumen.download', $doc))
            ->assertOk();

        $this->assertStringStartsWith('attachment', $response->headers->get('content-disposition'));
    }

    public function test_mode_baca_tidak_melewati_otorisasi(): void
    {
        $p = $this->proposalDenganDokumen();
        $doc = $p->dokumenTerakhir(DocumentType::Proposal);

        $orangLain = User::factory()->create();
        $orangLain->assignRole('peneliti');

        $this->actingAs($orangLain)
            ->get(route('dokumen.download', ['document' => $doc, 'baca' => 1]))
            ->assertForbidden();
    }
}

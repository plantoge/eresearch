<?php

namespace Tests\Feature;

use App\Enums\TipeProposal;
use App\Livewire\Proposal\Create;
use App\Models\Proposal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tipe proposal dipilih peneliti di form pengajuan dan langsung tercetak jadi
 * dua digit pertama nomor proposal. Karena nomor itu permanen, pilihannya harus
 * dipaksa ada SEBELUM pengajuan tersimpan — bukan diperbaiki belakangan.
 */
class PengajuanTipeProposalTest extends TestCase
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

    protected function form(string $tipe): Testable
    {
        return Livewire::test(Create::class)
            ->set('tipe_proposal', $tipe)
            ->set('peneliti_utama', 'Dr. Uji')
            ->set('judul_penelitian', 'Penelitian Uji')
            ->set('surat_pengantar', UploadedFile::fake()->create('pengantar.pdf', 50, 'application/pdf'))
            ->set('proposal_penelitian', UploadedFile::fake()->create('proposal.pdf', 50, 'application/pdf'));
    }

    public function test_pengajuan_tanpa_memilih_tipe_ditolak(): void
    {
        $this->form('')
            ->call('simpan')
            ->assertHasErrors(['tipe_proposal' => 'required']);

        $this->assertDatabaseCount('rspi.proposal', 0);
    }

    public function test_tipe_di_luar_daftar_ditolak(): void
    {
        $this->form('99')
            ->call('simpan')
            ->assertHasErrors(['tipe_proposal']);

        $this->assertDatabaseCount('rspi.proposal', 0);
    }

    public function test_pilihan_eksternal_terbawa_ke_nomor_proposal(): void
    {
        $this->form(TipeProposal::Eksternal->value)
            ->call('simpan')
            ->assertHasNoErrors();

        $p = Proposal::sole();

        $this->assertSame(TipeProposal::Eksternal, $p->tipe_proposal);
        $this->assertStringStartsWith('02', $p->kode);
        $this->assertSame(10, strlen($p->kode));
    }
}

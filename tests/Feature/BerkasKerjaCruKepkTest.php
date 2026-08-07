<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\StatusPembayaran;
use App\Enums\TujuanPembayaran;
use App\Models\BerkasPenelitian;
use App\Models\DokumenTelaah;
use App\Models\IzinPenelitian;
use App\Models\Menu;
use App\Models\Pembayaran;
use App\Models\Proposal;
use App\Models\ProtokolEtik;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Berkas kerja per unit: CRU dan KEPK punya tabelnya masing-masing.
 *
 * Catatan menjalankan: pelanggaran unique di PostgreSQL membatalkan seluruh
 * transaksi, dan RefreshDatabase membungkus tiap tes dalam satu transaksi —
 * karena itu setiap expectException harus jadi operasi database TERAKHIR di
 * metodenya, bukan digabung dalam satu tes berisi tiga model.
 */
class BerkasKerjaCruKepkTest extends TestCase
{
    use RefreshDatabase;

    protected User $peneliti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->peneliti = User::factory()->create();
        $this->peneliti->assignRole('peneliti');
        $this->actingAs($this->peneliti);
    }

    protected function buatProposal(): Proposal
    {
        return app(ProposalWorkflow::class)->ajukan([
            'peneliti_utama' => 'Dr. Uji',
            'judul_penelitian' => 'Penelitian Uji',
            'user_id' => $this->peneliti->id,
        ]);
    }

    // ===== 1:1 ditegakkan database, bukan hanya konvensi =====

    public function test_berkas_penelitian_hanya_satu_per_proposal(): void
    {
        $p = $this->buatProposal();
        BerkasPenelitian::create(['proposal_id' => $p->id]);

        $this->expectException(QueryException::class);
        BerkasPenelitian::create(['proposal_id' => $p->id]);
    }

    public function test_izin_penelitian_hanya_satu_per_proposal(): void
    {
        $p = $this->buatProposal();
        IzinPenelitian::create(['proposal_id' => $p->id]);

        $this->expectException(QueryException::class);
        IzinPenelitian::create(['proposal_id' => $p->id]);
    }

    public function test_protokol_etik_hanya_satu_per_proposal(): void
    {
        $p = $this->buatProposal();
        ProtokolEtik::create(['proposal_id' => $p->id]);

        $this->expectException(QueryException::class);
        ProtokolEtik::create(['proposal_id' => $p->id]);
    }

    /** Partial unique `where deleted_at is null`: baris terhapus tak boleh mengunci. */
    public function test_satelit_soft_deleted_tidak_menghalangi_baris_baru(): void
    {
        $p = $this->buatProposal();

        foreach ([BerkasPenelitian::class, IzinPenelitian::class, ProtokolEtik::class] as $model) {
            $model::create(['proposal_id' => $p->id])->delete();
            $model::create(['proposal_id' => $p->id]);

            $this->assertSame(1, $model::where('proposal_id', $p->id)->count(), $model);
        }
    }

    // ===== Pembayaran: dua tagihan terpisah per proposal =====

    public function test_dua_tujuan_pembayaran_berdampingan(): void
    {
        $p = $this->buatProposal();

        Pembayaran::create(['proposal_id' => $p->id, 'tujuan' => TujuanPembayaran::Cru->value]);
        Pembayaran::create(['proposal_id' => $p->id, 'tujuan' => TujuanPembayaran::Kepk->value]);

        $this->assertSame(2, $p->pembayaran()->count());
        $this->assertSame(StatusPembayaran::Menunggu, $p->pembayaran()->first()->status);
    }

    public function test_pembayaran_unik_per_proposal_dan_tujuan(): void
    {
        $p = $this->buatProposal();
        Pembayaran::create(['proposal_id' => $p->id, 'tujuan' => TujuanPembayaran::Cru->value]);

        $this->expectException(QueryException::class);
        Pembayaran::create(['proposal_id' => $p->id, 'tujuan' => TujuanPembayaran::Cru->value]);
    }

    // ===== Kerahasiaan berkas telaah =====

    protected function dokumenTelaah(Proposal $p): DokumenTelaah
    {
        Storage::disk('dokumen')->put("telaah/{$p->id}.pdf", 'PDF');

        return DokumenTelaah::create([
            'proposal_id' => $p->id,
            'path' => "telaah/{$p->id}.pdf",
            'nama_asli' => 'tanggapan-reviewer.pdf',
        ]);
    }

    protected function petugasKepk(): User
    {
        // Permission dibuat MenuObserver dari slug menu (pola SurveyPageSmokeTest).
        Menu::create(['nama' => 'Kaji Etik', 'slug' => 'kaji-etik', 'route' => 'antrian.kepk']);
        Role::findByName('kepk')->givePermissionTo(['kaji-etik.read', 'kaji-etik.update']);

        $kepk = User::factory()->create();
        $kepk->assignRole('kepk');

        return $kepk;
    }

    public function test_peneliti_tidak_bisa_mengunduh_berkas_telaah(): void
    {
        Storage::fake('dokumen');
        $p = $this->buatProposal();
        $dokumen = $this->dokumenTelaah($p);

        // Pemilik proposal sekalipun tidak punya jalan masuk — tidak ada cabang
        // "kecuali peneliti" yang bisa lolos di kemudian hari.
        $this->actingAs($this->peneliti)
            ->get(route('dokumen-telaah.download', $dokumen))
            ->assertForbidden();
    }

    public function test_petugas_kepk_bisa_mengunduh_berkas_telaah(): void
    {
        Storage::fake('dokumen');
        $p = $this->buatProposal();
        $dokumen = $this->dokumenTelaah($p);

        $this->actingAs($this->petugasKepk())
            ->get(route('dokumen-telaah.download', $dokumen))
            ->assertOk();
    }

    /** Berkas telaah tidak lagi bisa masuk ke tabel dokumen umum lewat enum. */
    public function test_tanggapan_reviewer_bukan_lagi_jenis_dokumen(): void
    {
        $this->assertNull(DocumentType::tryFrom('tanggapan_reviewer'));
    }
}

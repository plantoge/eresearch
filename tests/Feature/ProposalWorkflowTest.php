<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\ProposalStatus as S;
use App\Enums\TipeProposal;
use App\Enums\Unit;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected ProposalWorkflow $wf;

    protected User $peneliti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->wf = app(ProposalWorkflow::class);
        $this->peneliti = User::factory()->create();
        $this->peneliti->assignRole('peneliti');
        $this->actingAs($this->peneliti);
    }

    protected function buatProposal(TipeProposal $tipe = TipeProposal::Internal): Proposal
    {
        return $this->wf->ajukan([
            'peneliti_utama' => 'Dr. Uji',
            'judul_penelitian' => 'Penelitian Uji',
            'user_id' => $this->peneliti->id,
        ], $tipe);
    }

    public function test_pengajuan_membuat_status_awal_kode_dan_history(): void
    {
        $p = $this->buatProposal();

        $this->assertSame(S::MenungguVerifikasiBerkas, $p->status);
        $this->assertSame(Unit::Penelitian, $p->unit_sekarang);
        $this->assertSame(sprintf('01%02d%02d0001', now()->year % 100, now()->month), $p->kode);
        $this->assertDatabaseHas('rspi.proposal_status_history', [
            'proposal_id' => $p->id,
            'to_status' => S::MenungguVerifikasiBerkas->value,
        ]);
    }

    public function test_nomor_increment_per_tahun(): void
    {
        $this->buatProposal();
        $p2 = $this->buatProposal();

        $this->assertSame(2, $p2->nomor);
        $this->assertStringEndsWith('0002', $p2->kode);
    }

    /**
     * Internal dan eksternal berbagi SATU deret per tahun — kalau deretnya
     * dipisah per tipe, proposal kedua akan berakhir 0001, bukan 0002.
     */
    public function test_internal_dan_eksternal_berbagi_satu_deret(): void
    {
        $this->buatProposal(TipeProposal::Internal);
        $p2 = $this->buatProposal(TipeProposal::Eksternal);

        $this->assertSame(TipeProposal::Eksternal, $p2->tipe_proposal);
        $this->assertStringStartsWith('02', $p2->kode);
        $this->assertStringEndsWith('0002', $p2->kode);
    }

    public function test_kode_memuat_tahun_dan_bulan_terbit(): void
    {
        $this->travelTo(Carbon::create(2027, 3, 15));

        $p = $this->buatProposal();

        $this->assertSame('0127030001', $p->kode);
        $this->assertSame(2027, $p->tahun);
        $this->assertSame(3, $p->bulan);
    }

    public function test_nomor_urut_kembali_ke_satu_di_tahun_berikutnya(): void
    {
        $this->travelTo(Carbon::create(2027, 12, 20));
        $this->buatProposal();

        $this->travelTo(Carbon::create(2028, 1, 5));
        $p = $this->buatProposal();

        $this->assertSame(1, $p->nomor);
        $this->assertSame('0128010001', $p->kode);
    }

    public function test_happy_path_penuh_sampai_selesai(): void
    {
        $p = $this->buatProposal();

        $jalur = [
            S::MenungguPresentasi,
            S::MenungguKelengkapanBerkasEtik,
            S::MenungguPenunjukanReviewer,
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
            $this->wf->transition($p, $ke);
        }

        $this->assertSame(S::Selesai, $p->fresh()->status);
        // Status Selesai TIDAK dengan sendirinya berarti survey terisi: gate-nya
        // adalah keberadaan baris respon, bukan penanda yang ikut status.
        $this->assertFalse($p->fresh()->sudahIsiSurvey());
        $this->assertNull($p->fresh()->unit_sekarang);
        // 1 pengajuan + 11 transisi
        $this->assertSame(12, $p->statusHistory()->count());
    }

    public function test_loop_revisi_reviewer_bisa_lebih_dari_sekali(): void
    {
        $p = $this->buatProposal();
        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik, S::MenungguPenunjukanReviewer, S::MenungguReviewReviewer] as $ke) {
            $this->wf->transition($p, $ke);
        }

        // Dua ronde revisi
        foreach (range(1, 2) as $i) {
            $this->wf->transition($p, S::PerluRevisiReviewer);
            $this->assertSame(Unit::KajiEtik, $p->unit_sekarang); // KEPK memantau loop revisi
            $this->wf->transition($p, S::MenungguReviewReviewer);
        }

        $this->wf->transition($p, S::DisetujuiReviewer);
        $this->assertSame(Unit::KajiEtik, $p->fresh()->unit_sekarang);
    }

    public function test_loloskan_ke_kepk_setelah_verifikasi_revisi(): void
    {
        $p = $this->buatProposal();

        // Presentasi → CRU minta revisi → peneliti kirim revisi
        foreach ([S::MenungguPresentasi, S::PerluRevisiProposal, S::MenungguVerifikasiRevisi] as $ke) {
            $this->wf->transition($p, $ke);
        }

        // Revisi diterima → langsung ke KEPK, tanpa presentasi kedua
        $this->wf->transition($p, S::MenungguKelengkapanBerkasEtik, 'Revisi diterima');

        $this->assertSame(S::MenungguKelengkapanBerkasEtik, $p->fresh()->status);
        $this->assertSame(Unit::KajiEtik, $p->fresh()->unit_sekarang);
    }

    public function test_transisi_loncat_ditolak_403(): void
    {
        $p = $this->buatProposal();

        $this->expectException(HttpException::class);
        $this->wf->transition($p, S::MenungguPembayaran); // loncat T1 → T3
    }

    public function test_transisi_mundur_d4_pembayaran_dan_laporan(): void
    {
        $p = $this->buatProposal();
        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik, S::MenungguPenunjukanReviewer, S::MenungguReviewReviewer,
            S::DisetujuiReviewer, S::MenungguPembayaran, S::MenungguVerifikasiPembayaran] as $ke) {
            $this->wf->transition($p, $ke);
        }

        // Bukti bayar ditolak → kembali
        $this->wf->transition($p, S::MenungguPembayaran, 'Bukti tidak sah');
        $this->assertSame(S::MenungguPembayaran, $p->fresh()->status);

        foreach ([S::MenungguVerifikasiPembayaran, S::PelaksanaanPenelitian, S::MenungguVerifikasiAkhir] as $ke) {
            $this->wf->transition($p, $ke);
        }

        // Laporan ditolak → kembali pelaksanaan
        $this->wf->transition($p, S::PelaksanaanPenelitian, 'Laporan kurang');
        $this->assertSame(S::PelaksanaanPenelitian, $p->fresh()->status);
    }

    public function test_dibatalkan_dari_non_terminal_dan_terminal_terkunci(): void
    {
        $p = $this->buatProposal();
        $this->wf->transition($p, S::Dibatalkan);
        $this->assertSame(S::Dibatalkan, $p->fresh()->status);

        $this->expectException(HttpException::class);
        $this->wf->transition($p, S::MenungguPresentasi); // terminal → apa pun ditolak
    }

    public function test_tolak_kaji_etik_dari_kelengkapan_berkas(): void
    {
        $p = $this->buatProposal();
        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik] as $ke) {
            $this->wf->transition($p, $ke);
        }

        $this->wf->transition($p, S::DitolakKajiEtik, 'Tidak layak etik');
        $this->assertTrue($p->fresh()->status->isTerminal());
    }

    public function test_versi_dokumen_bertambah_per_jenis(): void
    {
        Storage::fake('dokumen');
        $p = $this->buatProposal();
        $f = UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf');

        $d1 = $this->wf->simpanDokumen($p, DocumentType::Proposal, $f);
        $d2 = $this->wf->simpanDokumen($p, DocumentType::Proposal, $f);

        $this->assertSame(1, $d1->versi);
        $this->assertSame(2, $d2->versi);
    }
}

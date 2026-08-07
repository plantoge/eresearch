<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\ProposalStatus as S;
use App\Enums\Unit;
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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * KEPK memverifikasi kelengkapan berkas etik SEBELUM menunjuk reviewer.
 *
 * Sebelum ada jalur ini, satu berkas salah memaksa KEPK memilih antara
 * meneruskan berkas cacat ke reviewer atau menolak etik secara permanen.
 */
class VerifikasiBerkasEtikTest extends TestCase
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

    /** Proposal yang berkas etiknya sudah masuk dan menunggu KEPK. */
    protected function proposalMenungguKepk(): Proposal
    {
        $this->actingAs($this->peneliti);

        $p = $this->wf->ajukan([
            'peneliti_utama' => 'Dr. Uji',
            'judul_penelitian' => 'Penelitian Uji',
            'user_id' => $this->peneliti->id,
        ]);

        foreach ([S::MenungguPresentasi, S::MenungguKelengkapanBerkasEtik, S::MenungguPenunjukanReviewer] as $ke) {
            $this->wf->transition($p, $ke);
        }

        return $p;
    }

    protected function petugasKepk(): User
    {
        Menu::firstOrCreate(['slug' => 'kaji-etik'], ['nama' => 'Kaji Etik', 'route' => 'antrian.kepk']);
        Role::findByName('kepk')->givePermissionTo(['kaji-etik.read', 'kaji-etik.update']);

        $kepk = User::factory()->create();
        $kepk->assignRole('kepk');

        return $kepk;
    }

    // ===== Peta transisi =====

    public function test_peta_transisi_verifikasi_berkas_etik(): void
    {
        $this->assertTrue(S::MenungguPenunjukanReviewer->canGoTo(S::PerluRevisiBerkasEtik));
        $this->assertTrue(S::PerluRevisiBerkasEtik->canGoTo(S::MenungguPenunjukanReviewer));

        // Hanya bisa maju — penolakan etik diambil setelah berkas perbaikan masuk.
        $this->assertFalse(S::PerluRevisiBerkasEtik->canGoTo(S::MenungguReviewReviewer));
        $this->assertFalse(S::PerluRevisiBerkasEtik->canGoTo(S::DitolakKajiEtik));

        // Pembatalan tetap tersedia dari status non-terminal mana pun.
        $this->assertTrue(S::PerluRevisiBerkasEtik->canGoTo(S::Dibatalkan));

        $this->assertSame(2, S::PerluRevisiBerkasEtik->tahapan());
        $this->assertSame(Unit::KajiEtik, S::PerluRevisiBerkasEtik->unit());
        $this->assertTrue(S::PerluRevisiBerkasEtik->bolaDiPeneliti());
    }

    public function test_bolak_balik_verifikasi_bisa_lebih_dari_sekali(): void
    {
        $p = $this->proposalMenungguKepk();

        foreach (range(1, 2) as $i) {
            $this->wf->transition($p, S::PerluRevisiBerkasEtik, "Putaran {$i}: informed consent belum ditandatangani");
            $this->assertSame(Unit::KajiEtik, $p->fresh()->unit_sekarang);
            $this->assertTrue($p->fresh()->status->bolaDiPeneliti());

            $this->wf->transition($p, S::MenungguPenunjukanReviewer);
        }

        $this->wf->transition($p, S::MenungguReviewReviewer);
        $this->assertSame(S::MenungguReviewReviewer, $p->fresh()->status);
    }

    public function test_berkas_yang_dikembalikan_tidak_bisa_loncat_ke_reviewer(): void
    {
        $p = $this->proposalMenungguKepk();
        $this->wf->transition($p, S::PerluRevisiBerkasEtik, 'Berkas kurang');

        $this->expectException(HttpException::class);
        $this->wf->transition($p, S::MenungguReviewReviewer);
    }

    // ===== Aksi lewat komponen =====

    public function test_kepk_mengembalikan_berkas_dengan_catatan(): void
    {
        $p = $this->proposalMenungguKepk();

        Livewire::actingAs($this->petugasKepk())
            ->test(Show::class, ['proposal' => $p])
            ->set('catatan', 'PKS belum ditandatangani direktur')
            ->call('kepkMintaRevisiBerkas')
            ->assertHasNoErrors();

        $this->assertSame(S::PerluRevisiBerkasEtik, $p->fresh()->status);

        // Dicari lewat to_status, bukan urutan waktu: relasi statusHistory() sudah
        // ber-orderBy('created_at') dan beberapa baris bisa lahir di detik yang sama.
        $this->assertSame(
            'PKS belum ditandatangani direktur',
            $p->statusHistory()->where('to_status', S::PerluRevisiBerkasEtik->value)->first()->catatan,
        );
    }

    /** Catatan wajib: peneliti harus tahu berkas mana yang salah. */
    public function test_kepk_tidak_bisa_mengembalikan_tanpa_catatan(): void
    {
        $p = $this->proposalMenungguKepk();

        Livewire::actingAs($this->petugasKepk())
            ->test(Show::class, ['proposal' => $p])
            ->call('kepkMintaRevisiBerkas')
            ->assertHasErrors('catatan');

        $this->assertSame(S::MenungguPenunjukanReviewer, $p->fresh()->status);
    }

    public function test_peneliti_unggah_ulang_satu_berkas_lalu_kembali_ke_kepk(): void
    {
        $p = $this->proposalMenungguKepk();
        $this->wf->transition($p, S::PerluRevisiBerkasEtik, 'Informed consent belum ditandatangani');

        Livewire::actingAs($this->peneliti)
            ->test(Show::class, ['proposal' => $p])
            ->set('fileEtik.'.DocumentType::InformedConsent->value, UploadedFile::fake()->create('informed-consent.pdf', 100, 'application/pdf'))
            ->call('kirimPerbaikanBerkasEtik')
            ->assertHasNoErrors();

        $this->assertSame(S::MenungguPenunjukanReviewer, $p->fresh()->status);
        $this->assertSame(1, $p->documents()->where('jenis', DocumentType::InformedConsent->value)->count());
    }

    /**
     * Tim KEPK menelaah proposalnya juga, bukan hanya berkas etik — jadi koreksi
     * yang menyangkut proposal harus bisa dijawab tanpa menyentuh berkas etik.
     */
    public function test_peneliti_boleh_unggah_ulang_proposal_saja(): void
    {
        $p = $this->proposalMenungguKepk();
        $this->wf->transition($p, S::PerluRevisiBerkasEtik, 'Metodologi di proposal perlu diperjelas');

        Livewire::actingAs($this->peneliti)
            ->test(Show::class, ['proposal' => $p])
            ->set('fileProposal', UploadedFile::fake()->create('proposal-revisi.pdf', 100, 'application/pdf'))
            ->call('kirimPerbaikanBerkasEtik')
            ->assertHasNoErrors();

        $this->assertSame(S::MenungguPenunjukanReviewer, $p->fresh()->status);
        $this->assertSame(1, $p->documents()->where('jenis', DocumentType::Proposal->value)->count());
    }

    public function test_peneliti_boleh_unggah_proposal_dan_berkas_etik_sekaligus(): void
    {
        $p = $this->proposalMenungguKepk();
        $this->wf->transition($p, S::PerluRevisiBerkasEtik, 'Proposal dan informed consent perlu diperbaiki');

        Livewire::actingAs($this->peneliti)
            ->test(Show::class, ['proposal' => $p])
            ->set('fileProposal', UploadedFile::fake()->create('proposal-revisi.pdf', 100, 'application/pdf'))
            ->set('fileEtik.'.DocumentType::InformedConsent->value, UploadedFile::fake()->create('informed-consent.pdf', 100, 'application/pdf'))
            ->call('kirimPerbaikanBerkasEtik')
            ->assertHasNoErrors();

        $this->assertSame(S::MenungguPenunjukanReviewer, $p->fresh()->status);
        $this->assertSame(1, $p->documents()->where('jenis', DocumentType::Proposal->value)->count());
        $this->assertSame(1, $p->documents()->where('jenis', DocumentType::InformedConsent->value)->count());
    }

    public function test_peneliti_harus_unggah_minimal_satu_berkas(): void
    {
        $p = $this->proposalMenungguKepk();
        $this->wf->transition($p, S::PerluRevisiBerkasEtik, 'Informed consent belum ditandatangani');

        Livewire::actingAs($this->peneliti)
            ->test(Show::class, ['proposal' => $p])
            ->call('kirimPerbaikanBerkasEtik')
            ->assertHasErrors('fileEtik');

        $this->assertSame(S::PerluRevisiBerkasEtik, $p->fresh()->status);
    }

    /**
     * Perbaikan kelengkapan TIDAK me-reset penugasan reviewer — di titik ini KEPK
     * belum menunjuk siapa pun, jadi reset hanya akan menyentuh data yang tak ada.
     */
    public function test_perbaikan_kelengkapan_tidak_menyentuh_penugasan_reviewer(): void
    {
        $p = $this->proposalMenungguKepk();
        $this->wf->transition($p, S::PerluRevisiBerkasEtik, 'Informed consent belum ditandatangani');

        Livewire::actingAs($this->peneliti)
            ->test(Show::class, ['proposal' => $p])
            ->set('fileEtik.'.DocumentType::InformedConsent->value, UploadedFile::fake()->create('informed-consent.pdf', 100, 'application/pdf'))
            ->call('kirimPerbaikanBerkasEtik');

        $this->assertSame(0, $p->penugasanReviewer()->count());
    }

    public function test_peneliti_tidak_bisa_mengembalikan_berkasnya_sendiri(): void
    {
        $p = $this->proposalMenungguKepk();

        Livewire::actingAs($this->peneliti)
            ->test(Show::class, ['proposal' => $p])
            ->set('catatan', 'coba-coba')
            ->call('kepkMintaRevisiBerkas')
            ->assertForbidden();

        $this->assertSame(S::MenungguPenunjukanReviewer, $p->fresh()->status);
    }
}

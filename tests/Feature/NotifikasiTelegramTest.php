<?php

namespace Tests\Feature;

use App\Enums\ProposalStatus as S;
use App\Enums\TipeProposal;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Notifikasi Telegram: hanya aksi peneliti, dan isinya cuma "bel".
 *
 * Dua hal yang dijaga tes ini sengaja bukan sekadar niat di dokumen:
 * kerahasiaan isi pesan (§ tanpa judul/nama), dan jaminan bahwa Telegram
 * mati tidak boleh menjatuhkan aksi peneliti.
 */
class NotifikasiTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected ProposalWorkflow $wf;

    protected User $peneliti;

    protected User $cru;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->wf = app(ProposalWorkflow::class);

        $this->peneliti = User::factory()->create();
        $this->peneliti->assignRole('peneliti');

        $this->cru = User::factory()->create();
        $this->cru->assignRole('cru');

        $this->aktifkanTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    protected function aktifkanTelegram(bool $aktif = true): void
    {
        Config::set('eproposal.telegram.aktif', $aktif);
        Config::set('eproposal.telegram.bot_token', '123:token-uji');
        Config::set('eproposal.telegram.chat_id', '-100123456');
    }

    /** Proposal milik peneliti yang sedang menunggu revisi dikirim balik. */
    protected function proposalPerluRevisi(): Proposal
    {
        $this->actingAs($this->peneliti);

        $p = $this->wf->ajukan([
            'peneliti_utama' => 'Dr. Uji',
            'judul_penelitian' => 'Penelitian Rahasia Uji',
            'user_id' => $this->peneliti->id,
        ], TipeProposal::Internal);

        $this->actingAs($this->cru);
        $this->wf->transition($p, S::PerluRevisiProposal, 'Berkas kurang');

        return $p->refresh();
    }

    public function test_aksi_peneliti_mengirim_notifikasi(): void
    {
        $p = $this->proposalPerluRevisi();

        $this->actingAs($this->peneliti);
        $this->wf->transition($p, S::MenungguVerifikasiRevisi);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.telegram.org')
            && str_contains($r['text'], $p->kode)
            && str_contains($r['text'], S::MenungguVerifikasiRevisi->value));
    }

    public function test_aksi_staf_tidak_mengirim_notifikasi(): void
    {
        $this->actingAs($this->peneliti);
        $p = $this->wf->ajukan([
            'peneliti_utama' => 'Dr. Uji',
            'judul_penelitian' => 'Penelitian Uji',
            'user_id' => $this->peneliti->id,
        ], TipeProposal::Internal);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        // CRU meminta revisi — aktornya petugas, bukan pemilik proposal.
        $this->actingAs($this->cru);
        $this->wf->transition($p, S::PerluRevisiProposal, 'Berkas kurang');

        Http::assertNothingSent();
    }

    public function test_toggle_mati_tidak_menyentuh_jaringan(): void
    {
        $p = $this->proposalPerluRevisi();

        $this->aktifkanTelegram(false);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->actingAs($this->peneliti);
        $this->wf->transition($p, S::MenungguVerifikasiRevisi);

        Http::assertNothingSent();
    }

    public function test_pesan_tidak_membocorkan_judul_dan_nama_peneliti(): void
    {
        $p = $this->proposalPerluRevisi();

        $this->actingAs($this->peneliti);
        $this->wf->transition($p, S::MenungguVerifikasiRevisi);

        Http::assertSent(function (Request $r) use ($p) {
            $teks = $r['text'];

            return str_contains($teks, $p->kode)
                && ! str_contains($teks, $p->judul_penelitian)
                && ! str_contains($teks, $p->peneliti_utama);
        });
    }

    public function test_telegram_gagal_tidak_menjatuhkan_aksi_peneliti(): void
    {
        $p = $this->proposalPerluRevisi();

        Http::fake(['api.telegram.org/*' => Http::response(['description' => 'boom'], 500)]);

        $this->actingAs($this->peneliti);
        $this->wf->transition($p, S::MenungguVerifikasiRevisi);

        // Statusnya tetap pindah dan tercatat, walau notifikasinya gagal.
        $this->assertSame(S::MenungguVerifikasiRevisi, $p->refresh()->status);
        $this->assertDatabaseHas('rspi.proposal_status_history', [
            'proposal_id' => $p->id,
            'to_status' => S::MenungguVerifikasiRevisi->value,
        ]);
    }
}

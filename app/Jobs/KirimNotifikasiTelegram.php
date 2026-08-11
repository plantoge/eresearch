<?php

namespace App\Jobs;

use App\Models\ProposalStatusHistory;
use App\Services\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Susun & kirim satu notifikasi perpindahan status ke grup Telegram.
 *
 * Lewat antrian supaya peneliti tidak ikut menunggu Telegram membalas —
 * kalau Telegram lambat, yang melambat job-nya, bukan halaman peneliti.
 */
class KirimNotifikasiTelegram implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Jeda antar percobaan (detik) — gangguan sesaat biasanya pulih di percobaan kedua. */
    public array $backoff = [10, 60];

    public function __construct(public ProposalStatusHistory $history) {}

    public function handle(TelegramNotifier $telegram): void
    {
        $proposal = $this->history->proposal;

        if (! $proposal) {
            return;
        }

        $telegram->kirim($this->susunPesan());
    }

    /**
     * Bel, bukan isi: cuma kode, status, unit, dan link.
     *
     * Judul penelitian, nama peneliti, dan catatan sengaja TIDAK ikut —
     * isi grup Telegram terlihat semua anggotanya, permanen, bisa
     * di-forward keluar, dan servernya di luar jaringan RS. Yang butuh
     * detail mengklik linknya, dan di sana izin ditegakkan seperti biasa.
     */
    protected function susunPesan(): string
    {
        $proposal = $this->history->proposal;

        $baris = [
            '🔔 <b>'.e($proposal->kode).'</b>',
            e($this->history->to_status->value),
        ];

        // Status terminal (Selesai/Dibatalkan) tidak punya unit — barisnya dilewati.
        if ($unit = $this->history->unit?->label()) {
            $baris[] = 'Untuk: '.e($unit);
        }

        $baris[] = route('proposal.show', $proposal);

        return implode("\n", $baris);
    }
}

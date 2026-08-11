<?php

namespace App\Observers;

use App\Jobs\KirimNotifikasiTelegram;
use App\Models\ProposalStatusHistory;

/**
 * Titik sisip TUNGGAL untuk notifikasi Telegram.
 *
 * Semua aksi peneliti (prd §5) melewati ProposalWorkflow dan bermuara di
 * catatHistory(), jadi memasang satu observer di sini menutup semuanya —
 * termasuk alur yang ditambahkan nanti. Menyisipkan panggilan di tiap
 * komponen justru membuat notifikasi bolong diam-diam begitu ada satu
 * tempat yang terlupa.
 *
 * Hanya created(): baris history append-only, tidak pernah di-update
 * atau dihapus dari UI.
 */
class ProposalStatusHistoryObserver
{
    public function created(ProposalStatusHistory $history): void
    {
        $proposal = $history->proposal;

        // Yang memindahkan status = pemilik proposal, berarti peneliti.
        // Aksi staf (CRU/KEPK/reviewer) sengaja tidak dikirim: isinya staf
        // memberi tahu staf lain hal yang baru saja mereka lakukan sendiri.
        // actor_id null (seeder/artisan tanpa login) ikut tersaring di sini.
        if (! $proposal || $history->actor_id !== $proposal->user_id) {
            return;
        }

        // afterCommit() wajib eksplisit: config/queue.php menyetel
        // 'after_commit' => false, sedangkan transition() jalan di dalam
        // DB::transaction — tanpa ini job bisa jalan sebelum commit dan
        // membaca proposal yang belum ada.
        KirimNotifikasiTelegram::dispatch($history)->afterCommit();
    }
}

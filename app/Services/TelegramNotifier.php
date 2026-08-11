<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim pesan ke satu grup Telegram lewat Bot API — cukup satu POST,
 * tanpa paket tambahan.
 *
 * Aturan yang tidak boleh dilanggar: kegagalan Telegram TIDAK PERNAH
 * boleh menjatuhkan aksi peneliti. Semua error ditelan jadi log dan
 * method ini balik false; pemanggilnya (job) yang urus retry.
 */
class TelegramNotifier
{
    protected const ENDPOINT = 'https://api.telegram.org/bot%s/sendMessage';

    /** Terkirim atau tidak. False juga berarti "sengaja tidak dikirim" (fitur mati). */
    public function kirim(string $pesan): bool
    {
        $token = config('eproposal.telegram.bot_token');
        $chatId = config('eproposal.telegram.chat_id');

        // Fitur mati atau belum dikonfigurasi: pulang sebelum menyentuh jaringan.
        if (! config('eproposal.telegram.aktif') || ! $token || ! $chatId) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('eproposal.telegram.timeout', 5))
                ->post(sprintf(self::ENDPOINT, $token), [
                    'chat_id' => $chatId,
                    'text' => $pesan,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if ($response->successful()) {
                return true;
            }

            // Telegram menaruh sebab sebenarnya di 'description' — tanpa itu
            // yang tercatat cuma "400 Bad Request" yang tidak menolong siapa pun.
            Log::warning('Notifikasi Telegram ditolak', [
                'status' => $response->status(),
                'description' => $response->json('description'),
            ]);
        } catch (Throwable $e) {
            Log::warning('Notifikasi Telegram gagal terkirim', ['pesan' => $e->getMessage()]);
        }

        return false;
    }
}

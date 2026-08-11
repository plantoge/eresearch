# Spec — Notifikasi Telegram ke Grup Staf

> Status: disetujui, belum diimplementasi · Tanggal: 11 Agustus 2026
> Rujukan: [prd.md §5](../../docs/prd.md) (aksi peneliti) ·
> [arsitektur.md](../../docs/arsitektur.md) (queue, `ProposalWorkflow`) ·
> [rules.md](../../docs/rules.md) (larangan `env()` di `app/`, batasan dependency)

---

## 1. Masalah

Staf CRU dan KEPK tidak tahu ada pekerjaan masuk sampai mereka membuka aplikasi dan
mengecek antrian sendiri. Tidak ada notifikasi apa pun di sistem — email hanya dipakai
untuk verifikasi akun dan reset password.

Akibatnya proposal bisa menganggur berhari-hari di antrian bukan karena stafnya menolak
mengerjakan, tapi karena tidak ada yang memberitahu bahwa ada yang perlu dikerjakan.

## 2. Yang dibangun

Setiap kali **peneliti** melakukan aksi yang memindahkan status proposal, sebuah pesan
singkat masuk ke satu grup Telegram berisi staf CRU/KEPK.

Bukan yang dibangun: notifikasi per-orang, bot dua arah, OTP Telegram, notifikasi untuk
aksi staf. Semuanya bisa menyusul tanpa membongkar pekerjaan ini.

## 3. Keputusan yang sudah diambil

### K1 — Satu titik sisip, bukan delapan

Delapan aksi peneliti di [prd.md §5](../../docs/prd.md) semuanya melewati
`ProposalWorkflow` (`ajukan()` untuk pengajuan pertama, `transition()` untuk sisanya),
dan keduanya bermuara di `catatHistory()` yang menulis satu baris
`proposal_status_history`.

Karena itu titik sisipnya **satu**: observer pada model `ProposalStatusHistory`.

Menyisipkan panggilan di delapan tempat ditolak: sekali lupa satu, notifikasi bolong
diam-diam, dan setiap alur baru harus diingat untuk disisipi lagi. Dengan satu titik,
alur baru otomatis ikut terkirim.

Observer dipilih ketimbang mengubah `ProposalWorkflow` supaya urusan "mencatat sejarah"
dan "mengirim notifikasi" tidak bercampur, dan `ProposalWorkflow` tidak disentuh sama
sekali. Polanya sudah ada di aplikasi ini (`MenuObserver`).

### K2 — Pesan adalah bel, bukan isi

Grup Telegram berada **di luar kendali izin aplikasi**: apa pun yang masuk terlihat semua
anggota grup, permanen, dan bisa di-forward keluar. Server Telegram juga di luar jaringan
rumah sakit — persis alasan MinIO/S3 cloud ditolak untuk penyimpanan dokumen
([arsitektur.md §4](../../docs/arsitektur.md)).

Maka pesan hanya memuat **kode proposal, status tujuan, unit, dan link**. Tidak ada judul
penelitian, nama peneliti, isi catatan, atau nama berkas. Yang butuh detail mengklik link
dan tetap melewati login serta pemeriksaan izin seperti biasa.

### K3 — Hanya aksi peneliti

Saringannya: `history.actor_id === proposal.user_id`. Yang memindahkan status adalah
pemilik proposal, berarti peneliti. Tepat menutup delapan aksi, tidak lebih.

Aksi staf (CRU minta revisi, KEPK tunjuk reviewer, reviewer merespons) tidak dikirim —
isinya staf memberitahu staf lain hal yang baru saja mereka lakukan sendiri.

### K4 — Lewat queue job, tapi bisa dibalik lewat env

Mengirim langsung dari observer membuat peneliti menunggu Telegram membalas; kalau
Telegram lambat, halaman menggantung karena urusan yang bukan urusannya.

Job juga memberi retry: Telegram ngadat sebentar → dicoba ulang; menyerah → masuk
`failed_jobs`, bisa dilihat dan di-`queue:retry`.

Queue worker **bukan syarat baru** — email verifikasi sudah lewat antrian dan worker
sudah wajib hidup di produksi. Kalau ternyata worker bermasalah, `QUEUE_CONNECTION=sync`
membuat job jalan inline tanpa worker, tanpa mengubah satu baris kode.

Laravel Notification ditolak: ia dirancang mengirim ke sebuah model (`$user->notify()`),
sementara tujuan kita satu grup. Memaksakannya berarti membuat channel custom + notifiable
palsu — mesin lebih banyak, hasil sama.

### K5 — Mati secara default, dan tidak pernah menjatuhkan aksi peneliti

Toggle default `false`. Selama token belum diisi, aplikasi berjalan persis seperti
sekarang dan tidak ada satu pun panggilan HTTP.

Kegagalan Telegram dalam bentuk apa pun tidak boleh membuat pengajuan proposal gagal.
Semua error ditelan menjadi log.

### K6 — Nol migration, nol dependency

Tidak ada tabel atau kolom baru — hormati aturan database baca-saja
([rules.md §5](../../docs/rules.md)). Tidak ada paket baru: mengirim pesan Telegram cukup
satu `Http::post()` bawaan Laravel, sesuai batasan
[rules.md §6](../../docs/rules.md).

---

## 4. Rancangan

### 4.1 Alur

```
Peneliti klik aksi (mis. "Kirim Revisi")
      ↓
ProposalWorkflow::transition()      ← tidak diubah sama sekali
      ↓
catatHistory() → baris baru proposal_status_history
      ↓
ProposalStatusHistoryObserver::created()
      ↓  (saring: actor_id === proposal.user_id)
KirimNotifikasiTelegram::dispatch($history)->afterCommit()
      ↓
TelegramNotifier::kirim($pesan) → Http::post() → grup Telegram
```

`afterCommit()` wajib: `transition()` berjalan di dalam `DB::transaction`, dan tanpa itu
job bisa dieksekusi sebelum transaksi commit sehingga membaca data yang belum ada.

### 4.2 Berkas

| Berkas | Isi |
|---|---|
| `config/eproposal.php` | *(diubah)* blok `telegram` |
| `app/Services/TelegramNotifier.php` | *(baru)* `kirim(string $pesan): bool` |
| `app/Jobs/KirimNotifikasiTelegram.php` | *(baru)* menyusun pesan, memanggil notifier |
| `app/Observers/ProposalStatusHistoryObserver.php` | *(baru)* `created()` + saringan |
| `app/Providers/AppServiceProvider.php` | *(diubah)* daftarkan observer |
| `tests/Feature/NotifikasiTelegramTest.php` | *(baru)* |

### 4.3 Konfigurasi

`.env`:

```
TELEGRAM_NOTIFIKASI_AKTIF=false
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

`config/eproposal.php`:

```php
'telegram' => [
    'aktif'     => env('TELEGRAM_NOTIFIKASI_AKTIF', false),
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id'   => env('TELEGRAM_CHAT_ID'),
    'timeout'   => env('TELEGRAM_TIMEOUT', 5),
],
```

Dibaca lewat `config('eproposal.telegram.*')`. Memanggil `env()` di `app/` dilarang —
`config:cache` di produksi mengembalikannya `null`.

**Setup sekali (oleh admin):** chat `@BotFather` → `/newbot` → salin token → buat grup →
masukkan bot sebagai anggota → ambil `chat_id` grup. `artisan config:clear` setelah
`.env` diubah.

### 4.4 `TelegramNotifier`

```php
public function kirim(string $pesan): bool
```

- Kembalikan `false` seketika bila `aktif` false, atau `bot_token`/`chat_id` kosong —
  tanpa memanggil HTTP sama sekali.
- `Http::timeout(...)->post("https://api.telegram.org/bot{$token}/sendMessage", [...])`
  dengan `chat_id`, `text`, `parse_mode => 'HTML'`,
  `disable_web_page_preview => true`.
- **Tidak pernah melempar exception.** Kegagalan koneksi maupun respons non-2xx
  ditangkap dan dicatat `Log::warning` beserta `description` dari Telegram, lalu
  kembalikan `false`.

### 4.5 Isi pesan

Disusun di job dari `ProposalStatusHistory` + `Proposal`:

```
🔔 <b>RSPISS-2026-014</b>
Menunggu Verifikasi Revisi
Bola di: CRU / Penelitian
https://host/proposal/019xxxx-xxxx
```

Sumber tiap baris:

| Baris | Dari |
|---|---|
| kode | `proposal.kode` |
| status | `history.to_status` (nilai enum `ProposalStatus`, sudah berbahasa Indonesia) |
| unit | `history.unit` → `Unit::label()` (sudah ada); baris dilewati bila `null` (status terminal) |
| link | `route('proposal.show', $proposal)` — butuh `APP_URL` benar |

Tidak ada tabel pemetaan teks baru. Status dan unit diambil dari enum yang sudah ada,
sehingga teks di Telegram tidak bisa melenceng dari status sebenarnya.

### 4.6 Kasus tepi saringan

Saringan `history.actor_id === proposal.user_id` sudah menutup semuanya, tapi perilakunya
perlu eksplisit supaya tidak dikira bug saat ditemukan nanti:

| Kejadian | `actor_id` | Terkirim? | Benar? |
|---|---|---|---|
| Peneliti mengajukan proposal (`ajukan()`) | = `user_id` | ya | ya — ini aksi peneliti pertama |
| CRU minta revisi / tolak / loloskan | petugas CRU | tidak | ya — aksi staf |
| KEPK tunjuk reviewer, teruskan revisi | petugas KEPK | tidak | ya |
| Transisi otomatis `Disetujui Reviewer` (semua reviewer ACC) | reviewer terakhir | tidak | ya — dipicu aksi reviewer, bukan peneliti |
| Seeder / perintah artisan tanpa login | `null` | tidak | ya — `null !== user_id`, tidak perlu guard tambahan |

Observer hanya memasang `created()`. Baris history tidak pernah di-update atau dihapus
dari UI (konvensi append-only di `ProposalStatusHistory`), jadi tidak ada event lain yang
perlu ditangani.

Relasi yang dipakai job sudah tersedia: `ProposalStatusHistory::proposal()`. Kolom
`to_status` dan `unit` sudah di-cast ke enum `ProposalStatus` dan `Unit` di model, jadi
job tidak perlu mengubah string apa pun.

### 4.7 Perilaku saat gagal

| Kondisi | Akibat |
|---|---|
| `TELEGRAM_NOTIFIKASI_AKTIF=false` | tidak ada HTTP sama sekali |
| Token/chat_id kosong | tidak ada HTTP, satu baris log |
| Telegram down / timeout | job retry `tries=3`, `backoff=[10, 60]` detik → `failed_jobs` + log |
| Token salah / bot bukan anggota grup | respons non-2xx, dicatat log, tidak retry berguna tapi tidak merusak apa pun |
| Queue worker mati | notifikasi mengantre, terkirim saat worker hidup — tidak hilang |
| **Semua kondisi di atas** | **pengajuan/revisi peneliti tetap sukses** |

---

## 5. Pengujian

`tests/Feature/NotifikasiTelegramTest.php`, memakai `Http::fake()`:

1. Aksi peneliti (mis. `Perlu Revisi Proposal` → `Menunggu Verifikasi Revisi` oleh
   pemilik) → satu request terkirim ke endpoint Telegram.
2. Aksi staf (mis. CRU meminta revisi) → **tidak ada** request.
3. Toggle `aktif = false` → **tidak ada** request, walau aksinya aksi peneliti.
4. Isi pesan memuat `kode` proposal, dan **tidak memuat** `judul_penelitian` maupun
   `peneliti_utama` — menegakkan K2 sebagai tes, bukan sekadar niat.
5. Telegram membalas 500 → aksi peneliti tetap sukses, tidak ada exception yang bocor.

Suite berjalan di PostgreSQL (`cru_test`) seperti tes lain.

---

## 6. Kondisi selesai

- [ ] `artisan test` lulus, termasuk 5 tes baru di atas
- [ ] Dengan toggle `false`, tidak ada perubahan perilaku sama sekali
- [ ] Dengan toggle `true` + token asli, aksi peneliti memunculkan pesan di grup
- [ ] Pesan tidak memuat judul penelitian maupun nama peneliti
- [ ] `vendor/bin/pint` bersih
- [ ] `docs/arsitektur.md` bertambah keterangan konfigurasi Telegram
- [ ] `docs/task.md` diperbarui (fitur ini pindah ke "sudah dikerjakan")
- [ ] `graphify update .` dijalankan

## 7. Di luar cakupan

Notifikasi per-orang · bot dua arah / webhook · OTP Telegram (ditolak: bot tidak bisa
mengirim ke orang yang belum pernah chat duluan, dan menangkap `chat_id` butuh webhook
publik atau proses polling yang dilarang [rules.md §6](../../docs/rules.md)) · halaman
admin untuk mengisi token · notifikasi untuk aksi staf · WhatsApp/SMS.

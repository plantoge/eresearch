# Arsitektur & Infrastruktur — eProposal

> Proses bisnis → [prd.md](prd.md) · database → [skema.md](skema.md) ·
> tampilan → [design.md](design.md) · aturan kerja → [rules.md](rules.md).

---

## 1. Stack

| Aspek | Pilihan | Catatan |
|---|---|---|
| Framework | Laravel 12 (PHP ^8.2, dev pakai 8.4.12) | |
| UI | Livewire 3 + Mary UI 2.9 + daisyUI 5 + Tailwind 4 | Filament tidak dipakai (preferensi tim); Flux UI berbayar |
| Otorisasi | spatie/laravel-permission 8.3 | morph key uuid, `teams => false` |
| Database | PostgreSQL 14, **dua schema**: `public` (bawaan Laravel & spatie) + `rspi` (seluruh domain) | `search_path=public,rspi`; nama DB berbeda per lingkungan — lihat [task.md](task.md) |
| Primary key | UUIDv7 di semua tabel domain | |
| Email | Resend (`resend/resend-laravel` 1.4) | |
| Build front-end | Vite 7 (butuh Node ≥ 20.19) | |
| Queue | `database` | dipakai email; worker wajib jalan di produksi |
| Realtime | **tidak ada** | tanpa WebSocket, tanpa broadcasting |
| Notifikasi | Telegram Bot API | keluar saja (satu `Http::post()`), tanpa paket, tanpa daemon |

### Lingkungan pengembangan (Windows/Laragon)

PHP dan Composer **tidak ada di PATH** — tulis path lengkap:

```bash
PHP='/c/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe'
"$PHP" artisan migrate
"$PHP" /c/laragon/bin/composer/composer.phar install
```

---

## 2. Struktur Aplikasi

```
app/
├── Concerns/HasUuidAndAudit.php      uuid v7 + created_by/updated_by/deleted_by otomatis
├── Enums/                            ProposalStatus, DocumentType, Unit,
│                                     TujuanPembayaran, StatusPembayaran, JenisTelaah, KeputusanEtik
├── Http/
│   ├── Controllers/                  DocumentDownloadController, DokumenTelaahDownloadController,
│   │                                 Auth/VerifyEmailController
│   └── Middleware/                   EnsureEmailIsVerifiedIfRequired
├── Livewire/
│   ├── Admin/                        Users, Roles, Menus, Survey, Kontak
│   ├── Antrian/                      BaseAntrian + Cru, Kepk, Reviewer
│   ├── Auth/                         Login, Register, ForgotPassword, ResetPassword, VerifyEmailNotice
│   ├── Layout/Sidebar.php            menu dinamis ter-filter permission
│   ├── Proposal/                     Create, Index, Show
│   └── Dashboard, Laporan, AuditLog, Profile
├── Models/                           Proposal + turunannya; berkas kerja CRU (BerkasPenelitian,
│                                     Pembayaran, IzinPenelitian) & KEPK (ProtokolEtik,
│                                     PenugasanReviewer, TelaahReviewer, DokumenTelaah);
│                                     Menu, InformasiKontak, master survey
├── Observers/MenuObserver.php        → MenuPermissionSync
├── Rules/ValidCaptcha.php
└── Services/
    ├── ProposalWorkflow.php          SATU-SATUNYA pintu perubahan status
    ├── MenuPermissionSync.php        menu ⇄ permission spatie
    └── MathCaptcha.php
```

**`ProposalWorkflow` adalah inti aplikasi.** Semua aksi UI memanggilnya, tidak ada satu pun
komponen yang menyentuh `$proposal->status` langsung:

| Method | Tugas |
|---|---|
| `ajukan(array)` | buat proposal + kode + status awal + baris history pertama |
| `transition($proposal, $ke, $catatan)` | validasi `canGoTo()` → abort 403 bila loncat; set status + `unit_sekarang`; tulis history; semuanya dalam satu transaksi |
| `simpanDokumen($proposal, $jenis, $file)` | simpan ke disk `dokumen`, versi naik otomatis per jenis |
| `tugaskanReviewer($proposal, $ids)` | buat/aktifkan penugasan lalu transisi ke `Menunggu Review Reviewer` |
| `simpanDokumenTelaah($proposal, $file, $telaah)` | simpan berkas rahasia telaah ke `kepk_dokumen_telaah` — sengaja bukan lewat `simpanDokumen()` |
| `reviewerMerespons($proposal, $keputusan, ...)` | catat `kepk_telaah_reviewer` per ronde, update penugasan, auto-transisi bila semua ACC |
| `resetPenugasanReviewer($proposal)` | kembalikan semua penugasan ke `menunggu` (ronde baru) |
| `generateKode()` | `RSPISS-YYYY-###` dengan `pg_advisory_xact_lock` per tahun |

---

## 3. Routing & Otorisasi

`routes/web.php` — semua halaman adalah komponen Livewire full-page.

```php
Route::middleware(['auth', 'verified.optional'])->group(function () {
    Route::get('/proposal', Livewire\Proposal\Index::class)
        ->middleware('permission:proposal.read')->name('proposal.index');
    // ...
});
```

Tiga lapis penegakan:

1. **Route** — `middleware('permission:{slug}.{aksi}')`.
2. **Komponen** — `abort_unless(...)` di `mount()` untuk otorisasi berbasis kepemilikan/penugasan
   yang tidak bisa diungkapkan sebagai permission statis. Contoh `Proposal\Show::mount()`:
   pemilik proposal, **atau** petugas CRU/KEPK, **atau** reviewer yang ditugaskan pada proposal itu.
3. **Tampilan** — tombol aksi hanya dirender bila `can()` terpenuhi (lihat [design.md](design.md)).

`/proposal/{proposal}` sengaja **tanpa** middleware permission — otorisasinya per-baris di komponen.

### RBAC & menu dinamis

- 12 menu awal & matriks permission per role: `database/seeders/MenuSeeder.php`; 9 role:
  `RoleSeeder.php`. Kondisi terpasang: 48 permission.
- Superadmin mengubah menu dan hak akses lewat UI (`/admin/menus`, `/admin/roles`) — **tanpa
  ubah kode**. `MenuObserver` → `MenuPermissionSync` membuat/rename/hapus permission mengikuti slug.
- Sidebar (`app/Livewire/Layout/Sidebar.php`) hanya menampilkan menu aktif yang user punya
  `{slug}.read`, terurut `urutan`, hierarkis lewat `parent_id`.

---

## 4. Penyimpanan File

Dokumen **tidak** disimpan di dalam folder aplikasi. Disk `dokumen` (`config/filesystems.php`):

```php
'dokumen' => [
    'driver' => 'local',
    'root' => env('DOCUMENTS_PATH', storage_path('app/dokumen')),
    'serve' => false,       // privat: tidak ada URL publik
    'throw' => false,
],
```

- `DOCUMENTS_PATH` mis. `D:\eproposal-files` (dev & server Windows), `/var/eproposal-files` (Linux).
- Struktur: `proposal/{proposal_id}/{jenis}/{namafile}`; kolom `path` di DB berisi path **relatif**.
- **Dua pintu akses, keduanya ber-otorisasi.** `DocumentDownloadController` untuk berkas umum —
  memeriksa kepemilikan/peran dan gate survey untuk `izin_final`.
  `DokumenTelaahDownloadController` untuk berkas telaah reviewer — hanya petugas unit, peneliti
  tidak punya cabang izin sama sekali. Tidak ada `storage:link` untuk disk ini.
- Pindah ke MinIO/S3 kelak = cukup ganti definisi disk; kode & isi DB tidak berubah.
  (MinIO/SFTP/S3 cloud sudah dipertimbangkan dan ditolak: data penelitian RS sebaiknya tidak
  keluar jaringan, dan server tambahan menambah beban operasional.)

### Batas ukuran upload — tiga lapis, semua harus memadai

| Lapis | Nilai | Lokasi |
|---|---|---|
| `php.ini` | `upload_max_filesize` & `post_max_size` ≥ 30M | php.ini server (beri margin overhead multipart) |
| Temp upload Livewire | 25 MB (`max:25600`) | `config/livewire.php` → `temporary_file_upload.rules` |
| Validasi per jenis dokumen | pdf 10 MB · bukti bayar 5 MB · raw data 20 MB | `DocumentType::aturanValidasi()` |

Kalau lapis atas lebih kecil dari lapis bawah, file ditolak **sebelum** validasi jenis dokumen
sempat jalan — gejalanya error yang tidak menyebut ukuran sebenarnya.

### Migrasi file lama (bila server pernah menyimpan di lokasi lama)

```
robocopy storage\app\public\proposal D:\eproposal-files\proposal /E
```

Tidak ada langkah DB — `path` sudah relatif. Masukkan folder itu ke rutinitas backup server.

---

## 5. Autentikasi, Email & Captcha

### Login & registrasi
Livewire (`app/Livewire/Auth/`), tanpa 2FA. Reset password memakai alur bawaan Laravel
(`password_reset_tokens`), halaman `ForgotPassword` & `ResetPassword`.

### Captcha — math self-hosted
`app/Services/MathCaptcha.php` membuat soal hitung sederhana (dua angka 1–15, operator `+`/`-`).
**Jawaban benar hanya disimpan di session server**, tidak pernah dikirim ke client — menaruhnya
di public property Livewire akan membocorkannya lewat snapshot di HTML. Sekali pakai: key session
dihapus saat diverifikasi, jadi replay ditolak. Nol dependency eksternal, nol panggilan API,
tidak butuh ekstensi GD. Dipasang di `Login` & `Register` lewat `App\Rules\ValidCaptcha`.

*(Cloudflare Turnstile sempat dipasang lalu dibuang — dinilai terlalu rumit.)*

### Email lewat Resend

| Item | Nilai |
|---|---|
| Mailer | `MAIL_MAILER=resend` |
| API key | **`RESEND_API_KEY`** — bukan `RESEND_KEY`. Salah nama = email tidak terkirim tanpa error jelas (`config/services.php`) |
| Pengirim | `MAIL_FROM_ADDRESS` harus di domain yang **Verified** di Resend |
| Kuota gratis | 3.000 email/bulan |

Resend (seperti SendGrid/Mailgun/Postmark) menolak mengirim atas nama domain yang belum
dibuktikan kepemilikannya lewat DNS — anti-spoofing. Karena itu `MAIL_FROM_ADDRESS=...@gmail.com`
tidak bisa dipakai.

**Verifikasi domain (sekali saja):** di resend.com → *Domains* → tambah domain → Resend memberi
3 record DNS; masukkan ke DNS provider (Hostinger: hPanel → Domains → DNS Zone → Add Record):

| # | Type | Name | Content | Catatan |
|---|---|---|---|---|
| 1 | TXT | `resend._domainkey` | `p=MIGf...` | DKIM — salin **utuh**, sering terpotong `[...]` di tampilan |
| 2 | MX | `send` | `feedback-smtp.us-east-1.amazonses.com` | **Priority 10** |
| 3 | TXT | `send` | `v=spf1 include:amazonses.com ~all` | |

Jebakan: isi kolom Name **bagian depan saja** (`send`, bukan `send.domain.com` — provider
menambahkan domain sendiri sehingga jadi dobel); `_domainkey` memang literal dengan underscore;
TTL 3600. Setelah tersimpan → tombol *Verify* di Resend; propagasi biasanya < 1 jam.
*Enable Receiving* biarkan OFF (kita hanya mengirim). DMARC dianjurkan tapi tidak wajib.

### Toggle verifikasi email

`EMAIL_VERIFICATION_REQUIRED` (`.env` → `config/eproposal.php`). Default kode **`false`**;
**nilai terpasang sekarang `true`** dengan pengirim `simrs@suliantisarosohospital.com`.

| Nilai | Perilaku saat user daftar |
|---|---|
| `false` | akun langsung aktif (`email_verified_at` terisi), **tidak ada percobaan kirim email** — dipakai selama domain belum Verified |
| `true` | user menerima link verifikasi; akses ke-gate sampai diklik |

Toggle ini ada karena pernah terjadi: domain pengirim belum Verified → Resend menolak kirim →
proses registrasi ikut gagal. Kalau gejala itu muncul lagi, matikan toggle dulu
(`false` + `config:clear`) sambil membereskan domain.

Gating memakai middleware custom **`verified.optional`**
(`app/Http/Middleware/EnsureEmailIsVerifiedIfRequired.php`, alias di `bootstrap/app.php`) —
bukan middleware bawaan `verified` yang dipasang kondisional. Alasannya: keputusan dibaca saat
request, bukan dibekukan saat route didaftarkan, sehingga toggle langsung berlaku tanpa
`route:cache` ulang dan mudah diuji per skenario.

**Menyalakan:** pastikan domain Verified → set `MAIL_FROM_ADDRESS` ke domain itu →
`EMAIL_VERIFICATION_REQUIRED=true` → `artisan config:clear`.
Cek pengiriman di Resend → menu *Logs/Emails*. User demo dari seeder sudah `email_verified_at`
terisi, jadi tidak ke-gate; untuk menguji, daftar akun baru.

### Notifikasi Telegram ke grup staf

Tiap aksi peneliti yang memindahkan status proposal mengirim satu pesan singkat ke **satu
grup Telegram** berisi staf CRU/KEPK. Tidak ada paket baru — cukup satu `Http::post()` ke
Bot API, dan arahnya hanya keluar sehingga tidak butuh webhook maupun proses yang dijaga hidup.

| Item | Nilai |
|---|---|
| Toggle | `TELEGRAM_NOTIFIKASI_AKTIF` — **default `false`** |
| Kredensial | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` |
| Timeout | `TELEGRAM_TIMEOUT` (detik, default 5) |
| Config | `config/eproposal.php` → `eproposal.telegram.*` |

**Setup sekali:** chat `@BotFather` → `/newbot` → salin token → buat grup → masukkan bot
sebagai anggota → ambil `chat_id` grup (angkanya diawali `-100...`). Lalu
`artisan config:clear`.

Selama toggle `false` atau token/chat_id kosong, **tidak ada satu pun panggilan HTTP** —
aplikasi berjalan persis seperti sebelum fitur ini ada.

**Isi pesan sengaja minim:** kode proposal, status, unit, dan link. Judul penelitian, nama
peneliti, dan catatan **tidak pernah dikirim**. Alasannya sama dengan alasan MinIO/S3 cloud
ditolak di §4 — grup Telegram di luar kendali izin aplikasi (semua anggota melihat semuanya,
bisa di-forward keluar) dan servernya di luar jaringan RS. Yang butuh detail mengklik
linknya, dan di sana otorisasi ditegakkan seperti biasa. Aturan ini dikunci sebagai tes di
`NotifikasiTelegramTest`, bukan sekadar konvensi.

**Titik sisipnya satu**: observer `created()` pada `proposal_status_history`. Semua aksi
peneliti sudah bermuara di `ProposalWorkflow::catatHistory()`, jadi alur baru otomatis ikut
terkirim tanpa menambah kode — dan `ProposalWorkflow` sendiri tidak disentuh sama sekali.
Saringannya `actor_id === proposal.user_id`, sehingga aksi staf dan aksi tanpa login
(seeder) tersaring dengan sendirinya.

> **Notifikasi ikut queue, sama seperti email.** Gejalanya juga sama: kalau worker mati,
> tidak ada error tapi pesan tidak sampai — bedanya notifikasi tidak hilang, ia mengantre
> sampai worker hidup. Kalau worker bermasalah dan ingin dijalankan langsung tanpa antrian,
> `QUEUE_CONNECTION=sync` membuatnya jalan inline **tanpa mengubah kode**.
>
> Kegagalan Telegram dalam bentuk apa pun (token salah, bot bukan anggota grup, API down)
> dicatat di `laravel.log` dan `failed_jobs`, dan **tidak pernah** menjatuhkan pengajuan
> peneliti.

---

## 6. Deploy — FrankenPHP di Ubuntu Server

FrankenPHP = Caddy (Go) + PHP tertanam. Satu binary, satu service — menggantikan Nginx + PHP-FPM.
HTTPS otomatis, kompresi zstd/br, HTTP/2–3. **Tidak** menggantikan queue worker & scheduler (§6.4).

### 6.1 Pilih mode dulu

| | Mode klasik (`php_server`) | Mode worker (Octane) |
|---|---|---|
| Cara kerja | boot Laravel tiap request | boot sekali, tahan di memori |
| Performa | setara PHP-FPM | ±2–4× lebih cepat |
| Risiko | ~nol, drop-in | state bocor antar request, memory leak |
| Deploy | copy file | wajib `octane:reload` |

**Mulai dari mode klasik**, naikkan ke worker setelah stabil beberapa hari — supaya kalau ada
masalah jelas penyebabnya. Audit §6.5 menunjukkan kode ini sudah aman untuk worker mode.

### 6.2 Instalasi

```bash
VERSION=84   # samakan dengan PHP dev (8.4)

sudo mkdir -p /etc/apt/keyrings
sudo curl https://pkg.henderkes.com/api/packages/${VERSION}/debian/repository.key \
  -o /etc/apt/keyrings/static-php${VERSION}.asc
echo "deb [signed-by=/etc/apt/keyrings/static-php${VERSION}.asc] \
  https://pkg.henderkes.com/api/packages/${VERSION}/debian php-zts main" | \
  sudo tee /etc/apt/sources.list.d/static-php${VERSION}.list
sudo apt update && sudo apt install frankenphp
```

Binary `/usr/bin/frankenphp` · Caddyfile `/etc/frankenphp/Caddyfile` · php.ini `/etc/php-zts/php.ini`.

> **ADA DUA PHP DI SERVER — jangan tertukar.** Ini jebakan termahal di dokumen ini.
> `/usr/bin/php-zts` (milik FrankenPHP, `/etc/php-zts/php.ini`) yang **melayani semua request web**;
> `/usr/bin/php` biasa (mis. dari PPA Sury) hanya untuk CLI: artisan, composer, queue, cron.
> Ekstensi di `php` biasa **tidak** otomatis ada di PHP-ZTS.
>
> ```bash
> /usr/bin/php-zts -m | grep -E 'pdo|pgsql|mbstring|intl|zip|gd'
> ```
>
> Memakai `php -m` di sini adalah kesalahan yang lolos diam-diam: hasilnya hijau semua, lalu
> aplikasi mati dengan `Class "PDO" not found` di request pertama. Gejalanya HTTP 500 dengan
> header `X-Powered-By: PHP/8.4.x`. Juga **bukan** `frankenphp php-cli -m` — subcommand itu
> memperlakukan `-m` sebagai nama file.
>
> Paket ZTS default **tidak membawa PDO sama sekali**:
> ```bash
> sudo apt install php-zts-pdo php-zts-pdo-pgsql php-zts-pgsql
> sudo systemctl restart frankenphp
> ```

`/etc/php-zts/php.ini`: `upload_max_filesize = 30M`, `post_max_size = 30M`,
`memory_limit = 256M`, `max_execution_time = 60`. Restart service tiap file ini berubah.

### 6.3 Mode klasik

> **Jangan taruh aplikasi di dalam `/home`.** Unit systemd bawaan memakai `ProtectHome=true`,
> sehingga seluruh `/home` tak terlihat oleh proses FrankenPHP apa pun permission Unix-nya.
> Gejalanya menyesatkan: **HTTP 403 body kosong** dan `laravel.log` tidak pernah terbuat.
> Pakai `/var/www` (`ProtectSystem=full` tidak menyentuhnya).

`/etc/frankenphp/Caddyfile`:

```caddyfile
{
	frankenphp
	order php_server before file_server
}

:80 {
	root /var/www/eproposal/public
	encode zstd br gzip
	php_server
}
```

Ganti `:80` dengan nama domain kalau sudah ada DNS (Caddy urus TLS otomatis); untuk IP internal
biarkan `:80` — auto-TLS tidak bisa menerbitkan sertifikat untuk alamat IP.

```bash
sudo chown -R frankenphp:frankenphp /var/www/eproposal/storage /var/www/eproposal/bootstrap/cache
sudo systemctl enable --now frankenphp
```

Pemiliknya **`frankenphp`, bukan `www-data`** (cek `systemctl cat frankenphp`). Kalau port 80
masih dipegang Nginx/Apache, service gagal start dengan `bind: address already in use` dan systemd
hanya menampilkan `activating (auto-restart)` — cek `sudo ss -tlnp | grep -E ':80|:443'`.

### 6.4 Mode worker (Octane) + proses pendukung

```bash
composer require laravel/octane
php artisan octane:install --server=frankenphp
sudo systemctl disable --now frankenphp     # jangan berebut port
```

systemd unit `eproposal-octane.service` menjalankan
`php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=80 --admin-port=2019`
sebagai `www-data`; port 80 butuh `sudo setcap cap_net_bind_service=+ep /usr/bin/frankenphp`.
`.env`: `OCTANE_HTTPS=false` selama masih HTTP internal (kalau di belakang reverse proxy TLS,
set `true` — tanpa itu URL yang di-generate berskema `http://` dan Livewire kena mixed-content).
Default worker = jumlah core; `--max-requests=500` adalah jaring pengaman memory leak, jangan
dinaikkan tanpa alasan.

**Queue & scheduler tetap proses terpisah** — Octane tidak menjalankannya. Email Resend lewat
queue (`QUEUE_CONNECTION=database`), jadi tanpa worker gejalanya "tidak ada error, tapi email
tidak sampai".

```ini
# /etc/systemd/system/eproposal-queue.service → ExecStart
/usr/bin/php artisan queue:work --tries=3 --timeout=90
```
```cron
* * * * * cd /var/www/eproposal && php artisan schedule:run >> /dev/null 2>&1
```

### 6.5 Catatan khusus aplikasi ini (hasil audit worker mode)

- **Singleton / state statis: aman** — tidak ada `singleton()`, `static $`, atau injeksi
  container/request ke konstruktor di `app/`.
- **`env()` di luar config: aman** — tidak ada pemanggilan `env()` di `app/`, jadi `config:cache`
  tidak memutus apa pun.
- **spatie/permission: aman** — `'teams' => false`; fitur teams itulah yang menyimpan state
  per-request dan bocor antar request di worker mode.
- **Mary UI** — `Blade::component('badge', ...)` di `AppServiceProvider::boot()` hanya jalan
  sekali saat worker boot; justru itu yang diinginkan.
- **`DOCUMENTS_PATH` harus path Linux** di server (`/var/eproposal-files`) dan **writable oleh
  `www-data`**, bukan user login.
- **Postgres remote** — worker menahan koneksi lama; kalau firewall memutus koneksi idle,
  gejalanya error sporadis "server closed the connection unexpectedly".
- **Livewire temp upload** menulis ke `storage/app/private/livewire-tmp` — ikut di-`chown`.

### 6.6 Alur deploy ulang

> **Node ≥ 20.19 wajib** (`vite ^7` + `tailwindcss ^4`). Node bawaan Ubuntu 22.04 gagal dengan
> `SyntaxError: Unexpected token '.'` — itu Node tersedak optional chaining, bukan bug Vite.
> Kalau dpkg menolak karena bentrok `/usr/include/node/common.gypi`, buang paket Node distro
> dulu (`sudo apt remove libnode-dev libnode72 nodejs npm nodejs-doc`).

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan octane:reload        # tanpa ini, kode lama masih dilayani
php artisan queue:restart        # worker queue juga menahan kode lama
```

**Rollback** ke mode klasik: `sudo systemctl disable --now eproposal-octane` lalu
`sudo systemctl enable --now frankenphp`. Kode aplikasi tidak perlu diubah.

### 6.7 Checklist server

- [ ] `/usr/bin/php-zts -m` (**bukan** `php -m`) memuat `pdo`, `pdo_pgsql`, `pcntl`, `mbstring`, `intl`, `gd`, `zip`
- [ ] `upload_max_filesize`/`post_max_size` ≥ 30M di `/etc/php-zts/php.ini`
- [ ] `DOCUMENTS_PATH` = path Linux, dimiliki user yang menjalankan PHP
- [ ] `storage/` + `bootstrap/cache/` ter-chown
- [ ] `php artisan storage:link` (untuk disk `public`; disk `dokumen` tidak butuh)
- [ ] Service queue aktif (uji: registrasi user → email verifikasi masuk)
- [ ] Cron scheduler terpasang
- [ ] Uji upload raw data 20 MB → file muncul di `DOCUMENTS_PATH` → unduh dari UI
- [ ] Uji login, dashboard, dan satu alur Livewire penuh

---

## 7. Performa

Yang dipercepat worker mode adalah **bootstrap Laravel** (~20–50 ms/request), bukan query.
Karena itu: jangan mengukur di halaman yang query-nya berat, dan angka baru bermakna kalau ada
**dua** pengukuran (klasik vs worker) pada endpoint & data yang sama. Ubah satu variabel saja
antar pengukuran. Jalankan alat benchmark **dari laptop lewat LAN**, bukan dari server (server
2 core; alat benchmark akan berebut CPU dengan worker yang diukur).

```bash
oha -z 30s -c 20 http://<host>/login      # /login: publik, query ringan → dominan biaya bootstrap
```

**Titik yang akan ambruk duluan di volume besar — semuanya query, tidak satupun tertolong
worker mode:**

| Lokasi | Masalah |
|---|---|
| `Proposal/Index.php`, `Antrian/BaseAntrian.php` (`render()`) | `count()` penuh **tiap render**, termasuk tiap ketikan pencarian |
| idem | `ilike '%kata%'` — wildcard di depan, index B-tree tidak terpakai → seq scan |
| `BaseAntrian::render()` | `orderByDesc('updated_at')` padahal index hanya di `unit_sekarang`, `status`, `user_id`. Berlaku di **kedua** tab sekarang — dulu tab Antrian `asc` |
| `BaseAntrian::riwayatQuery()` | `whereHas('statusHistory')` — subquery EXISTS per render |

Perbaikannya di ranah database (index trigram/GIN untuk pencarian, index `updated_at`, buang
`count()` per render, debounce input) — bukan di app server. Uji volume besar tersedia lewat
`ProposalBulkSeeder` (`BULK_PROPOSAL_COUNT=1000000 php artisan db:seed --class=ProposalBulkSeeder`);
data itu tanpa history/dokumen, jadi tab **Riwayat** tidak ikut terukur.

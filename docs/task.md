# Progres & Pekerjaan yang Belum Selesai

> **File ini titik masuk untuk melanjutkan pekerjaan.** Sesi baru: baca [prd.md](prd.md)
> (apa yang dibangun) → file ini (posisi sekarang) → ambil item dari §3.
>
> Semua tambahan pekerjaan, temuan, dan ide fitur ditulis **di sini** — bukan sebagai file
> docs baru (lihat [rules.md §1](rules.md#1-dokumentasi)).
>
> Terakhir diselaraskan dengan kode: **11 Agustus 2026**.

---

## 1. Kondisi Sekarang

Aplikasi berjalan penuh untuk alur 4 tahap.

**Struktur database sudah dipisah per kelompok CRU & KEPK** (schema `rspi`, 5 tabel baru,
2 tabel di-rename), **Tahap 2 mendapat langkah verifikasi berkas etik oleh KEPK** yang
sebelumnya tidak ada, dan **PKS dipindah dari berkas wajib Tahap 2 menjadi unggahan CRU**
yang lepas dari alur. Verifikasi terakhir: **`artisan test` → 73 lulus, 183 assertion**,
berjalan di PostgreSQL (`cru_test`) — bukan lagi sqlite in-memory.

Dua penyeragaman tampilan menyusul, aturannya dikunci di
[design.md §5](design.md#5-aturan-tampilan-yang-mengikat): semua daftar data menampilkan
**terbaru di atas**, dan setiap input unggah menampilkan **format & ukuran maksimal** yang
diturunkan dari aturan validasinya sendiri.

**DB kerja `cru` sudah di-migrate** (`migrate:fresh --seed`, 7 Agustus 2026) dan diverifikasi
read-only: 14 tabel di `public`, 17 di `rspi`, 7 partial unique index, dan keempat kolom yang
dibuang memang sudah tidak ada di `rspi.proposal`. Isi seeder pulih utuh — 10 user, 9 role,
48 permission, 12 menu, master survey 3/6/5.

**DB server `eprotocol` (172.16.202.207) juga sudah di-migrate** (`migrate:fresh --seed`,
10 Agustus 2026, atas perintah eksplisit pemilik sistem — data lama dikonfirmasi dummy).
Strukturnya sekarang sama dengan `cru`. Detail & status akses baca-tulis di
[§3 "Data uji volume besar di database"](#3-belum-dikerjakan).

Cadangan struktur & data lama sebelum migrasi: `C:\Users\arsip\cru-backup-2026-08-07.sql`
(49,9 KB, `pg_dump`). Boleh dihapus kalau sudah yakin.

`SemuaHalamanRenderTest` menutup lubang lama: Dashboard, Laporan, Audit Log, Antrian, dan
daftar Proposal sebelumnya **tidak punya tes sama sekali**, padahal pemisahan ini menyentuh
hampir semua model. Sekarang ke-14 halaman dirender, plus halaman proposal di ke-13 status.

| Bagian | Status |
|---|---|
| Fondasi: Laravel 12, Livewire 3, Mary UI + daisyUI, spatie/permission, UUIDv7 + audit columns | ✅ |
| Domain inti: 7 enum, 14 migration, `ProposalWorkflow` sebagai pintu tunggal transisi | ✅ |
| Pemisahan struktur CRU & KEPK (schema `rspi`, berkas kerja per unit) | ✅ terpasang di `cru` (dev) & `eprotocol` (server) |
| RBAC & menu dinamis: 9 role, 12 menu, 48 permission, sinkronisasi menu→permission otomatis | ✅ |
| Auth: login, registrasi, lupa/reset password, math captcha, layout + sidebar dinamis | ✅ |
| Tahap 1 (CRU): revisi, presentasi, tolak, loloskan | ✅ |
| PKS: diunggah CRU kapan saja, lepas dari alur (bukan lagi syarat Tahap 2) | ✅ |
| Tahap 2 (KEPK + Reviewer): **3** berkas etik, **verifikasi kelengkapan oleh KEPK + minta revisi**, penunjukan reviewer, loop ronde, ACC, lanjut/tolak | ✅ |
| Tahap 3: dua bukti bayar + verifikasi/tolak, info rekening dari master kontak | ✅ |
| Tahap 4: draft izin, laporan + raw data, izin final, **gate survey** | ✅ |
| Penyimpanan file di luar folder aplikasi (disk `dokumen`, privat) | ✅ |
| Email Resend + template email kustom (verifikasi & reset password) | ✅ — `EMAIL_VERIFICATION_REQUIRED=true` di `.env`, pengirim `simrs@suliantisarosohospital.com` |
| Dashboard per role, halaman Laporan, Audit Log, Profil | ✅ |
| Notifikasi Telegram ke grup staf untuk aksi peneliti | ✅ kode selesai — **belum diuji di server**, toggle masih `false` |
| Chat real-time (Reverb) | ❌ dihapus permanen — lihat §4 |

---

## 2. Cara Menambah Entri

Tulis sebagai sub-bagian di §3 dengan tiga baris ini — supaya sesi berikutnya bisa langsung
mengerjakan tanpa menebak:

```
### Judul singkat
**Kenapa:** alasan / masalah yang diselesaikan.
**Kondisi selesai:** apa yang harus benar supaya dianggap beres.
**Catatan:** file/potongan kode terkait, keputusan yang sudah diambil.
```

---

## 3. Belum Dikerjakan

### Nyalakan notifikasi Telegram di server
**Kenapa:** kodenya sudah jadi dan tertutup tes, tapi `TELEGRAM_NOTIFIKASI_AKTIF` masih
`false` dan belum ada bot/grup sungguhan. Sampai itu diisi, fiturnya tidak melakukan apa pun.
Sama seperti email, pengirimannya juga bergantung **queue worker yang berjalan** — tanpa
worker gejalanya "tidak ada error, tapi notifikasi tidak muncul di grup".
**Kondisi selesai:** satu aksi peneliti di server memunculkan pesan di grup Telegram,
isinya kode + status + unit + link, dan **tanpa** judul penelitian maupun nama peneliti.
**Catatan:** langkah `@BotFather`, cara ambil `chat_id`, dan perilaku saat gagal ada di
[arsitektur.md §5](arsitektur.md#5-autentikasi-email--captcha). Kalau worker bermasalah,
`QUEUE_CONNECTION=sync` menjalankannya inline tanpa ubah kode. Siapa saja yang boleh masuk
grup itu perlu diputuskan dulu — isinya kode proposal dan link, jadi anggotanya sebaiknya
terbatas staf CRU/KEPK.

### Halaman akses mudah untuk reviewer
**Kenapa:** reviewer rata-rata sudah sepuh dan kesulitan dengan email + password + captcha,
sehingga proposal tertahan bukan karena telaahnya berat tapi karena login-nya menyusahkan.
**Kondisi selesai:** reviewer bisa masuk ke daftar proposal yang ditugaskan padanya cukup
dengan mengetik PIN 6 angka.
**Catatan:** rancangannya sudah dibahas dan **belum ditulis jadi spec**. Arah yang disepakati:
kartu cetak ber-QR (link personal permanen) sebagai pembawa identitas + PIN 6 angka sebagai
rahasia — pola token, tapi tokennya kertas. PIN satu kolom tanpa identitas **ditolak**: tanpa
kolom identitas, rate-limit per akun mustahil, dan ruang tebakan terbagi jumlah reviewer
sehingga sistem justru makin rapuh saat penggunanya makin banyak. OTP Telegram juga ditolak —
bot tidak bisa mengirim ke orang yang belum pernah chat duluan, jadi onboarding-nya malah
lebih berat daripada login yang mau digantikan.

### Pemantauan PKS yang belum terbit
**Kenapa:** sejak PKS dilepas dari syarat Tahap 2, **tidak ada apa pun yang memaksa PKS pernah
diunggah** — proposal bisa `Selesai` tanpa PKS dan tak seorang pun diingatkan. Itu memang
konsekuensi yang diterima (penerbitannya lama), tapi berarti kelalaian jadi tidak terlihat.
**Kondisi selesai:** CRU punya cara melihat proposal mana yang belum ada PKS-nya — mis. filter
di antrian CRU atau angka di dashboard.
**Catatan:** datanya sudah ada, tinggal query: `proposal` yang tidak punya baris
`proposal_documents` ber-`jenis = 'pks'`. Tidak butuh kolom atau tabel baru.

### Buang spec kerja pemisahan CRU/KEPK
**Kenapa:** `.claude/specs/2026-08-07-pemisahan-cru-kepk.md` adalah spec sementara; strukturnya
sudah terpasang dan `docs/skema.md` sudah menggambarkannya sebagai kondisi terpasang. Dibiarkan
hidup, ia akan jadi dokumen basi — persis alasan `rules.md §1` membatasi docs jadi enam file.
**Kondisi selesai:** file itu dihapus, dan tidak ada lagi yang menunjuk ke sana.
**Catatan:** ditahan dulu karena berkasnya **belum masuk git** — menghapusnya sekarang berarti
hilang permanen, termasuk 8 keputusan terkunci dan alasannya. Buang setelah pekerjaan ini
di-commit, atau kalau isinya sudah tidak diperlukan.

### Isi berkas kerja CRU/KEPK dari halaman KEPK yang sebenarnya
**Kenapa:** `kepk_protokol_etik` sekarang diisi dari panel Penunjukan Reviewer di halaman
proposal — cukup untuk nomor protokol, jenis telaah, dan tanggal sidang, tapi KEPK belum punya
halamannya sendiri untuk mengelola protokol lintas proposal.
**Kondisi selesai:** KEPK bisa melihat & mengelola protokol etik sebagai daftar, bukan hanya
per proposal.
**Catatan:** `jenis_telaah` saat ini **tidak menggerakkan alur apa pun** — tidak ada percabangan
yang membacanya. Kalau di RSPI `exempted` berarti tanpa reviewer, itu butuh perubahan di
`ProposalWorkflow::tugaskanReviewer()` (sekarang wajib ≥1 reviewer) dan mungkin
`ProposalStatus::allowedNext()`. Keduanya sengaja tidak disentuh saat pemisahan struktur.

Yang **tidak** disentuh sama sekali: `ProposalStatus`, `Unit`, dan
`ProposalWorkflow::transition()`. Kalau suatu saat ketiganya ikut berubah, berarti ada yang
melenceng dari kesepakatan.

### Export laporan ke Excel
**Kenapa:** Direksi/CRU/Auditor butuh rekap yang bisa diolah di luar aplikasi; halaman Laporan
sekarang hanya menampilkan angka di layar.
**Kondisi selesai:** tombol unduh di `/laporan` menghasilkan file `.xlsx` berisi rekap per
status untuk tahun terpilih, ter-otorisasi `laporan.read`.
**Catatan:** `app/Livewire/Laporan.php` sudah menyiapkan `$q`, `$perStatus`, `$tahunTersedia`.
Paket Maatwebsite Excel **belum terpasang** (`composer.json`), folder `app/Exports/` belum ada.

### Pastikan pengiriman email benar-benar sampai di produksi
**Kenapa:** toggle verifikasi sudah `true` dan pengirimnya `simrs@suliantisarosohospital.com`,
tapi pengiriman bergantung pada dua hal di luar kode: status domain di Resend dan **queue worker
yang berjalan** (email dikirim lewat queue). Tanpa worker, gejalanya "tidak ada error, tapi email
tidak sampai".
**Kondisi selesai:** registrasi akun baru di server menghasilkan email yang tercatat *delivered*
di Resend → *Logs/Emails*.
**Catatan:** langkah DNS & jebakannya di
[arsitektur.md §5](arsitektur.md#5-autentikasi-email--captcha); service queue di
[arsitektur.md §6.4](arsitektur.md#64-mode-worker-octane--proses-pendukung).

### Deploy ke server (FrankenPHP)
**Kenapa:** aplikasi masih dijalankan dari lingkungan dev.
**Kondisi selesai:** checklist di [arsitektur.md §6.7](arsitektur.md#67-checklist-server) tercentang
semua, mode klasik dulu, queue worker & cron aktif.
**Catatan:** jebakan PHP-ZTS vs PHP biasa dan larangan menaruh app di `/home` sudah didokumentasikan —
baca sebelum mulai, dua-duanya pernah memakan waktu.

### Optimasi query untuk volume besar
**Kenapa:** dengan >1,2 juta baris `proposal`, halaman antrian & daftar proposal melambat.
Penyebabnya query, bukan app server.
**Kondisi selesai:** pencarian & antrian tetap responsif pada volume itu.
**Catatan:** empat titik masalah (count per render, `ilike '%..%'`, `orderBy('updated_at')` tanpa
index, `whereHas` per render) beserta arah perbaikannya ada di
[arsitektur.md §7](arsitektur.md#7-performa).

### Titik masuk Direksi, Rekam Medis, dan Auditor
**Kenapa:** ketiga role sudah ada di [prd.md §2](prd.md#2-aktor--hak-akses) tapi belum punya
halaman sendiri — Direksi hanya menumpang antrian CRU + laporan, Rekam Medis baru punya dashboard.
**Kondisi selesai:** tiap role punya halaman yang sesuai perannya (Direksi: ringkasan eksekutif;
Auditor: audit log yang bisa difilter; Rekam Medis: menunggu fitur dataset request).

### Dataset request (permintaan data ke Rekam Medis)
**Kenapa:** ada di scope produk sejak awal, belum ada kode sama sekali.
**Kondisi selesai:** peneliti dengan proposal berizin dapat mengajukan permintaan data,
Rekam Medis memprosesnya, seluruh aktivitas tercatat di audit trail.
**Catatan:** perlu tabel + enum status sendiri; ikuti konvensi [skema.md §1](skema.md#1-konvensi-wajib).

### Payment gateway (Tahap 3)
**Kenapa:** pembayaran sekarang manual — peneliti transfer lalu mengunggah dua bukti bayar.
**Kondisi selesai:** pembayaran ke CRU & KEPK dapat dilakukan dalam aplikasi, status pembayaran
terverifikasi otomatis.
**Catatan:** ditunda sampai alur manual dikonfirmasi benar oleh pengguna.

### ~~Data uji volume besar di database~~ — selesai, server sudah di-migrate juga
**Temuan 7 Agustus 2026:** entri lama menyebut 1.201.076 baris di DB `eprotocol`. Diperiksa
read-only: **`eprotocol` tidak ada di PostgreSQL lokal.** Yang ada adalah DB **`cru`** (sesuai
`.env` mesin dev) dengan 25 tabel di schema `public` dan **`public.proposal` berisi 0 baris**.

**Update 10 Agustus 2026:** `eprotocol` ternyata ada — di server (`172.16.202.207`), bukan di
PostgreSQL lokal. Diperiksa read-only: masih struktur lama (26 tabel `public`, tanpa schema
`rspi`), `public.proposal` berisi 1.201.079 baris. Atas perintah eksplisit pemilik sistem
(datanya dikonfirmasi dummy/benchmark lama, boleh hilang), dijalankan `migrate:fresh --seed`
di server ini. Hasil: struktur sekarang sama dengan dev — `public` 14 tabel, `rspi` 17 tabel,
seeder terisi (10 user, 9 role, 48 permission, 12 menu, master survey), `rspi.proposal` 0 baris.
**Kondisi selesai:** ✅ tercapai — server dan dev sama-sama sudah pakai struktur CRU/KEPK
terpisah. Backup **tidak dibuat** untuk migrasi server ini (beda dari migrasi dev 7 Agustus)
karena datanya sudah dikonfirmasi dummy dan sengaja diganti bersih.
**Catatan:** `ProposalBulkSeeder` tetap ada untuk membangkitkan ulang bila benchmark
diperlukan lagi (`BULK_PROPOSAL_COUNT=1000000`). Akses ke `eprotocol` kembali ke **hanya-baca**
mulai sekarang — operasi tulis berikutnya butuh perintah eksplisit pemilik sistem lagi, bukan
diasumsikan boleh karena sudah terjadi sekali.

### ~~Selaraskan nama database di dokumen dengan kenyataan~~ — selesai
**Kenapa (asal masalah):** `arsitektur.md`, `skema.md`, dan `rules.md` menyebut DB `eprotocol`,
sementara `.env` di mesin dev menunjuk `cru`. Sesi berikutnya bisa tertipu dan mencari database
yang tidak ada.
**Update 10 Agustus 2026 — sudah jelas sekarang, dua database, dua lingkungan:**
- **`cru`** — database dev lokal (PostgreSQL di mesin dev), dipakai untuk kerja & tes
  sehari-hari. Sudah di-migrate ke struktur CRU/KEPK terpisah sejak 7 Agustus.
- **`eprotocol`** — database di server (`172.16.202.207`), bukan lingkungan lokal. Isinya
  sebelumnya struktur lama + data dummy volume besar; per 10 Agustus sudah di-migrate ke
  struktur yang sama dengan `cru` (lihat entri di atas). Akses ke sini tetap **hanya-baca**
  secara default (lihat memory `database-hanya-baca`).

Jadi bukan salah satu nama yang keliru — keduanya nyata, hanya beda lingkungan. `.env` proyek
menentukan lingkungan mana yang aktif; jangan asumsikan dari nama DB saja, selalu cek `.env`.
**Kondisi selesai:** ✅ tercapai — dicatat di sini sebagai rujukan tunggal soal dua database ini.

---

## 4. Keputusan yang Sudah Ditutup

Jangan dibuka lagi tanpa alasan baru:

- **Chat real-time (Laravel Reverb)** — dibangun 13 Juli 2026, **dihapus total 16 Juli 2026**.
  Beban operasionalnya (proses `reverb:start` yang harus dijaga hidup, supervisi, port firewall)
  dinilai tidak sepadan. Kode lamanya masih ada di commit `68cfd52` bila suatu saat diperlukan.
  Kalau dibangun ulang: pakai `wire:poll`, bukan WebSocket.
- **Cloudflare Turnstile** — dipasang lalu di-revert; diganti math captcha self-hosted.
- **MinIO / SFTP / S3 cloud** untuk dokumen — ditolak; data penelitian RS tetap di jaringan
  internal, disk `dokumen` lokal sudah cukup. Jalur upgrade: ganti definisi disk saja.
- **Filament** — tidak dipakai (preferensi tim); **Flux UI** — berbayar.
- **2FA pada login** — tidak dipakai.

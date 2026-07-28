# Progres & Pekerjaan yang Belum Selesai

> **File ini titik masuk untuk melanjutkan pekerjaan.** Sesi baru: baca [prd.md](prd.md)
> (apa yang dibangun) → file ini (posisi sekarang) → ambil item dari §3.
>
> Semua tambahan pekerjaan, temuan, dan ide fitur ditulis **di sini** — bukan sebagai file
> docs baru (lihat [rules.md §1](rules.md#1-dokumentasi)).
>
> Terakhir diselaraskan dengan kode: **28 Juli 2026**.

---

## 1. Kondisi Sekarang

Aplikasi berjalan penuh untuk alur 4 tahap. Verifikasi terakhir: **`artisan test` → 40 lulus,
78 assertion** (sqlite in-memory, tidak menyentuh DB nyata).

| Bagian | Status |
|---|---|
| Fondasi: Laravel 12, Livewire 3, Mary UI + daisyUI, spatie/permission, UUIDv7 + audit columns | ✅ |
| Domain inti: 3 enum, 12 migration, `ProposalWorkflow` sebagai pintu tunggal transisi | ✅ |
| RBAC & menu dinamis: 9 role, 12 menu, 48 permission, sinkronisasi menu→permission otomatis | ✅ |
| Auth: login, registrasi, lupa/reset password, math captcha, layout + sidebar dinamis | ✅ |
| Tahap 1 (CRU): revisi, presentasi, tolak, loloskan | ✅ |
| Tahap 2 (KEPK + Reviewer): berkas etik, penunjukan reviewer, loop ronde, ACC, lanjut/tolak | ✅ |
| Tahap 3: dua bukti bayar + verifikasi/tolak, info rekening dari master kontak | ✅ |
| Tahap 4: draft izin, laporan + raw data, izin final, **gate survey** | ✅ |
| Penyimpanan file di luar folder aplikasi (disk `dokumen`, privat) | ✅ |
| Email Resend + template email kustom (verifikasi & reset password) | ✅ — `EMAIL_VERIFICATION_REQUIRED=true` di `.env`, pengirim `simrs@suliantisarosohospital.com` |
| Dashboard per role, halaman Laporan, Audit Log, Profil | ✅ |
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

### Data uji volume besar di database
**Kenapa:** tabel `proposal` di `eprotocol` berisi **1.201.076 baris** (±553 MB) sisa
`ProposalBulkSeeder` untuk benchmark; data ini tanpa dokumen/history sehingga membuat tampilan
daftar terlihat penuh oleh data palsu.
**Kondisi selesai:** ada keputusan pemilik sistem — dipertahankan untuk uji performa, atau
dibersihkan sebelum dipakai sungguhan.
**Catatan:** pembersihan adalah operasi tulis — **harus dijalankan pemilik sistem**, bukan
diam-diam (lihat [rules.md §5](rules.md#5-database)).

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

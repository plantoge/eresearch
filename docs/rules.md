# Aturan Kerja & Konvensi Kode — eProposal

> Baca ini **sebelum** menyentuh kode. Proses bisnis → [prd.md](prd.md) ·
> stack & infra → [arsitektur.md](arsitektur.md) · database → [skema.md](skema.md) ·
> tampilan → [design.md](design.md) · pekerjaan yang belum selesai → [task.md](task.md).

---

## 1. Dokumentasi

**Folder `docs/` berisi tepat enam file dan tidak boleh bertambah.**

| File | Untuk apa |
|---|---|
| `prd.md` | produk, aktor, alur, aturan bisnis |
| `arsitektur.md` | stack, infra, deploy, keputusan teknis |
| `skema.md` | database & enum |
| `design.md` | tampilan/UI |
| `rules.md` | file ini |
| `task.md` | progres & pekerjaan yang belum selesai |

- **Jangan membuat file docs baru** — tidak ada `setup-x.md`, `tutorial-y.md`, atau
  `prd-fitur-z.md`. Temuan, rencana fitur, dan sisa pekerjaan ditulis ke **`task.md`**;
  penjelasan permanen masuk ke salah satu dari lima file rujukan di atas.
- Alasannya: sebelumnya docs tumbuh satu file per fitur sampai isinya tumpang tindih dan
  melenceng dari kode. Dengan enam file tetap, "baca folder docs" selalu terarah.
- Kalau kode berubah, dokumen yang terkait **ikut diubah di commit yang sama**.
- Bahasa dokumen: **Indonesia**.

---

## 2. Aturan Domain (paling sering dilanggar)

1. **Jangan pernah menyetel `$proposal->status` langsung.** Semua perubahan status lewat
   `App\Services\ProposalWorkflow` (`ajukan`, `transition`, `tugaskanReviewer`,
   `reviewerMerespons`). Di sanalah validasi transisi, sinkronisasi `unit_sekarang`, dan
   pencatatan `proposal_status_history` terjadi. Menyetel langsung = audit trail bolong.
2. **Transisi baru didaftarkan di enum, bukan di komponen.** Tambahkan pasangan status di
   `ProposalStatus::allowedNext()`; `canGoTo()` yang menegakkannya.
3. **Kerahasiaan reviewer tidak boleh bocor** — komentar, file `tanggapan_reviewer`, dan
   identitas reviewer tidak pernah sampai ke peneliti (lihat [prd.md §6](prd.md#6-aturan-bisnis-yang-tidak-boleh-dilanggar)).
   Setiap query dokumen/review yang bisa dilihat peneliti wajib disaring.
4. **Aturan file diambil dari enum**, bukan ditulis ulang di komponen:
   `DocumentType::aturanValidasi()`. Menambah jenis dokumen = menambah case enum, bukan kolom baru.
5. **Gate survey** — `izin_final` tidak boleh bisa diunduh peneliti sebelum baris `respon`
   untuk proposal itu ada. Satu-satunya pintu unduhan adalah `DocumentDownloadController`.

---

## 3. Konvensi Kode

- **Tabel baru**: uuid v7 PK + `timestamps()` + `softDeletes()` + `auditColumns()`.
  Model memakai trait `HasUuidAndAudit` + `SoftDeletes`, dengan `$incrementing = false` dan
  `$keyType = 'string'`.
- **Foreign key masih ditunda** — kolom `*_id` tanpa constraint, tapi tetap di-index.
- **Permission** memakai pola `{slug}.{read|create|update|delete}`. Menu baru dibuat lewat UI
  atau seeder; permission-nya dibuat otomatis oleh `MenuObserver`/`MenuPermissionSync` —
  jangan membuat permission manual dengan pola lain.
- **Otorisasi berlapis**: middleware `permission:` di route + `abort_unless()` di method
  komponen + `@can` di view. Yang di view hanya kosmetik; guard di komponen yang mengamankan.
- **Jangan panggil `env()` di luar `config/`** — `config:cache` di produksi akan mengembalikan
  `null`. Tambahkan key di `config/`, baca lewat `config()`.
- **Jangan hardcode warna atau teks pemilik aplikasi** di view (lihat [design.md](design.md)).
- Ikuti gaya kode yang sudah ada: komentar Indonesia yang menjelaskan **kenapa**, bukan apa.
  Format dengan `vendor/bin/pint` sebelum commit.

---

## 4. Menjalankan Perintah (Windows/Laragon)

PHP & Composer **tidak ada di PATH**:

```bash
PHP='/c/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe'
"$PHP" artisan test
"$PHP" /c/laragon/bin/composer/composer.phar install
```

- **`artisan config:clear` wajib setiap kali `.env` berubah** — `config/eproposal.php` dan
  disk `dokumen` membaca env, dan cache config membuat perubahan tidak berefek.
- Jalankan **`artisan test`** sebelum commit. Suite mencakup workflow proposal, penugasan
  reviewer, gate survey, captcha, verifikasi email, dan sinkronisasi menu→permission.
- Satu pekerjaan selesai = satu commit, dengan pesan yang menjelaskan perubahan.

---

## 5. Database

- **Akses database produksi/development bersifat baca saja.** Tidak boleh menghapus data,
  mengubah struktur, atau menjalankan DDL/DML lewat sesi kerja: **tanpa** `migrate:fresh`,
  `db:wipe`, `DROP`, `ALTER`, `TRUNCATE`, `DELETE`, `UPDATE` massal.
  Untuk memeriksa: `artisan db:show`, `artisan db:table <tabel>`, `SELECT` ke
  `information_schema` — semuanya read-only.
- Perubahan struktur hanya lewat **file migration baru** yang dijalankan sendiri oleh pemilik
  sistem, bukan dieksekusi diam-diam. Jangan mengedit migration yang sudah pernah jalan.
- DB `eprotocol` saat ini berisi **data uji volume besar** (>1,2 juta baris `proposal` dari
  `ProposalBulkSeeder`) — perhitungkan itu saat menguji query; jangan menghapusnya tanpa
  persetujuan.

---

## 6. Batasan Produk

- **Tidak menambah fitur chat / realtime**, dan tidak menambah proses server yang harus dijaga
  hidup terus-menerus (WebSocket dan sejenisnya). Riwayat keputusannya di
  [task.md §4](task.md#4-keputusan-yang-sudah-ditutup).
- **Tidak menambah dependency baru tanpa alasan kuat.** Prioritaskan yang sudah terpasang
  (Livewire, Mary UI, spatie/permission). Hindari layanan eksternal untuk hal yang bisa
  diselesaikan sendiri — captcha adalah contohnya.
- **Tidak ada file dokumen yang bisa diakses publik.** Semua unduhan lewat controller
  ber-otorisasi.

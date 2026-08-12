# Spec — Halaman Reviewer Sederhana

> Status: diimplementasi · Tanggal: 12 Agustus 2026
> Rujukan: **[docs/fitur/fitur-halaman-reviewer.md](../../docs/fitur/fitur-halaman-reviewer.md)
> (PRD modul — sumber kebenaran untuk UI/UX halaman ini)** ·
> [prd.md](../../docs/prd.md) (otorisasi "reviewer hanya melihat proposal
> yang ditugaskan kepadanya", kerahasiaan komentar reviewer) ·
> [design.md](../../docs/design.md) (pola halaman daftar/antrian, Mary UI)

---

## 1. Masalah

Reviewer KEPK sebagian besar dokter senior yang tidak terbiasa navigasi aplikasi
berlapis. Alur saat ini ("Antrian Reviewer" → klik baris → pindah ke halaman
`/proposal/{id}` yang penuh kartu status semua pihak) membuat mereka tersesat:
harus tahu tab mana, scroll ke bagian mana, dan halaman itu memuat banyak
informasi yang tidak relevan untuk peran reviewer (kartu CRU, KEPK, riwayat
pembayaran, dsb).

Login pun masih harus dijaga tetap email+password+captcha biasa (bukan QR/PIN)
sesuai kebijakan keamanan, jadi solusinya bukan mengubah cara masuk, melainkan
menyederhanakan apa yang terjadi SETELAH masuk.

## 2. Yang dibangun

Satu halaman baru, `/reviewer/telaah`, yang jadi tujuan login otomatis untuk
akun yang HANYA berperan reviewer. Halaman ini: cari nama peneliti/kode/judul →
klik baris → dokumen dan form tanggapan (ACC/Minta Revisi) muncul di tempat,
tanpa pindah halaman.

Bukan yang dibangun: perubahan cara login, penghapusan "Antrian Reviewer" atau
`/proposal/{id}` lama (tetap ada, dipakai reviewer yang juga punya peran lain,
dan untuk kebutuhan admin/QA), perubahan pada `ProposalWorkflow::reviewerMerespons()`.

## 3. Keputusan yang sudah diambil

### K1 — Halaman baru, bukan mengubah yang lama

`Antrian Reviewer` (`/antrian/reviewer`) dan `Proposal\Show` (`/proposal/{id}`)
tetap ada persis seperti sebelumnya. Reviewer yang merangkap peran lain (mis. juga
`kepk`) tetap diarahkan ke `/dashboard` seperti biasa dan bisa memakai halaman
lama bila perlu detail lengkap proposal. Menghapus/reroute jalur lama berisiko
memutus alur staf lain yang sudah terbiasa dan berisiko regresi test yang ada.

### K2 — Komponen berdiri sendiri, bukan mewarisi `BaseAntrian`

`BaseAntrian` dirancang untuk pola "klik baris → pindah ke halaman lain"
(`link="{{ route('proposal.show', $p) }}"`). Kebutuhan di sini adalah baris
expand di tempat + form inline pada komponen yang sama. Meniru properti/pola
penamaan `BaseAntrian` (`cari`, `perPage`, `muatLagi()`, debounce, infinite
scroll) tapi sebagai class `App\Livewire\Reviewer\TelaahSederhana` tersendiri,
supaya `BaseAntrian` tidak dipaksa mendukung dua model interaksi berbeda.

### K3 — Menulis lewat `ProposalWorkflow::reviewerMerespons()` yang sudah ada, tanpa perubahan

Semua logika bisnis (menulis `TelaahReviewer`, update status `PenugasanReviewer`,
auto-transisi proposal saat semua reviewer ACC) sudah benar dan teruji di
`ReviewerAssignmentTest`. Halaman baru hanya memanggilnya persis seperti
`Proposal\Show::reviewerAcc()`/`reviewerMintaRevisi()` — tidak menduplikasi atau
mengubah `ProposalWorkflow`.

### K4 — Baris yang ditampilkan = SEMUA penugasan reviewer ini (bukan hanya yang menunggu)

Pencarian mengembalikan proposal dari `whereHas('penugasanReviewer',
reviewer_id = auth()->id())` tanpa filter status, supaya riwayat yang sudah
direspons ikut tampil. Baris yang statusnya bukan `menunggu` ditampilkan
read-only: dokumen + komentar/keputusan lama (semua ronde), tanpa form baru.

### K5 — Otorisasi baris-level di `render()`, bukan hanya di route

Karena `proposalIdTerbuka` adalah properti publik Livewire (bisa dimanipulasi
lewat request langsung ke endpoint Livewire), `render()` menegakkan ulang
`abort_if($proposalTerbuka && !$penugasanTerbuka, 403)` — pola yang sama
dengan `abort_unless` di `Proposal\Show::mount()`. Middleware route
`permission:antrian-reviewer.read` hanya menjamin PERAN, bukan kepemilikan
baris tertentu.

### K6 — Hanya telaah milik reviewer yang login yang ditampilkan

Beda dari `Proposal\Show` yang menampilkan semua reviewer (nama disamarkan
untuk yang tak berwenang) untuk kebutuhan KEPK, halaman ini HANYA reviewer,
jadi cukup dan HARUS hanya menampilkan
`telaahReviewer()->where('reviewer_id', auth()->id())` — menghindari kebocoran
komentar reviewer lain pada proposal yang sama (pelanggaran kerahasiaan PRD).

### K7 — Redirect login menghormati `intended()` lebih dulu

Reviewer yang login lewat deep-link (mis. dari link notifikasi ke
`/proposal/{id}`) tetap diarahkan ke tujuan aslinya, bukan dipaksa ke
`/reviewer/telaah`. Redirect otomatis ke halaman sederhana HANYA berlaku saat
tidak ada `url.intended` di session (login biasa dari halaman `/login`) DAN
satu-satunya peran user adalah `reviewer`.

### K8 — Layout sendiri tanpa sidebar

Halaman ini memakai `components.layouts.reviewer`, bukan `components.layouts.app`.
PRD §4.5 melarang sidebar bermenu banyak; navigasinya hanya nama aplikasi,
identitas reviewer, pemilih tema, dan Keluar. Dropdown profil dihilangkan —
halaman kerja reviewer tidak boleh menawarkan jalan keluar dari alurnya.
Ukuran dasar teks `text-lg` (18px), tombol ≥48px, input 56px, sesuai PRD §22.

**Pemilih tema dipertahankan atas permintaan eksplisit** (12 Agustus 2026).
Awalnya ikut dibuang bersama dropdown profil demi §4.5, tapi tema adalah
preferensi kenyamanan baca — justru relevan untuk mata yang sudah tua — dan
tidak memindahkan reviewer ke halaman lain, jadi tidak melanggar prinsip
navigasi minimal. Mekanismenya sama persis dengan layout utama (localStorage +
`data-theme`), hanya ukurannya diperbesar mengikuti §22.

### K9 — Keputusan dipilih dulu, baru disimpan (dua langkah + konfirmasi)

Versi pertama memakai dua tombol yang **langsung** mengirim (ACC / Minta Revisi).
Diganti pola PRD §14–§17: tombol besar memilih keputusan → komentar diisi →
`SIMPAN HASIL REVIEW` → dialog konfirmasi → simpan. Alasannya pencegahan salah
klik: keputusan reviewer bersifat final dan memicu transisi status, sementara
penggunanya justru yang paling rawan salah tekan.

Validasi dijalankan di `mintaKonfirmasi()`, **sebelum** dialog muncul — bukan
sesudah reviewer menekan "Ya, Simpan" — supaya kesalahan isian tidak muncul di
balik dialog. `batalKonfirmasi()` sengaja tidak menghapus keputusan/komentar
(PRD §21: data yang sudah ditulis tidak boleh hilang).

### K10 — PDF dibaca inline lewat pembaca bawaan browser, bukan pustaka JS

PRD §13 menuntut dokumen terbuka di halaman yang sama (bukan tab baru) dengan
nomor halaman, maju/mundur, zoom, dan layar penuh. Semua itu sudah disediakan
pembaca PDF bawaan browser bila berkas disajikan `Content-Disposition: inline`
di dalam `<iframe>`. Karena itu `DocumentDownloadController` diberi mode
`?baca=1` yang hanya mengubah cara penyajian — **otorisasinya satu jalur** dengan
unduhan biasa, tidak ada gerbang baru yang bisa lupa disamakan.

PDF.js ditolak: `docs/rules.md` melarang JS dari CDN, dan mem-bundle-nya lewat
npm menambah dependency front-end besar untuk kemampuan yang sudah ada gratis
di browser. Disediakan tautan "Unduh dokumen ini" sebagai jalan keluar bila
pembaca bawaan gagal.

### K11 — Bahasa status ditulis ulang untuk dokter, bukan untuk staf

`ProposalStatus` ("Menunggu Review Reviewer", "Disetujui Reviewer") ditulis untuk
alur kerja staf dan tidak pernah ditampilkan di halaman ini. Yang ditampilkan
adalah status penugasan reviewer itu sendiri, dengan kosakata PRD §19:
`menunggu` → **Belum Diperiksa**, `revisi` → **Perlu Revisi**, `acc` →
**Disetujui**. Ada tes yang menjaga ini (`assertDontSee('Menunggu Review Reviewer')`)
supaya istilah internal tidak bocor lagi tanpa sengaja.

## 4. Rancangan

### 4.1 Alur

```
Login (email+password+captcha, TIDAK BERUBAH)
      ↓
Auth::attempt() sukses → session()->regenerate()
      ↓
role check: hanya 'reviewer' & tak ada url.intended?
      ↓ ya                                  ↓ tidak
redirect ke /reviewer/telaah        redirect()->intended(dashboard)
      ↓
TelaahSederhana::render() — ringkasan (total/perlu/sudah) + cari → daftar proposal
      ↓
klik kartu → buka($id) → panel detail muncul di tempat
      ↓
render() ulang: otorisasi baris + detail + dokumen + riwayat telaah reviewer ini
      ↓
bacaDokumen($dokId) → iframe PDF inline di halaman yang sama (tutupDokumen untuk menutup)
      ↓
pilihKeputusan('approve'|'revise') → isi catatan
      ↓
mintaKonfirmasi() — VALIDASI di sini, lalu dialog konfirmasi tampil
      ↓                                    ↓ batalKonfirmasi()
simpanReview()                     dialog tertutup, isian utuh
      ↓
ProposalWorkflow::reviewerMerespons()  ← TIDAK DIUBAH
      ↓ (gagal → toast error, isian TIDAK dibuang)
toast sukses, panel berganti sendiri ke tampilan hasil (read-only)
```

### 4.2 Berkas

| Berkas | Isi |
|---|---|
| `app/Livewire/Reviewer/TelaahSederhana.php` | komponen utama |
| `resources/views/livewire/reviewer/telaah-sederhana.blade.php` | view |
| `resources/views/components/layouts/reviewer.blade.php` | layout tanpa sidebar (K8) |
| `app/Http/Controllers/DocumentDownloadController.php` | mode `?baca=1` untuk iframe (K10) |
| `routes/web.php` | route `reviewer.telaah` |
| `app/Livewire/Auth/Login.php` | redirect kondisional |
| `database/seeders/MenuSeeder.php` | entri menu baru |
| `tests/Feature/TelaahSederhanaTest.php` | test komponen + layout (24) |
| `tests/Feature/LoginRedirectReviewerTest.php` | test redirect login (3) |
| `tests/Feature/BacaDokumenInlineTest.php` | test penyajian inline (3) |
| `tests/Feature/SemuaHalamanRenderTest.php` | `/reviewer/telaah` masuk smoke test |

### 4.3 Konfigurasi

Tidak ada konfigurasi/env baru. Tidak ada migration baru — memakai tabel dan
permission (`antrian-reviewer.read`/`.update`) yang sudah ada. Satu permission
baru murni untuk penyaringan menu: `reviewer-telaah.read`/`.update`, otomatis
di-grant lewat `MenuSeeder` (tidak perlu migration data manual, cukup jalankan
ulang seeder).

### 4.4 `TelaahSederhana`

```php
protected function query();               // daftar proposal milik reviewer ini
protected function penugasanTerbuka(): ?PenugasanReviewer;
public function buka(string $proposalId): void;
public function reviewerAcc(): void;
public function reviewerMintaRevisi(): void;
protected function kirim(string $keputusan): void;  // panggil ProposalWorkflow::reviewerMerespons()
```

### 4.5 Statistik "total proposal atas nama peneliti"

Dihitung per-baris yang dibuka (bukan agregat tunggal di atas daftar), karena
pencarian bisa mencocokkan >1 nama peneliti berbeda. Query terpisah (bukan
dihitung dari koleksi `$proposals` yang sudah dibatasi `perPage`, supaya tidak
under-count):

```php
Proposal::where('peneliti_utama', $proposalTerbuka->peneliti_utama)
    ->whereHas('penugasanReviewer', fn ($q) => $q->where('reviewer_id', auth()->id()))
    ->count();
```

Selalu di-scope ke reviewer yang login — tidak pernah jadi hitungan sistem-wide.

### 4.6 Kasus tepi

| Kejadian | Perilaku |
|---|---|
| Reviewer belum punya penugasan sama sekali | "Belum ada proposal — Saat ini tidak ada proposal yang perlu Anda periksa." |
| Pencarian tak menemukan hasil | "Proposal tidak ditemukan — Coba gunakan nama peneliti atau judul penelitian yang berbeda." |
| Menyimpan tanpa memilih keputusan | Ditolak validasi, dialog konfirmasi tidak muncul |
| Konfirmasi dibatalkan | Dialog tertutup; keputusan & komentar tetap utuh |
| `reviewerMerespons()` melempar error | Toast "Review belum berhasil disimpan", isian **tidak** dibuang, error di-`report()` |
| PDF gagal tampil di iframe | Disediakan tautan "Unduh dokumen ini" di bawah viewer |
| Berpindah proposal saat draf terisi | Draf dibersihkan — mencegah komentar proposal A tersimpan di proposal B |
| Reviewer coba buka `proposalIdTerbuka` milik proposal yang TIDAK ditugaskan ke dirinya (manipulasi request) | 403 (`abort_if` di `render()`) |
| Proposal punya banyak ronde (`ronde` bertambah tiap kirim-ulang) | Semua ronde riwayat telaah reviewer ini ditampilkan berurutan, bukan hanya ronde terakhir |
| Minta Revisi tanpa komentar | Ditolak validasi (`required|min:3`), sama seperti `Show::reviewerMintaRevisi()` |
| ACC tanpa komentar | Diperbolehkan (`catatan` opsional), sama seperti `Show::reviewerAcc()` |
| Reviewer merangkap peran lain (mis. `kepk`) | Tidak diarahkan otomatis ke halaman ini saat login; tetap ke `/dashboard`; menu baru tetap muncul di sidebar jika role `reviewer` dimiliki |
| Login lewat deep-link (`url.intended` ada di session) | `intended()` menang, tidak dipaksa ke halaman sederhana |

### 4.7 Perilaku saat gagal

Tidak ada state gagal baru — semua penanganan error (validasi, otorisasi,
kegagalan upload file) mengikuti persis apa yang sudah ada di
`ProposalWorkflow::reviewerMerespons()` dan `Show.php`, karena logika tulis
tidak diduplikasi.

## 5. Pengujian

27 test, semua ditulis TDD (RED → GREEN) dengan pola class-based
`Tests\TestCase` + `RefreshDatabase` seperti `ReviewerAssignmentTest`.

**`TelaahSederhanaTest`** (24) — otorisasi & daftar: hanya penugasan sendiri,
pencarian per nama peneliti, proposal asing ditolak 403. Ringkasan: total/perlu/
sudah benar dan ter-scope per reviewer. Bahasa: "Belum Diperiksa" muncul,
"Menunggu Review Reviewer" tidak pernah. Detail & dokumen: nomor proposal,
peneliti, daftar dokumen, tombol BACA DOKUMEN; viewer membuka iframe; dokumen
proposal lain ditolak 403. Keputusan: simpan tanpa memilih keputusan ditolak,
revisi wajib komentar, disetujui boleh tanpa komentar, konfirmasi muncul sebelum
simpan, batal tidak menghapus draf, simpan menulis telaah + mengubah status.
Sesudah: read-only tanpa form, semua ronde tampil, komentar reviewer lain tidak
pernah terlihat. Empty state kosong & hasil pencarian nihil. Layout (via HTTP,
bukan `Livewire::test`): halaman render 200, pemilih tema ada, sidebar tidak ada.

**`LoginRedirectReviewerTest`** (3) — reviewer tunggal-peran → `reviewer.telaah`;
peran ganda → `/dashboard`; ada `url.intended` → tujuan asli menang.

**`BacaDokumenInlineTest`** (3) — `?baca=1` menyajikan `Content-Disposition:
inline`; tanpa parameter tetap `attachment`; mode baca tidak melewati otorisasi.

## 6. Kondisi selesai

- [x] `artisan test` lulus, termasuk test baru di atas
- [x] Reviewer tunggal-peran login → langsung di `/reviewer/telaah`, tanpa klik apa pun lagi
- [x] Reviewer dengan peran ganda login → tetap ke `/dashboard`
- [x] Cari, klik, ACC/Revisi semuanya di satu halaman tanpa reload/navigasi
- [x] Komentar reviewer lain pada proposal yang sama TIDAK terlihat di halaman ini
- [x] `/antrian/reviewer` dan `/proposal/{id}` tetap berfungsi seperti sebelumnya
- [x] `vendor/bin/pint` bersih
- [x] `docs/task.md` diperbarui

## 7. Di luar cakupan

Login QR/PIN · penghapusan "Antrian Reviewer" atau `Proposal\Show` lama ·
notifikasi push saat ada penugasan baru · mode offline/PWA · perubahan pada
`ProposalWorkflow`.

### Penyimpangan sadar dari PRD modul

Tiga hal di [PRD](../../docs/fitur/fitur-halaman-reviewer.md) sengaja **tidak**
diikuti persis, dengan alasan:

1. **§27 nilai keputusan `revision`/`approved`.** Tetap memakai `approve`/`revise`
   yang sudah dipakai `ProposalWorkflow` dan tersimpan di kolom
   `kepk_telaah_reviewer.keputusan`. Mengganti kosakata berarti migration +
   backfill data lama + menyentuh alur KEPK yang dipakai bersama — biaya nyata
   untuk manfaat nol, karena keduanya sama-sama konsisten secara internal.
   Label tampilan sudah sesuai PRD ("Perlu Revisi" / "Disetujui").

2. **§19 status "Sedang Direview".** Tidak ditampilkan. Status itu berarti
   "reviewer sedang membuka proposal" — keadaan sesaat yang tidak disimpan di
   mana pun; melacaknya butuh kolom/tabel baru tanpa menambah kejelasan bagi
   reviewer, yang sudah tahu proposal mana yang sedang ia buka.

3. **§6 halaman login tanpa captcha.** Halaman login **tidak diubah** — math
   captcha tetap terpasang. PRD §6.2 hanya mendaftar komponen minimum dan tidak
   membahas keamanan; membuang captcha adalah kemunduran keamanan (lihat
   keputusan tertutup soal Turnstile di `docs/task.md §4`) dan akan mematikan
   `CaptchaTest`. Perlu keputusan terpisah bila memang diinginkan.

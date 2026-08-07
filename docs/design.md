# Panduan Tampilan (UI) — eProposal

> Proses bisnis → [prd.md](prd.md) · stack & infra → [arsitektur.md](arsitektur.md) ·
> database → [skema.md](skema.md) · aturan kerja → [rules.md](rules.md).
>
> Tujuan file ini: halaman baru terlihat seperti halaman yang sudah ada. Semua pola di bawah
> diambil dari kode yang berjalan — ikuti, jangan bikin dialek baru.

---

## 1. Stack UI

| Lapis | Pilihan |
|---|---|
| Komponen | **Mary UI 2.9**, prefix `mary-` → `<x-mary-card>`, `<x-mary-button>`, … |
| Design system | **daisyUI 5** (kelas `btn`, `badge`, `tabs`, `card`, token `base-100/200/300`, `primary`, …) |
| Utility CSS | Tailwind 4 (`@import 'tailwindcss'` di `resources/css/app.css`) |
| Ikon | Heroicons outline lewat Mary: `o-inbox-stack`, `o-eye`, `o-magnifying-glass`, … |
| Font | Instrument Sans (`--font-sans` di blok `@theme`) |
| Bahasa | **Indonesia**, `<html lang="id">` |

Prefix `mary-` diatur di `config/mary.php` (`'prefix' => 'mary-'`). Konsekuensinya ada satu
workaround permanen di `AppServiceProvider::boot()`:

```php
// Mary 2.9: Tab.php memanggil <x-badge> tanpa prefix, dan 'badge' tidak ada
// di alias internal Mary → tanpa baris ini semua halaman ber-x-mary-tab gagal compile.
Blade::component('badge', \Mary\View\Components\Badge::class);
```

Komponen Mary yang sudah dipakai (urut frekuensi): `card`, `button`, `input`, `form`, `header`,
`file`, `textarea`, `alert`, `stat`, `icon`, `menu-item`, `tab`/`tabs`, `select`, `password`,
`modal`, `choices-offline`, `list-item`, `dropdown`, `checkbox`, `toast`, `nav`, `main`.
**Pakai yang sudah ada dulu** sebelum menulis markup sendiri.

---

## 2. Tema

Sepuluh tema daisyUI aktif (`resources/css/app.css`):
`light` (default), `dark`, `cupcake`, `corporate`, `emerald`, `nord`, `winter`, `night`,
`dracula`, `retro`.

Aturannya:

- **Tema tidak mengikuti OS.** Tidak ada `--prefersdark`; pilihan murni milik user, disimpan di
  `localStorage['theme']`, diterapkan sebagai `data-theme` pada `<html>`.
- Script `window.applyTheme()` dijalankan **di `<head>` sebelum render** agar tidak berkedip,
  dan dipasang ulang pada event `livewire:navigated` — `wire:navigate` men-swap dokumen tanpa
  menjalankan ulang script head.
- Utilitas `dark:` diarahkan ke tema gelap daisyUI, bukan `prefers-color-scheme`:
  ```css
  @custom-variant dark (&:where([data-theme=dark] *, [data-theme=dark], [data-theme=night] *, ...));
  ```
- Pemilih tema ada di navbar (dropdown `o-swatch`) lengkap dengan pratinjau tiga swatch
  `primary`/`secondary`/`accent`.

**Karena itu: jangan menulis warna hardcode** (`bg-white`, `text-black`, `#fff`). Pakai token
daisyUI — `bg-base-100`, `bg-base-200`, `border-base-300`, `text-primary`, `opacity-60` —
supaya sepuluh tema itu tetap terbaca.

---

## 3. Layout

Dua layout di `resources/views/components/layouts/`:

### `app.blade.php` — halaman ber-login
- `<x-mary-nav sticky full-width>`: brand **eResearch Proposal**, tombol hamburger (mobile),
  dropdown tema, dropdown user (nama + role + Profil + Keluar via form POST tersembunyi).
- `<x-mary-main full-width with-nav>` dengan slot `sidebar` (drawer `main-drawer`, `collapsible`)
  berisi `<livewire:layout.sidebar />`.
- Notis hak cipta ditaruh **di dalam slot `content`**, bukan slot `footer` milik `x-mary-main`:
  slot footer dirender di luar drawer sehingga menambah tinggi halaman dan menyisakan ruang
  kosong di bawah sidebar. Tinggi konten dikunci `min-h-[calc(100vh-65px)]` (65px = tinggi navbar,
  angka yang sama dipakai Mary) lalu isi dibungkus `flex-1` agar notis menempel di dasar layar.
- `<x-mary-toast />` di akhir body.

### `guest.blade.php` — login, registrasi, lupa/reset password, notis verifikasi
Kartu terpusat `max-w-md`, brand + tagline di atas, `x-copyright variant="full"` di footer.
Script tema sama seperti layout app.

### Sidebar
`app/Livewire/Layout/Sidebar.php` mengambil menu `aktif` terurut `urutan`, **menyaring dengan
`$user->can("{$menu->slug}.read")`**, lalu memisahkan root vs anak (`parent_id`).
View memakai `<x-mary-menu activate-by-route>` + `x-mary-menu-sub` untuk submenu. Route yang
belum terdaftar jatuh ke `#` (dijaga `Route::has()`), jadi menu baru tidak pernah membuat error.

---

## 4. Pola Halaman

### Halaman daftar / antrian
Contoh acuan: `resources/views/livewire/antrian/index.blade.php`.

1. `<x-mary-header :title :subtitle separator>` dengan input pencarian di slot `middle`:
   ```blade
   <x-mary-input placeholder="Cari kode / judul / peneliti..."
                 wire:model.live.debounce="cari" icon="o-magnifying-glass" clearable />
   ```
2. Tab daisyUI (`tabs tabs-box`) untuk **Antrian Aktif** vs **Riwayat**, ikon di tiap tab.
3. `<x-mary-card shadow>` membungkus tabel dalam kontainer scroll
   `overflow-x-auto overflow-y-auto max-h-[65vh]`, tabel `table table-pin-rows` supaya header
   tetap terbaca saat scroll.
4. **Infinite scroll**, bukan pagination: sentinel `x-intersect="$wire.muatLagi()"` diletakkan
   **di dalam** kontainer scroll (kalau di luar, tidak pernah terpicu karena yang bergerak adalah
   scroll internal), dengan `wire:key` yang berubah tiap batch. Bila habis, tampilkan
   "Semua data sudah ditampilkan (n)".
5. **Selalu ada empty state** yang manusiawi: "Antrian kosong. 🎉" / "Belum ada proposal yang
   melewati unit ini."

### Menampilkan status & tahap
Jangan menulis kelas warna sendiri — ambil dari enum:

```blade
<span class="badge badge-sm {{ $p->status->warna() }}">{{ $p->status->value }}</span>
{{ $p->status->tahapan() ? 'T'.$p->status->tahapan() : '—' }}
```

Kolom kode proposal memakai `font-mono`; judul panjang dipotong `max-w-sm truncate`;
waktu memakai `diffForHumans()`.

### Halaman detail & aksi
Acuan: `livewire/proposal/show.blade.php` + `app/Livewire/Proposal/Show.php`.

- Satu halaman, blok aksi muncul **kondisional menurut status + peran** (`$isPemilik`, `$isCru`,
  `$isKepk`, `$isReviewer` disiapkan di `render()`), bukan halaman terpisah per aksi.
- Form pakai `<x-mary-form>` + `x-mary-input` / `x-mary-file` / `x-mary-textarea` / `x-mary-select`;
  pemilihan reviewer (multi) memakai `x-mary-choices-offline`.
- Umpan balik pakai toast Mary (`use Mary\Traits\Toast;` → `$this->success("Status: ...")`),
  bukan alert manual.
- Konfirmasi aksi berat pakai `x-mary-modal`.

### Form & validasi
Aturan validasi upload **selalu** diambil dari enum, jangan ditulis ulang:

```php
$this->validate(['fileUpload' => 'required|' . DocumentType::IzinFinal->aturanValidasi()]);
```

Label error bahasa Indonesia lewat argumen ketiga `validate()` (mis. `['catatan' => 'komentar']`).

---

## 5. Aturan Tampilan yang Mengikat

1. **Tombol aksi hanya dirender bila user berhak.** Sembunyikan dengan `@can('{slug}.{aksi}')`
   di view — tapi jangan berhenti di situ: guard `abort_unless()` di method komponen tetap wajib.
2. **Kerahasiaan reviewer di UI.** Komentar reviewer, berkas telaah, dan nama reviewer tidak
   boleh dirender untuk peneliti. `Show::render()` sudah menyiapkan `$bolehLihatReview` dan
   mengosongkan `$reviews`/`$dokumenTelaah` bagi yang tak berwenang — pakai flag itu, jangan
   buat pengecekan sendiri. Di riwayat status yang dilihat peneliti, pelaku ditulis "Reviewer".
   `$dokumen` tidak perlu disaring lagi: berkas telaah ada di tabel `kepk_dokumen_telaah`
   dengan route unduh sendiri, jadi tidak pernah ikut terbawa ke daftar dokumen peneliti.
3. **Setiap `x-mary-file` wajib punya `hint`** berisi format & ukuran maksimal, diambil dari
   `DocumentType::hintUnggah()` — jangan mengetik "PDF, maks 10 MB" langsung di view, karena
   itulah yang membuat teks dan aturan validasi melenceng.
4. **Daftar data ditampilkan terbaru di atas** (antrian, proposal, audit log, users).
5. **Bahasa Indonesia** untuk semua label, tombol, pesan kosong, dan pesan error.
6. **Tanpa JS/CDN eksternal.** Interaksi memakai Livewire + Alpine yang sudah ada
   (`wire:model.live.debounce`, `wire:click`, `x-intersect`). Tidak ada script dari luar.

---

## 6. Komponen Kustom

| Komponen | Fungsi |
|---|---|
| `<x-captcha />` | Widget math captcha: pertanyaan + input angka + tombol ganti soal. Murni Blade/Livewire, tanpa JS eksternal. Dipakai di Login & Register |
| `<x-copyright />` | `variant="short"` (default) → `© 2025 RSPI Prof. Dr. Sulianti Saroso`; `variant="full"` → nama panjang RS. Tahun otomatis jadi rentang (`2025–2026`) memakai `config('app.copyright_year')`, `app.owner`, `app.owner_full` |

Nama pemilik & tahun **tidak ditulis di view** — semuanya dari config/env (`APP_OWNER`,
`APP_OWNER_FULL`, `APP_COPYRIGHT_YEAR`).

---

## 7. Tampilan Email

Dua email transaksional memakai **notifikasi & template kustom** (bukan bawaan Laravel):

| Email | Notifikasi | View |
|---|---|---|
| Verifikasi akun | `App\Notifications\VerifikasiEmail` | `resources/views/emails/verifikasi.blade.php` |
| Reset password | `App\Notifications\ResetPasswordRSPI` | `resources/views/emails/reset-password.blade.php` |

Keduanya dipasang lewat override di `app/Models/User.php`
(`sendEmailVerificationNotification()`, `sendPasswordResetNotification($token)`), memakai
komponen Markdown mail (`<x-mail::message>`, `<x-mail::button :url="$url">`). Bingkai email
(header/footer/warna) sudah di-publish ke `resources/views/vendor/mail/`.

**Satu hal yang tidak boleh diubah:** URL di tombol.

> Kolom `email_verified_at` tidak diisi oleh template. Ia terisi karena user mengklik URL
> **bertanda tangan (signed)** hasil `URL::temporarySignedRoute('verification.verify', ...)`
> — di `VerifikasiEmail` masa berlakunya 60 menit. Selama tombol memakai variabel `$url`
> itu, field terisi otomatis. Menyusun link manual → tanda tangan tidak valid → user dapat **403**.

Yang bebas diubah: subjek, sapaan, isi kalimat, teks tombol, dan gaya di
`resources/views/vendor/mail/html/` (`header.blade.php`, `themes/default.css`,
`footer.blade.php` — berlaku untuk semua email).

Setiap habis mengubah: `php artisan config:clear`, lalu uji dengan mendaftar akun baru dan cek
Resend → *Logs/Emails*. Setup pengiriman & toggle verifikasi ada di
[arsitektur.md §5](arsitektur.md#5-autentikasi-email--captcha).

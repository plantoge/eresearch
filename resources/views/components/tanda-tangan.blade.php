{{--
    Kanvas tanda tangan — keluaran SVG.

    Menggantikan <x-mary-signature> karena komponen itu mematok keluarannya ke
    `toDataURL()` alias PNG, dan PNG memaksa dompdf memakai GD (lihat
    App\Support\TandaTangan). Pustaka gambarnya tetap signature_pad yang sama,
    jadi cara memakainya di layar tidak berubah.

    Kanvasnya ditemukan lewat x-ref, BUKAN document.getElementById(). Itu sengaja:
    versi maryUI merakit id kanvas dari hash argumen konstruktornya, sehingga dua
    kanvas dengan tinggi dan hint yang sama mendapat id identik dan yang kedua
    diam-diam mengikat diri ke kanvas pertama. x-ref selalu terikat ke elemen di
    dalam komponennya sendiri, jadi masalah itu tidak bisa muncul lagi.

    @props:
      model  — jalur wire:model, mis. 'formEtik.tanda_tangan'
      tinggi — tinggi kanvas dalam piksel
--}}
@props(['model', 'tinggi' => 180])

<div>
    <div wire:ignore x-data="{
        nilai: @entangle($model),
        pad: null,
        init() {
            const kanvas = this.$refs.kanvas;

            this.pad = new SignaturePad(kanvas, {
                penColor: '#111827',
                minWidth: 0.7,
                maxWidth: 2.2,
            });

            // Kanvas HTML punya dua ukuran: ukuran tampilan (CSS) dan ukuran
            // buffer piksel. Tanpa penyelarasan ini, goresan muncul melenceng
            // dari ujung pena di layar ber-DPI tinggi.
            const rasio = Math.max(window.devicePixelRatio || 1, 1);
            kanvas.width = kanvas.offsetWidth * rasio;
            kanvas.height = kanvas.offsetHeight * rasio;
            kanvas.getContext('2d').scale(rasio, rasio);

            this.pad.addEventListener('endStroke', () => this.rekam());
        },
        rekam() {
            // Kanvas kosong mengirim '' supaya `required` menangkapnya, bukan
            // menyimpan SVG kosong yang lolos validasi tapi tak berisi apa-apa.
            this.nilai = this.pad.isEmpty() ? '' : this.pad.toDataURL('image/svg+xml');
        },
        hapus() {
            this.pad.clear();
            this.nilai = '';
        },
    }"
        class="relative rounded-lg border border-base-300 bg-white select-none touch-none"
        :class="$wire.$errors?.has('{{ $model }}') && '!border-error'">
        <canvas x-ref="kanvas" height="{{ $tinggi }}"
            class="block w-full rounded-lg select-none touch-none"></canvas>

        <x-mary-button icon="o-backspace" label="Hapus" @click="hapus" type="button"
            class="btn-sm btn-ghost absolute end-2 top-1/2 -translate-y-1/2" />
    </div>

    @error($model)
        <div class="text-error text-xs pt-1">{{ $message }}</div>
    @enderror

    <div class="fieldset-label text-xs pt-1">
        Gambar tanda tangan Anda di dalam kotak. Bisa memakai mouse, pena, atau jari di layar sentuh.
    </div>
</div>

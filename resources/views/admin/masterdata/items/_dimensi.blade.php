{{-- Dimensi per koli/karton (cm) untuk CBM. Dipakai di create & edit; $item opsional. --}}
@php($item = $item ?? null)
<div class="separator separator-dashed my-5"></div>
<div class="mb-4">
    <div class="fw-bolder fs-6 text-gray-800">Dimensi Koli (untuk CBM)</div>
    <div class="form-text mt-1">Ukuran 1 koli/karton dalam <strong>cm</strong>. Opsional, tetapi panjang, lebar, dan tinggi harus diisi bersamaan.</div>
</div>
<div class="row g-6">
    @foreach (['panjang_koli' => 'Panjang (P)', 'lebar_koli' => 'Lebar (L)', 'tinggi_koli' => 'Tinggi (T)'] as $field => $label)
        <div class="col-md-4 col-xl-2">
            <div class="fv-row mb-7">
                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                <div class="input-group input-group-solid">
                    <input type="number" min="0.01" step="0.01" class="form-control form-control-solid js-dimensi" id="{{ $field }}" name="{{ $field }}"
                           value="{{ old($field, $item?->{$field}) }}" placeholder="0">
                    <span class="input-group-text">cm</span>
                </div>
                @error($field)
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
    @endforeach
    <div class="col-md-6 col-xl-3">
        <div class="fv-row mb-7">
            <label class="form-label">CBM per Koli</label>
            <div class="input-group input-group-solid">
                <input type="text" class="form-control form-control-solid bg-light fw-bolder" id="cbm_per_koli_preview" value="-" readonly tabindex="-1">
                <span class="input-group-text">m³</span>
            </div>
            <div class="form-text">P &times; L &times; T &divide; 1.000.000</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="fv-row mb-7">
            <label class="form-label">CBM per Satuan</label>
            <div class="input-group input-group-solid">
                <input type="text" class="form-control form-control-solid bg-light fw-bolder" id="cbm_per_unit_preview" value="-" readonly tabindex="-1">
                <span class="input-group-text">m³</span>
            </div>
            <div class="form-text">CBM per koli &divide; isi per koli (field Koli)</div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        $(function () {
            function num(selector) {
                var v = parseFloat($(selector).val());
                return isNaN(v) || v <= 0 ? null : v;
            }

            function fmt(value, digits) {
                return value.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: digits });
            }

            function updateCbm() {
                var p = num('#panjang_koli'), l = num('#lebar_koli'), t = num('#tinggi_koli');
                var perKoli = Math.floor(num('#koli') || 0);
                if (p === null || l === null || t === null) {
                    $('#cbm_per_koli_preview, #cbm_per_unit_preview').val('-');
                    return;
                }
                var cbm = p * l * t / 1000000;
                $('#cbm_per_koli_preview').val(fmt(cbm, 6));
                $('#cbm_per_unit_preview').val(perKoli > 0 ? fmt(cbm / perKoli, 8) : '-');
            }

            $('.js-dimensi, #koli').on('input change', updateCbm);
            updateCbm();
        });
    </script>
@endpush

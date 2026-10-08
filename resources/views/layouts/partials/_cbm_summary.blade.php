{{-- Kotak ringkasan Total Qty / Koli / CBM untuk form. Diisi via ImsCbm.renderSummary(). $id wajib. --}}
<div id="{{ $id }}" class="{{ $class ?? 'mt-6' }}">
    <div class="d-flex flex-wrap align-items-center justify-content-end gap-8 rounded border border-dashed border-gray-300 bg-light px-6 py-4">
        <div class="d-flex align-items-center me-auto text-gray-700">
            <i class="bi bi-box-seam fs-2 text-primary me-3"></i>
            <div>
                <div class="fw-bolder text-gray-800">Estimasi Volume (CBM)</div>
                <div class="fs-7 text-muted">Dihitung dari dimensi koli pada master item</div>
            </div>
        </div>
        <div class="text-end">
            <div class="fs-7 text-muted">Total Qty</div>
            <div class="fw-bolder fs-5 text-gray-800" data-cbm-total-qty>0</div>
        </div>
        <div class="text-end">
            <div class="fs-7 text-muted">Total Koli</div>
            <div class="fw-bolder fs-5 text-gray-800" data-cbm-total-koli>0</div>
        </div>
        <div class="text-end">
            <div class="fs-7 text-muted">Total CBM</div>
            <div class="fw-bolder fs-3 text-primary"><span data-cbm-total>0,000</span> m³</div>
        </div>
    </div>
</div>
@include('layouts.partials._cbm_script')

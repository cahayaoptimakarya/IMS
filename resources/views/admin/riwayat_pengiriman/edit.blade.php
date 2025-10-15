@extends('layouts.app')

@push('styles')
    <style>
        .items-toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap}
        .badge-sku{background:#F1F5F9;color:#0F172A;padding:.25rem .5rem;border-radius:.375rem;font-weight:700}
        .items-scroll{max-height:420px;overflow:auto;border:1px solid #EFF2F5;border-radius:.5rem}
        .items-scroll .table thead th{position:sticky;top:0;background:#fff;z-index:1}
        /* Match modal: remove hover + borders */
        .items-scroll .table tbody tr:hover{background-color:transparent!important;box-shadow:none}
        .items-scroll .table> :not(caption)>*>*{border-bottom:0!important}
        /* Align numeric inputs like modal */
        .qty-input,.koli-input{text-align:right}
        .meta{color:#6b7280;font-size:.875rem}
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Edit Pengiriman',
        'breadcrumbs' => ['Admin', 'Riwayat Pengiriman', 'Edit'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body p-6 p-lg-10">
                <div class="fw-bolder fs-5 mb-1">Informasi Pengiriman</div>
                <div class="meta">Kode: {{ $shipment->code ?? '-' }} | Ref: {{ $referenceCode ?? '-' }}</div>
                <div class="meta">Asal: {{ $fromWarehouse ?? '-' }}</div>
                <div class="meta mb-6">Tujuan: {{ $toWarehouse ?? '-' }}</div>

                <form action="{{ route('admin.riwayat-pengiriman.update', $shipment->id) }}" method="POST" id="shipment_edit_form">
                    @csrf
                    @method('PUT')

                            <div class="row g-4">
                                 <div class="col-md-6">
                                    <label class="form-label">Pilih Kendaraan (Opsional)</label>
                                    <select class="form-select form-select-solid" id="select_vehicle" data-control="select2">
                                        <option value="">-- Pilih Kendaraan --</option>
                                        @isset($vehicles)
                                            @foreach($vehicles as $vehicle)
                                                <option value="{{ $vehicle->id }}" data-type="{{ $vehicle->type }}" data-license="{{ $vehicle->license_plate }}">{{ $vehicle->type .' - ' . $vehicle->model }} ({{ $vehicle->license_plate }})</option>
                                            @endforeach
                                        @endisset
                                    </select>
                                </div>
                                 <div class="col-md-6">
                                    <label class="form-label">Pilih Karyawan (Opsional)</label>
                                    <select class="form-select form-select-solid" id="select_employee" data-control="select2">
                                        <option value="">-- Pilih Karyawan --</option>
                                        @isset($employees)
                                            @foreach($employees as $employee)
                                                <option value="{{ $employee->id }}" data-name="{{ $employee->name }}" data-contact="{{ $employee->phone }}">{{ $employee->name }}</option>
                                            @endforeach
                                        @endisset
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Tanggal Kirim</label>
                                    <input type="text" id="shipping_date" class="form-control form-control-solid @error('shipping_date') is-invalid @enderror" name="shipping_date" value="{{ old('shipping_date', $shipment->shipping_date ? \Carbon\Carbon::parse($shipment->shipping_date)->format('Y-m-d') : '') }}" required>
                                    @error('shipping_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                               
                                <div class="col-md-6">
                                    <label class="form-label required">Tipe Kendaraan</label>
                                    <input type="text" id="vehicle_type" class="form-control form-control-solid @error('vehicle_type') is-invalid @enderror" name="vehicle_type" value="{{ old('vehicle_type', $shipment->vehicle_type) }}" required>
                                    @error('vehicle_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Plat Nomor</label>
                                    <input type="text" id="license_plate" class="form-control form-control-solid @error('license_plate') is-invalid @enderror" name="license_plate" value="{{ old('license_plate', $shipment->license_plate) }}" required>
                                    @error('license_plate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                               
                                <div class="col-md-6">
                                    <label class="form-label required">Nama Pengemudi</label>
                                    <input type="text" id="driver_name" class="form-control form-control-solid @error('driver_name') is-invalid @enderror" name="driver_name" value="{{ old('driver_name', $shipment->driver_name) }}" required>
                                    @error('driver_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kontak Pengemudi</label>
                                    <input type="text" id="driver_contact" class="form-control form-control-solid @error('driver_contact') is-invalid @enderror" name="driver_contact" value="{{ old('driver_contact', $shipment->driver_contact) }}">
                                    @error('driver_contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Deskripsi / Catatan</label>
                                    <textarea class="form-control form-control-solid @error('description') is-invalid @enderror" name="description" rows="2">{{ old('description', $shipment->description) }}</textarea>
                                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <hr class="my-6">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0">Item Dalam Pengiriman</h5>
                                <div id="summary" class="text-muted"></div>
                            </div>
                            <div class="items-scroll">
                                <table class="table align-middle table-sm table-borderless">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th width="200">Qty Kirim</th>
                                            <th width="180">Koli Kirim</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items_tbody">
                                        @foreach($shipment->itemDetails as $detail)
                                            @php
                                                $max = $lineMax[$detail->id] ?? ['max_qty' => $detail->quantity_shipped, 'max_koli' => $detail->koli_shipped];
                                                $ratio = max(1, (float) optional($detail->item)->koli);
                                                $currentQty = old("items.$detail->id.quantity", $detail->quantity_shipped);
                                                $currentKoli = old("items.$detail->id.koli", $detail->koli_shipped);
                                            @endphp
                                            <tr data-koli-ratio="{{ $ratio }}" data-max-qty="{{ $max['max_qty'] }}" data-max-koli="{{ $max['max_koli'] }}">
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="badge badge-sku">{{ optional($detail->item)->sku ?? '-' }}</span>
                                                            <span class="fw-semibold">{{ optional($detail->item)->nama_barang ?? ($detail->item->name ?? '-') }}</span>
                                                        </div>
                                                        <div class="meta">Max: {{ number_format($max['max_qty'], 0) }} Qty • {{ number_format($max['max_koli'], 2) }} Koli</div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="number" step="any" class="form-control form-control-sm form-control-solid qty-input @error('items.'.$detail->id.'.quantity') is-invalid @enderror" name="items[{{ $detail->id }}][quantity]" value="{{ $currentQty }}" min="0" max="{{ $max['max_qty'] }}" placeholder="Qty">
                                                    <div class="text-muted small mt-1">Maks: {{ number_format($max['max_qty'], 0) }} Qty</div>
                                                    @error('items.'.$detail->id.'.quantity')<div class="text-danger small">{{ $message }}</div>@enderror
                                                </td>
                                                <td>
                                                    <input type="number" step="any" class="form-control form-control-sm form-control-solid koli-input @error('items.'.$detail->id.'.koli') is-invalid @enderror" name="items[{{ $detail->id }}][koli]" value="{{ $currentKoli }}" min="0" max="{{ $max['max_koli'] }}" placeholder="Koli">
                                                    <div class="text-muted small mt-1">Maks: {{ number_format($max['max_koli'], 2) }} Koli</div>
                                                    @error('items.'.$detail->id.'.koli')<div class="text-danger small">{{ $message }}</div>@enderror
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm form-control-solid" name="items[{{ $detail->id }}][description]" value="{{ old('items.'.$detail->id.'.description', $detail->description) }}" placeholder="Keterangan">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between mt-6">
                                <a href="{{ route('admin.riwayat-pengiriman.index') }}" class="btn btn-light">Batal</a>
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function(){
        const tbody = document.getElementById('items_tbody');
        const summary = document.getElementById('summary');
        const form = document.getElementById('shipment_edit_form');
        const shipDate = document.getElementById('shipping_date');
        const selectVehicle = document.getElementById('select_vehicle');
        const selectEmployee = document.getElementById('select_employee');

        if (selectVehicle) {
            selectVehicle.addEventListener('change', function(){
                const opt = this.options[this.selectedIndex];
                const type = opt ? opt.getAttribute('data-type') : '';
                const license = opt ? opt.getAttribute('data-license') : '';
                const vt = document.getElementById('vehicle_type');
                const lp = document.getElementById('license_plate');
                if (vt) vt.value = type || '';
                if (lp) lp.value = license || '';
            });
        }
        if (selectEmployee) {
            selectEmployee.addEventListener('change', function(){
                const opt = this.options[this.selectedIndex];
                const name = opt ? opt.getAttribute('data-name') : '';
                const contact = opt ? opt.getAttribute('data-contact') : '';
                const dn = document.getElementById('driver_name');
                const dc = document.getElementById('driver_contact');
                if (dn) dn.value = name || '';
                if (dc) dc.value = contact || '';
            });
        }

        function updateSummary(){
            let totalQty = 0, totalKoli = 0, count = 0;
            tbody.querySelectorAll('tr').forEach(tr => {
                const qty = parseFloat(tr.querySelector('.qty-input')?.value || '0');
                const koli = parseFloat(tr.querySelector('.koli-input')?.value || '0');
                totalQty += isNaN(qty) ? 0 : qty;
                totalKoli += isNaN(koli) ? 0 : koli;
                count += 1;
            });
            summary.textContent = `${count} item • Total: ${totalQty} Qty | ${totalKoli} Koli`;
        }

        function clamp(val, min, max){
            val = isNaN(val) ? 0 : val;
            if (typeof max === 'number') val = Math.min(val, max);
            if (typeof min === 'number') val = Math.max(val, min);
            return val;
        }

        tbody.addEventListener('input', function(e){
            const target = e.target;
            const tr = target.closest('tr');
            if (!tr) return;
            const ratio = parseFloat(tr.getAttribute('data-koli-ratio')) || 1;
            const maxQty = parseFloat(tr.getAttribute('data-max-qty'));
            const maxKoli = parseFloat(tr.getAttribute('data-max-koli'));
            if (target.classList.contains('qty-input')){
                let q = parseFloat(target.value);
                q = clamp(q, 0, maxQty);
                target.value = q;
                const koliInput = tr.querySelector('.koli-input');
                if (koliInput){
                    const newKoli = ratio > 0 ? (q / ratio) : 0;
                    koliInput.value = newKoli.toFixed(2);
                }
            }
            if (target.classList.contains('koli-input')){
                let k = parseFloat(target.value);
                k = clamp(k, 0, maxKoli);
                target.value = k;
                const qtyInput = tr.querySelector('.qty-input');
                if (qtyInput){
                    let newQty = k * ratio;
                    newQty = clamp(newQty, 0, maxQty);
                    qtyInput.value = newQty.toFixed(2);
                }
            }
            updateSummary();
        });

        // Removed plus/minus stepper controls (match modal behavior)

        updateSummary();

        // Init flatpickr for shipping_date if available
        if (window.flatpickr && shipDate) {
            flatpickr(shipDate, { dateFormat: 'Y-m-d' });
        }

        // SweetAlert confirm on submit
        if (form && window.Swal) {
            form.addEventListener('submit', function(e){
                e.preventDefault();
                Swal.fire({
                    text: 'Apakah Anda yakin ingin menyimpan perubahan pengiriman ini?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Simpan',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary',
                        cancelButton: 'btn fw-bold btn-active-light-primary'
                    }
                }).then(function(result){
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        }
    })();
</script>
@endpush




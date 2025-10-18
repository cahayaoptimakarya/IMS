@props(['delay' => '0.1s', 'icon' => 'box', 'iconBg' => 'primary', 'value' => '-', 'label' => '', 'bucket' => null])

<div class="col-xl-3">
    <div class="card card-xl-stretch mb-xl-8 summary-card card-animate {{ $bucket ? 'cursor-pointer' : '' }}"
         style="animation-delay: {{ $delay }};"
         @if($bucket)
            role="button"
            tabindex="0"
            data-bs-toggle="modal"
            data-bs-target="#stockBucketsModal"
            data-bucket="{{ $bucket }}"
         @endif
    >
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="symbol symbol-50px me-5">
                    <div class="symbol-label bg-light-{{ $iconBg }}">
                        <i class="fas fa-{{ $icon }} text-{{ $iconBg }} fs-2x"></i>
                    </div>
                </div>
                <div>
                    <div class="text-dark fw-bolder fs-2">{{ $value }}</div>
                    <div class="text-muted fw-bold">{{ $label }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

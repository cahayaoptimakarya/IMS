@props(['delay' => '0.1s', 'icon' => 'box', 'iconBg' => 'primary', 'value' => '-', 'label' => '', 'bucket' => null, 'meta' => null, 'valueId' => null, 'metaId' => null, 'wrapClass' => ''])

<div class="col-xl-3 {{ $wrapClass }}">
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
                    <div class="text-dark fw-bolder fs-2" @if($valueId) id="{{ $valueId }}" @endif>{{ $value }}</div>
                    <div class="text-muted fw-bold">{{ $label }}</div>
                    @if($meta)
                        <div class="text-muted fs-8 mt-1" @if($metaId) id="{{ $metaId }}" @endif>{{ $meta }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

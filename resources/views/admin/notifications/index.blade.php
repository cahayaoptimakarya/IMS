@extends('layouts.app')

@section('content')
<div class="post d-flex flex-column-fluid" id="kt_post">
  <div id="kt_content_container" class="container-xxl">
    <div class="card">
      <div class="card-header align-items-center">
        <h3 class="card-title">Notifikasi</h3>
        <div class="card-toolbar">
          <form action="{{ route('admin.notifications.read-all') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-sm btn-light-primary">Tandai semua terbaca</button>
          </form>
        </div>
      </div>
      <div class="card-body">
        <div class="d-flex mb-5 gap-2">
          <a href="{{ route('admin.notifications.index', ['filter' => 'all']) }}" class="btn btn-sm {{ $filter==='all' ? 'btn-primary' : 'btn-light' }}">Semua</a>
          <a href="{{ route('admin.notifications.index', ['filter' => 'unread']) }}" class="btn btn-sm {{ $filter==='unread' ? 'btn-primary' : 'btn-light' }}">Belum dibaca</a>
          <a href="{{ route('admin.notifications.index', ['filter' => 'read']) }}" class="btn btn-sm {{ $filter==='read' ? 'btn-primary' : 'btn-light' }}">Sudah dibaca</a>
        </div>

        @forelse($notifications as $notification)
          @php
            $data = $notification->data ?? [];
            $event = $data['event'] ?? '';
            $message = $data['message'] ?? $notification->type;
            $trId = $data['transfer_request_id'] ?? null;
            $shipmentId = $data['shipment_id'] ?? null;
            $grId = $data['goods_receipt_id'] ?? null;
          @endphp
          <div class="d-flex justify-content-between align-items-start border rounded p-4 mb-3 {{ is_null($notification->read_at) ? 'bg-light' : '' }}">
            <div>
              <div class="fw-semibold">{{ $message }}</div>
              <div class="text-muted fs-8">{{ $notification->created_at->diffForHumans() }}</div>
              <div class="mt-2 d-flex flex-wrap gap-2">
                @if($trId)
                  <a class="badge badge-light-primary" href="{{ route('admin.stok-masuk.request-transfer.show', $trId) }}">Lihat Permintaan Transfer</a>
                @endif
                @if($shipmentId)
                  <a class="badge badge-light-info" href="{{ route('admin.riwayat-pengiriman.show', $shipmentId) }}">Lihat Pengiriman</a>
                @endif
                @if($grId)
                  <a class="badge badge-light-success" href="{{ route('admin.stok-masuk.penerimaan-barang.show', $grId) }}">Lihat Penerimaan</a>
                @endif
              </div>
            </div>
            <div>
              @if(is_null($notification->read_at))
              <form action="{{ route('admin.notifications.read', $notification) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-light">Tandai terbaca</button>
              </form>
              @else
                <span class="badge badge-light">Terbaca</span>
              @endif
            </div>
          </div>
        @empty
          <div class="text-center py-10 text-muted">Tidak ada notifikasi.</div>
        @endforelse

        <div class="mt-5">
          {{ $notifications->links() }}
        </div>
      </div>
    </div>
  </div>
</div>
@endsection


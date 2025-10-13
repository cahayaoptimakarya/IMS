<div id="kt_header" class="header align-items-stretch" data-kt-sticky="true" data-kt-sticky-name="header"
    data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <!--begin::Container-->
    <div class="container-xxl d-flex align-items-center">
        <!--begin::Heaeder menu toggle-->
        <div class="d-flex topbar align-items-center d-lg-none ms-n2 me-3" title="Show aside menu">
            <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px"
                id="kt_header_menu_mobile_toggle">
                <!--begin::Svg Icon | path: icons/duotune/abstract/abs015.svg-->
                <span class="svg-icon svg-icon-2x">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none">
                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z"
                            fill="black" />
                        <path opacity="0.3"
                            d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z"
                            fill="black" />
                    </svg>
                </span>
                <!--end::Svg Icon-->
            </div>
        </div>
        <!--end::Heaeder menu toggle-->
        <!--begin::Header Logo-->
        <div class="header-logo me-5 me-md-10 flex-grow-1 flex-lg-grow-0">
            <a href="/admin/dashboard">
                <img alt="Logo" src="/metronic/assets/media/logos/logo-demo2.png" class="logo-default h-25px" />
                <img alt="Logo" src="/metronic/assets/media/logos/logo-demo2.png"
                    class="logo-sticky h-25px" />
            </a>
        </div>
        <!--end::Header Logo-->
        <!--begin::Wrapper-->
        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
            <!--begin::Navbar-->
            <div class="d-flex align-items-stretch" id="kt_header_nav">
                <!--begin::Menu wrapper-->
                <div class="header-menu align-items-stretch" data-kt-drawer="true" data-kt-drawer-name="header-menu"
                    data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true"
                    data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start"
                    data-kt-drawer-toggle="#kt_header_menu_mobile_toggle" data-kt-swapper="true"
                    data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_body', lg: '#kt_header_nav'}">


                    {{-- begin Dashboard menu --}}

                    {{-- End Dashboard menu --}}
                    <!--begin::Menu-->
                    <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch"
                        id="#kt_header_menu" data-kt-menu="true">
                        @include('layouts.partials._menu')
                    </div>
                    <!--end::Menu-->
                </div>
                <!--end::Menu wrapper-->
            </div>
            <!--end::Navbar-->
            <!--begin::Topbar-->
            <div class="d-flex align-items-stretch flex-shrink-0">
                <!--begin::Toolbar wrapper-->
                <div class="topbar d-flex align-items-stretch flex-shrink-0">
                    <!--begin::Notifications-->
                    <div class="d-flex align-items-center me-2">
                        @php
                            $unreadCount = auth()->user()->unreadNotifications()->count();
                            $latestNotifications = auth()->user()->notifications()->latest()->limit(10)->get();
                        @endphp
                        <div class="btn btn-icon btn-active-light-primary position-relative w-30px h-30px w-md-40px h-md-40px"
                             data-kt-menu-trigger="click" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                            <!-- bell icon -->
                            <span class="svg-icon svg-icon-2x">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 22C13.1 22 14 21.1 14 20H10C10 21.1 10.9 22 12 22ZM18 16V11C18 7.93 16.36 5.36 13.5 4.68V4C13.5 3.17 12.83 2.5 12 2.5C11.17 2.5 10.5 3.17 10.5 4V4.68C7.63 5.36 6 7.92 6 11V16L4 18V19H20V18L18 16Z" fill="black"/>
                                </svg>
                            </span>
                            @if($unreadCount > 0)
                            <span class="badge badge-circle bg-danger position-absolute top-0 end-0 translate-middle">{{ $unreadCount }}</span>
                            @endif
                        </div>
                        <!--begin::Dropdown-->
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-7 w-375px"
                             data-kt-menu="true">
                            <div class="px-5 d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bolder">Notifikasi</span>
                                <a href="{{ route('admin.notifications.read-all') }}" onclick="event.preventDefault(); this.nextElementSibling.submit();" class="text-primary fs-8">Tandai semua terbaca</a>
                                <form action="{{ route('admin.notifications.read-all') }}" method="POST" class="d-none">@csrf</form>
                            </div>
                            <div class="separator my-2"></div>
                            <div class="scroll-y mh-300px px-5">
                                @forelse($latestNotifications as $n)
                                    @php $data = $n->data ?? []; @endphp
                                    <div class="d-flex align-items-start mb-4 {{ is_null($n->read_at) ? '' : 'opacity-75' }}">
                                        <div class="symbol symbol-30px me-3">
                                            <span class="symbol-label bg-light-primary">
                                                <span class="svg-icon svg-icon-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 22C13.1 22 14 21.1 14 20H10C10 21.1 10.9 22 12 22ZM18 16V11C18 7.93 16.36 5.36 13.5 4.68V4C13.5 3.17 12.83 2.5 12 2.5C11.17 2.5 10.5 3.17 10.5 4V4.68C7.63 5.36 6 7.92 6 11V16L4 18V19H20V18L18 16Z" fill="black"/></svg>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $data['message'] ?? $n->type }}</div>
                                            <div class="text-muted fs-9">{{ $n->created_at->diffForHumans() }}</div>
                                            <div class="mt-1 d-flex gap-2 flex-wrap">
                                                @if(($data['transfer_request_id'] ?? null))
                                                    <a class="link-primary fs-9" href="{{ route('admin.stok-masuk.request-transfer.show', $data['transfer_request_id']) }}">Lihat Permintaan</a>
                                                @endif
                                                @if(($data['shipment_id'] ?? null))
                                                    <a class="link-info fs-9" href="{{ route('admin.riwayat-pengiriman.show', $data['shipment_id']) }}">Lihat Pengiriman</a>
                                                @endif
                                                @if(($data['goods_receipt_id'] ?? null))
                                                    <a class="link-success fs-9" href="{{ route('admin.stok-masuk.penerimaan-barang.show', $data['goods_receipt_id']) }}">Lihat Penerimaan</a>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="ms-2">
                                            @if(is_null($n->read_at))
                                                <a href="{{ route('admin.notifications.read', $n) }}" onclick="event.preventDefault(); this.nextElementSibling.submit();" class="btn btn-xs btn-light">Tandai baca</a>
                                                <form action="{{ route('admin.notifications.read', $n) }}" method="POST" class="d-none">@csrf</form>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted text-center py-10">Tidak ada notifikasi.</div>
                                @endforelse
                            </div>
                            <div class="separator my-2"></div>
                            <div class="px-5"><a class="btn btn-sm btn-light-primary w-100" href="{{ route('admin.notifications.index') }}">Lihat semua</a></div>
                        </div>
                        <!--end::Dropdown-->
                    </div>
                    <!--end::Notifications-->

                    <!--begin::User-->
                    <div class="d-flex align-items-center me-n3 ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
                        <!--begin::Menu wrapper-->
                        <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px"
                            data-kt-menu-trigger="click" data-kt-menu-attach="parent"
                            data-kt-menu-placement="bottom-end">
                            <img class="h-30px w-30px rounded" src="/metronic/assets/media/avatars/blank.png"
                                alt="" />
                        </div>
                        <!--begin::Menu-->
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px"
                            data-kt-menu="true">
                            <!--begin::Menu item-->
                            <div class="menu-item px-3">
                                <div class="menu-content d-flex align-items-center px-3">
                                    <!--begin::Avatar-->
                                    <div class="symbol symbol-50px me-5">
                                        <img alt="Logo" src="/metronic/assets/media/avatars/profile.png" />
                                    </div>
                                    <!--end::Avatar-->
                                    <!--begin::Username-->
                                    <div class="d-flex flex-column">
                                        <div class="fw-bolder d-flex align-items-center fs-5">
                                            {{ auth()->user()->name }}
                                            <span
                                                class="badge badge-light-success fw-bolder fs-8 px-2 py-1 ms-2">Pro</span>
                                        </div>
                                        <a href="#"
                                            class="fw-bold text-muted text-hover-primary fs-9">{{ auth()->user()->email }}</a>
                                    </div>
                                    <!--end::Username-->
                                </div>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu separator-->
                            <div class="separator my-2"></div>
                            <!--end::Menu separator-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <a href="{{ route('admin.user-activities.index') }}" class="menu-link px-5">Aktivitas User</a>
                            </div>
                            <!--end::Menu item-->
                            <div class="separator my-2"></div>
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <a href="{{ route('logout') }}"
                                    onclick="event.preventDefault();
																	 document.getElementById('logout-form').submit();"
                                    class="menu-link px-5">
                                    Sign Out
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST"
                                    class="d-none">
                                    @csrf
                                </form>
                            </div>
                            <!--end::Menu item-->
                        </div>
                        <!--end::Menu-->
                        <!--end::Menu wrapper-->
                    </div>
                    <!--end::User -->
                    <!--begin::Aside mobile toggle-->
                    <!--end::Aside mobile toggle-->
                </div>
                <!--end::Toolbar wrapper-->
            </div>
            <!--end::Topbar-->
        </div>
        <!--end::Wrapper-->
    </div>
    <!--end::Container-->
</div>

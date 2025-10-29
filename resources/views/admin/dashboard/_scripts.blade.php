<script>
    $(document).ready(function() {
        var ctx = document.getElementById('stockChart').getContext('2d');

        var gradientIn = ctx.createLinearGradient(0, 0, 0, 400);
        gradientIn.addColorStop(0, 'rgba(75, 192, 192, 0.5)');
        gradientIn.addColorStop(1, 'rgba(75, 192, 192, 0)');

        var gradientOut = ctx.createLinearGradient(0, 0, 0, 400);
        gradientOut.addColorStop(0, 'rgba(255, 99, 132, 0.5)');
        gradientOut.addColorStop(1, 'rgba(255, 99, 132, 0)');

        window.stockChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($stockChartData['labels'] ?? []) !!},
                datasets: [{
                    label: 'Stok Masuk',
                    data: {!! json_encode($stockChartData['stock_in'] ?? []) !!},
                    backgroundColor: gradientIn,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    tension: 0.4
                }, {
                    label: 'Stok Keluar',
                    data: {!! json_encode($stockChartData['stock_out'] ?? []) !!},
                    backgroundColor: gradientOut,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    tension: 0.4
                }]
            },
            options: {
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuad'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(200, 200, 200, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 12
                        },
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.formattedValue}`;
                            }
                        }
                    }
                }
            }
        });

        // ====== Modal Stock Buckets ======
        var stockBucketsData = null;
        var stockBucketsLoading = false;
        var currentWarehouseId = @json($currentWarehouseId ?? null);
        var stockBucketsUrl = "{{ route('admin.dashboard.stock-buckets') }}" + (currentWarehouseId ? ('?warehouse_id=' + currentWarehouseId) : '');
        var stockBucketsTimeout = null;

        function setBadgeCounts(data){
            $('#badge-out').text(data.out_of_stock.count);
            $('#badge-low').text(data.low_stock.count);
            $('#badge-safe').text(data.safe.count);
            $('#badge-over').text(data.over_stock.count);
        }

        function startLoading(ids){
            ids.forEach(function(id){ $('#loading-' + id).removeClass('d-none'); });
        }
        function stopLoading(ids){
            ids.forEach(function(id){ $('#loading-' + id).addClass('d-none'); });
        }

        function numberFmt(n){
            try { return parseFloat(n).toLocaleString('id-ID'); } catch(e) { return n; }
        }

        function renderRows(tbodyId, items){
            var $tbody = $('#tbody-' + tbodyId);
            $tbody.empty();
            if(!items || items.length === 0){
                $tbody.append('<tr class="no-data"><td colspan="6" class="text-center text-muted">Tidak ada data</td></tr>');
                $('#count-' + tbodyId).text('0 data');
                return;
            }
            items.forEach(function(it){
                var tr = `
                    <tr>
                        <td class="fw-bold">${it.sku ?? '-'}</td>
                        <td>${it.nama_barang ?? '-'}</td>
                        <td>${it.warehouse ?? '-'}</td>
                        <td class="text-end">${numberFmt(it.qty ?? 0)}</td>
                        <td class="text-end">${numberFmt(it.koli ?? 0)}</td>
                        <td class="text-end">${numberFmt(it.threshold ?? 0)}</td>
                    </tr>`;
                $tbody.append(tr);
            });
            $('#count-' + tbodyId).text(items.length + ' data');
        }

        function populateTables(data){
            setBadgeCounts(data);
            renderRows('out', data.out_of_stock.items);
            renderRows('low', data.low_stock.items);
            renderRows('safe', data.safe.items);
            renderRows('over', data.over_stock.items);
            // Ensure any spinner is hidden after rendering
            stopLoading(['out','low','safe','over']);
        }

        function fetchStockBuckets(done){
            if (stockBucketsLoading) return; // avoid duplicate requests
            stockBucketsLoading = true;
            // Safety timeout to ensure spinner not stuck
            if (stockBucketsTimeout) clearTimeout(stockBucketsTimeout);
            stockBucketsTimeout = setTimeout(function(){
                stopLoading(['out','low','safe','over']);
            }, 15000);

            $.getJSON(stockBucketsUrl)
                .done(function(resp){
                    stockBucketsData = resp;
                    populateTables(resp);
                })
                .fail(function(){
                    ['out','low','safe','over'].forEach(function(id){
                        var $tbody = $('#tbody-' + id);
                        $tbody.html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat data</td></tr>');
                    });
                })
                .always(function(){
                    stockBucketsLoading = false;
                    stopLoading(['out','low','safe','over']);
                    if (stockBucketsTimeout) { clearTimeout(stockBucketsTimeout); stockBucketsTimeout = null; }
                    if (typeof done === 'function') done();
                });
        }

        var stockModal = document.getElementById('stockBucketsModal');
        if (stockModal) {
            stockModal.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                var targetBucket = trigger && trigger.getAttribute('data-bucket');

                var tabIdMap = {
                    'out_of_stock': '#tab-out',
                    'low_stock': '#tab-low',
                    'safe': '#tab-safe',
                    'over_stock': '#tab-over'
                };
                // Default to out_of_stock when not provided
                var tabSelector = tabIdMap[targetBucket] || '#tab-out';
                var tabBtn = document.querySelector(tabSelector);
                if (tabBtn) {
                    var tab = new bootstrap.Tab(tabBtn);
                    tab.show();
                }

                if (!stockBucketsData) {
                    // show spinner only for active tab for better UX
                    var activePane = document.querySelector('.tab-pane.active');
                    var activeId = activePane ? activePane.id.replace('pane-','') : 'out';
                    startLoading([activeId]);
                    fetchStockBuckets();
                } else {
                    populateTables(stockBucketsData);
                    stopLoading(['out','low','safe','over']);
                }
            });

            // Ensure spinner never remains after hide
            stockModal.addEventListener('hidden.bs.modal', function(){
                stopLoading(['out','low','safe','over']);
            });

            // When switching tabs, if data already loaded just ensure table populated
            var tabElList = [].slice.call(document.querySelectorAll('#stockTabs button[data-bs-toggle="tab"]'));
            tabElList.forEach(function(tabEl){
                tabEl.addEventListener('shown.bs.tab', function(){
                    if (stockBucketsData) {
                        populateTables(stockBucketsData);
                        // hide spinner for the tab switched to
                        var target = tabEl.getAttribute('data-bs-target') || '';
                        var id = target.replace('#pane-','');
                        if (id) stopLoading([id]);
                    }
                });
            });
        }

        // ====== AJAX Warehouse Filter ======
        function setElText(id, text){
            var el = document.getElementById(id);
            if (el) el.textContent = text;
        }
        function fmt(n){ try { return Number(n).toLocaleString('id-ID'); } catch(e){ return n; } }
        function toggleMode(mode){
            var showSys = (mode === 'system');
            $('.sys-only').toggleClass('d-none', !showSys);
            $('.wh-only').toggleClass('d-none', showSys);
        }

        function formatDateHuman(dateStr){
            try {
                var d = new Date(dateStr);
                var now = new Date();
                var sameDay = d.toDateString() === now.toDateString();
                if (sameDay) return 'Hari ini';
                return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            } catch(e){ return dateStr; }
        }

        function renderRecentMovements(items){
            var wrap = $('#dash-recent-movements');
            wrap.empty();
            if (!items || items.length === 0){ wrap.append('<div class="text-muted">Tidak ada pergerakan terakhir.</div>'); return; }
            items.forEach(function(m){
                var isIn = (m.quantity >= 0);
                var arrow = isIn ? 'arrow-down' : 'arrow-up';
                var color = isIn ? 'success' : 'danger';
                var qty = fmt(m.quantity || 0);
                var dateText = formatDateHuman(m.date);
                wrap.append(
                    '<div class="d-flex align-items-center mb-5">\
                        <div class="symbol symbol-35px me-4">\
                            <div class="symbol-label bg-light-' + color + '">\
                                <i class="fas fa-' + arrow + ' text-' + color + '"></i>\
                            </div>\
                        </div>\
                        <div class="flex-grow-1">\
                            <p class="text-gray-800 fw-bolder fs-6 mb-0">' + (m.item || 'Item') + ' (' + (m.type || '') + ')</p>\
                            <span class="text-muted fw-bold">' + qty + ' • ' + dateText + '</span>\
                        </div>\
                    </div>'
                );
            });
        }
        function renderTopLowStocks(items){
            var tbody = $('#dash-toplow-tbody');
            tbody.empty();
            if (!items || items.length === 0){ tbody.append('<tr><td colspan="3" class="text-center text-muted">Tidak ada data</td></tr>'); return; }
            items.forEach(function(inv){
                tbody.append('<tr>\
                    <td class="fw-bolder text-gray-800">' + (inv.item || '-') + '</td>\
                    <td class="text-muted">' + (inv.sku || '-') + '</td>\
                    <td class="text-end fw-bolder">' + fmt(inv.quantity || 0) + '</td>\
                </tr>');
            });
        }
        function updateChart(data){
            if (!window.stockChart) return;
            window.stockChart.data.labels = data.labels || [];
            if (window.stockChart.data.datasets && window.stockChart.data.datasets.length >= 2){
                window.stockChart.data.datasets[0].data = data.stock_in || [];
                window.stockChart.data.datasets[1].data = data.stock_out || [];
            }
            window.stockChart.update();
        }

        function applyDashboardData(resp){
            // Toggle system vs warehouse summary blocks
            toggleMode(resp.mode);
            if (resp.mode === 'system') {
                setElText('dash-total-users', fmt(resp.totalUsers || 0));
                setElText('dash-total-warehouses', fmt(resp.totalWarehouses || 0));
            } else {
                setElText('dash-pending-transfers', fmt(resp.pendingTransfers || 0));
                setElText('dash-low-stock-items-card', fmt(resp.lowStockItems || 0));
            }
            // Common summaries
            setElText('dash-today-stock-in', fmt(resp.todayStockIn || 0));
            setElText('dash-today-stock-in-koli', 'Koli: ' + fmt(resp.todayStockInKoli || 0));
            setElText('dash-today-stock-out', fmt(resp.todayStockOut || 0));
            setElText('dash-today-stock-out-koli', 'Koli: ' + fmt(resp.todayStockOutKoli || 0));

            // Buckets on cards
            setElText('dash-zero-stock', fmt(resp.zeroStockItems || 0));
            setElText('dash-low-stock', fmt(resp.lowStockItems || 0));
            setElText('dash-optimal-stock', fmt(resp.optimalStockItems || 0));
            setElText('dash-over-stock', fmt(resp.overStockItems || 0));

            // Stock in statuses
            setElText('dash-stockin-requested', fmt(resp.stockInRequested || 0));
            setElText('dash-stockin-shipped', fmt(resp.stockInShipped || 0));
            setElText('dash-stockin-completed', fmt(resp.stockInCompleted || 0));

            // Total SKUs
            setElText('dash-total-skus', fmt(resp.totalSkus || 0));

            // Chart
            updateChart(resp.stockChartData || {labels:[],stock_in:[],stock_out:[]});

            // Lists
            renderTopLowStocks(resp.topLowStocks || []);
            renderRecentMovements(resp.recentMovements || []);
        }

        function setFilterLoading(loading){
            $('#warehouseFilterLoading').toggleClass('d-none', !loading);
            $('#warehouseFilterSelect').prop('disabled', !!loading);
        }

        $('#warehouseFilterReset').on('click', function(){
            $('#warehouseFilterSelect').val('');
            $('#warehouseFilterSelect').trigger('change');
        });

        $('#warehouseFilterSelect').on('change', function(){
            var wid = $(this).val();
            setFilterLoading(true);
            // Update modal URL context
            currentWarehouseId = wid || null;
            stockBucketsUrl = "{{ route('admin.dashboard.stock-buckets') }}" + (currentWarehouseId ? ('?warehouse_id=' + currentWarehouseId) : '');

            $.getJSON("{{ route('admin.dashboard.data') }}", { warehouse_id: wid })
                .done(function(resp){
                    applyDashboardData(resp);
                })
                .fail(function(){
                    if (window.toastr) toastr.error('Gagal memuat data dashboard'); else alert('Gagal memuat data dashboard');
                })
                .always(function(){
                    setFilterLoading(false);
                });
        });
    });
</script>

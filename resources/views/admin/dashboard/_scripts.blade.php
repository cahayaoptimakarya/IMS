<script>
    $(document).ready(function() {
        var ctx = document.getElementById('stockChart').getContext('2d');

        var gradientIn = ctx.createLinearGradient(0, 0, 0, 400);
        gradientIn.addColorStop(0, 'rgba(75, 192, 192, 0.5)');
        gradientIn.addColorStop(1, 'rgba(75, 192, 192, 0)');

        var gradientOut = ctx.createLinearGradient(0, 0, 0, 400);
        gradientOut.addColorStop(0, 'rgba(255, 99, 132, 0.5)');
        gradientOut.addColorStop(1, 'rgba(255, 99, 132, 0)');

        var stockChart = new Chart(ctx, {
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
        var stockBucketsUrl = "{{ route('admin.dashboard.stock-buckets') }}";
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
    });
</script>

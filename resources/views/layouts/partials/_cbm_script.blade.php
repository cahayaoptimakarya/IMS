{{-- Helper JS CBM bersama. Rumus harus sama dengan App\Support\Cbm & accessor Item::cbm_per_koli. --}}
@once
    @push('scripts')
        <script>
            window.ImsCbm = (function () {
                function toNum(v) {
                    var n = parseFloat(v);
                    return isNaN(n) ? 0 : n;
                }

                // CBM per koli (m³) dari P x L x T (cm); null jika dimensi belum lengkap.
                function perKoli(p, l, t) {
                    p = toNum(p); l = toNum(l); t = toNum(t);
                    if (p <= 0 || l <= 0 || t <= 0) return null;
                    return Math.round(p * l * t) / 1000000;
                }

                function perKoliFromItem(item) {
                    if (!item) return null;
                    return perKoli(item.panjang_koli, item.lebar_koli, item.tinggi_koli);
                }

                // CBM satu baris: koli sebagai acuan, fallback qty / isi per koli.
                function forLine(cbmPerKoli, ratio, qty, koli) {
                    if (cbmPerKoli === null || cbmPerKoli === undefined || cbmPerKoli === '' || isNaN(parseFloat(cbmPerKoli))) return null;
                    cbmPerKoli = parseFloat(cbmPerKoli);
                    koli = toNum(koli);
                    if (koli > 0) return koli * cbmPerKoli;
                    qty = toNum(qty);
                    ratio = Math.floor(toNum(ratio));
                    if (qty > 0 && ratio > 0) return qty * (Math.round(cbmPerKoli / ratio * 1e8) / 1e8);
                    return 0;
                }

                function format(v) {
                    if (v === null || v === undefined || isNaN(v)) return '-';
                    if (v > 0 && Math.round(v * 1000) === 0) {
                        return v.toLocaleString('id-ID', { maximumFractionDigits: 6 });
                    }
                    return v.toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
                }

                function formatNum(v, digits) {
                    return toNum(v).toLocaleString('id-ID', { maximumFractionDigits: digits });
                }

                // Tampilkan CBM baris ke elemen tujuan (+ keterangan per koli).
                function renderCell($cell, cbm, cbmPerKoli) {
                    if (!$cell || !$cell.length) return;
                    var $val = $cell.find('[data-cbm-value]');
                    var $hint = $cell.find('[data-cbm-hint]');
                    if (cbm === null) {
                        // Item tanpa dimensi koli: tidak ikut dihitung
                        $val.text('-');
                        $hint.text('');
                    } else {
                        $val.text(format(cbm) + ' m³');
                        $hint.text(format(parseFloat(cbmPerKoli)) + ' m³/koli');
                    }
                }

                // Isi kotak ringkasan (partial _cbm_summary).
                function renderSummary($box, s) {
                    if (!$box || !$box.length) return;
                    $box.find('[data-cbm-total-qty]').text(formatNum(s.qty, 2));
                    $box.find('[data-cbm-total-koli]').text(formatNum(s.koli, 2));
                    $box.find('[data-cbm-total]').text(format(s.cbm || 0));
                }

                // Gabungkan banyak trigger menjadi satu kali hitung, setelah handler lain selesai.
                function schedule(fn) {
                    if (fn.__cbmPending) return;
                    fn.__cbmPending = true;
                    setTimeout(function () {
                        fn.__cbmPending = false;
                        fn();
                    }, 0);
                }

                // Hitung ulang saat isi tabel berubah (input, pilih item, tambah/hapus baris).
                function watch(container, fn) {
                    var el = container && container.jquery ? container[0] : container;
                    if (!el) return;
                    var run = function () { schedule(fn); };
                    $(el).on('input change', run);
                    if (window.MutationObserver) {
                        new MutationObserver(run).observe(el, { childList: true });
                    }
                    fn();
                }

                return {
                    perKoli: perKoli,
                    perKoliFromItem: perKoliFromItem,
                    forLine: forLine,
                    format: format,
                    formatNum: formatNum,
                    renderCell: renderCell,
                    renderSummary: renderSummary,
                    schedule: schedule,
                    watch: watch
                };
            })();
        </script>
    @endpush
@endonce

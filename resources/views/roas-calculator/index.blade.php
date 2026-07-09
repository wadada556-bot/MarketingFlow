@extends('components.layouts.app')

@section('title', 'ROAS Calculator')

@push('styles')
    <link href="{{ asset('css/roas-calculator.css') }}" rel="stylesheet">
@endpush

@section('content')
    <div class="mb-4">
        <h4 class="mb-0" style="font-size:22px;font-weight:400;color:var(--md-on-surface)">ROAS Calculator</h4>
        <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">
            Simulasi profitabilitas iklan berdasarkan data varian dengan HPP tertinggi.
        </p>
    </div>

    @include('components.alert')

    <div class="calc-grid">
        <!-- PANEL KIRI (INPUT - LANGKAH PARAMETER IKLAN) -->
        <div>
            <div class="calc-panel-unified">
                <h3 class="mb-4" style="font-size: 16px; font-weight: 700; color: var(--md-on-surface); border-bottom: 2px solid var(--md-primary); padding-bottom: 8px;">
                    <i class="bi bi-sliders"></i> Konfigurasi & Parameter Iklan
                </h3>

                <!-- Langkah 1: Cari Produk -->
                <div class="calc-section">
                    <h4>🔍 Langkah 1: Cari Produk</h4>
                    <div class="d-flex gap-2">
                        <input type="text" id="inpProductId" class="form-control" style="font-family:var(--bs-font-monospace);" placeholder="Masukkan 19-digit Product ID">
                        <button type="button" class="btn btn-primary px-3" id="btnLookup" style="border-radius:var(--md-shape-sm)">Cari</button>
                    </div>

                    <!-- Empty State -->
                    <div id="productEmptyState" class="calc-empty-state">
                        <i class="bi bi-search"></i>
                        <h5>Belum ada produk yang dimuat</h5>
                        <p class="mb-0">Masukkan Product ID di atas untuk menarik data HPP & Harga Jual otomatis dari database.</p>
                    </div>

                    <!-- Skeleton Loader -->
                    <div id="productSkeleton" style="display:none;" class="p-3 bg-light rounded border mt-3">
                        <div class="skeleton-line title"></div>
                        <div class="skeleton-line sub mb-3"></div>
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line"></div>
                    </div>
                    
                    <!-- Product Info Box (Collapsible) -->
                    <div id="productInfoBox" style="display:none;" class="p-3 bg-light rounded border mt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary" id="badgeStore">TOKO</span>
                                <span class="fw-bold text-truncate" id="lblProductName" style="max-width: 250px; font-size:14px;" title="">Nama Produk</span>
                            </div>
                        </div>
                        <div style="font-size:12px; color:var(--md-on-surface-variant);">
                            HPP tertinggi dari varian: <strong id="lblVariantSku" class="text-dark">SKU</strong> — <span id="lblVariantLabel">Label</span>
                        </div>
                        
                        <!-- Collapsible Table Varian -->
                        <div class="variants-table-wrap mt-3" id="variantsTableWrap">
                            <div class="collapsible-header collapsed p-2" data-target="variantsTableContent">
                                <span class="fw-bold" style="font-size:12px; color:var(--md-primary);"><i class="bi bi-table"></i> Lihat Semua Varian</span>
                                <i class="bi bi-chevron-down chevron"></i>
                            </div>
                            <div class="collapsible-summary px-2 pb-2" id="variantsSummaryText">Klik untuk melihat breakdown semua varian produk.</div>
                            <div id="variantsTableContent" class="collapsible-content collapsed mt-1 px-2 pb-2">
                                <table class="variants-table">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>Variasi</th>
                                            <th>HPP</th>
                                            <th>Promo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="variantsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="calc-divider">

                <!-- Langkah 2: Harga & Modal Dasar -->
                <div class="calc-section">
                    <h4>💵 Langkah 2: Harga & Modal Dasar</h4>
                    <div class="calc-row">
                        <label>Harga Jual</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="hargaJual" class="form-control inp auto-calc" value="0">
                        </div>
                    </div>
                    <div class="calc-row">
                        <label>HPP / Modal</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="hpp" class="form-control inp auto-calc" value="0">
                        </div>
                    </div>
                    <div class="calc-row">
                        <label>Diskon Tambahan</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="diskon" class="form-control inp auto-calc" value="0">
                        </div>
                    </div>
                </div>

                <hr class="calc-divider">

                <!-- Langkah 3: Komponen Biaya (Collapsible) -->
                <div class="calc-section">
                    <div class="collapsible-header collapsed" data-target="feePanelContent">
                        <h4>⚙️ Langkah 3: Komponen Biaya (TikTok Shop)</h4>
                        <i class="bi bi-chevron-down chevron"></i>
                    </div>
                    <div class="collapsible-summary" id="feePanelSummary">Total platform fee: 36.5% + Rp 3.000</div>
                    
                    <div id="feePanelContent" class="collapsible-content collapsed">
                        <div style="font-size: 11px; font-weight: 700; color: var(--md-on-surface-variant); text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid var(--md-outline-variant); padding-bottom: 4px;">Biaya Variabel (Persentase)</div>
                        <div class="calc-row">
                            <label>Komisi Platform <i class="bi bi-info-circle" title="Biaya komisi tetap kategori produk TikTok Shop"></i></label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="feePlatform" class="form-control inp auto-calc" step="0.1" value="7.5">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="calc-row">
                            <label>Komisi Dinamis <i class="bi bi-info-circle" title="Komisi program afiliasi dinamis TikTok"></i></label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="feeDinamis" class="form-control inp auto-calc" step="0.1" value="8">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="calc-row">
                            <label>Growth Xtra <i class="bi bi-info-circle" title="Biaya keikutsertaan campaign/growth program"></i></label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="feeGrowth" class="form-control inp auto-calc" step="0.1" value="4">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="calc-row">
                            <label>Komisi Affiliasi</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="feeAffiliate" class="form-control inp auto-calc" step="0.1" value="7">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="calc-row">
                            <label>Biaya Operasional</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="feeOps" class="form-control inp auto-calc" step="0.1" value="10">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="calc-row">
                            <label>Biaya Lain-lain</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="feeLainPersen" class="form-control inp auto-calc" step="0.1" value="0">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                        <div style="font-size: 11px; font-weight: 700; color: var(--md-on-surface-variant); text-transform: uppercase; margin: 16px 0 8px; border-bottom: 1px solid var(--md-outline-variant); padding-bottom: 4px;">Biaya Tetap (Nominal)</div>
                        <div class="calc-row">
                            <label>Biaya Pemrosesan</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Rp</span>
                                <input type="number" id="feePemrosesanRp" class="form-control inp auto-calc" value="1250">
                            </div>
                        </div>
                        <div class="calc-row">
                            <label>Logistik/Ongkir</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Rp</span>
                                <input type="number" id="feeLogistikRp" class="form-control inp auto-calc" value="1250">
                            </div>
                        </div>
                        <div class="calc-row">
                            <label>Garansi</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Rp</span>
                                <input type="number" id="feeGaransiRp" class="form-control inp auto-calc" value="0">
                            </div>
                        </div>
                        <div class="calc-row">
                            <label>Biaya Packing</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Rp</span>
                                <input type="number" id="feePackingRp" class="form-control inp auto-calc" value="500">
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="calc-divider">

                <!-- Langkah 4: Target & Asumsi -->
                <div class="calc-section">
                    <h4>🎯 Langkah 4: Target & Asumsi</h4>
                    <div class="calc-row">
                        <label>Target Profit</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="targetProfitPersen" class="form-control inp auto-calc" step="0.1" value="10">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="calc-row">
                        <label>PPN PMSE <i class="bi bi-info-circle" title="PPN atas platform fee TikTok (11%)"></i></label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="ppnPersen" class="form-control inp auto-calc" step="0.1" value="11">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <hr style="border-color:var(--md-outline-variant); margin:20px 0;">
                    <div class="calc-row">
                        <label>ROAS Aktual (What-If)</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="roasAktual" class="form-control inp auto-calc" step="0.1" placeholder="Misal: 5.5">
                            <span class="input-group-text">x</span>
                        </div>
                    </div>
                    <p style="font-size:11px;color:var(--md-on-surface-variant);margin-top:-5px;margin-bottom:0;">
                        *Kosongkan jika hanya ingin melihat target. Isi untuk melihat simulasi profit riil.
                    </p>
                </div>
            </div>
        </div>

        <!-- PANEL KANAN (OUTPUT - HASIL ANALISIS PROFITABILITAS) -->
        <div id="outputColumn">
            <!-- Hero Results -->
            <div class="result-hero">
                <div class="lbl">TARGET ROAS IDEAL</div>
                <div class="big" id="outTargetRoas">---</div>
                <div class="sub">ROAS BEP: <span id="outRoasBep">---</span> &nbsp;|&nbsp; ACOS Ideal: <span id="outAcos">---</span></div>
            </div>

            <!-- Mini Stats -->
            <div class="mini-stats">
                <div class="mini-stat">
                    <div class="l">Harga Final</div>
                    <div class="v" id="outHargaFinal">---</div>
                </div>
                <div class="mini-stat">
                    <div class="l">Margin Kotor (Nominal)</div>
                    <div class="v" id="outMarginKotorRp">---</div>
                </div>
                <div class="mini-stat">
                    <div class="l">Budget Iklan Ideal</div>
                    <div class="v" id="outBudgetIdealRp">---</div>
                </div>
                <div class="mini-stat">
                    <div class="l">Potensi Untung (Net)</div>
                    <div class="v" id="outProfitNetRp">---</div>
                </div>
            </div>

            <!-- Hasil Analisis Profitabilitas (Unified Output Card) -->
            <div class="calc-panel-unified">
                <h3 class="mb-4" style="font-size: 16px; font-weight: 700; color: var(--md-on-surface); border-bottom: 2px solid var(--md-primary); padding-bottom: 8px;">
                    <i class="bi bi-bar-chart-line"></i> Hasil Analisis Profitabilitas
                </h3>

                <!-- Bagian A: Ringkasan Margin -->
                <div class="calc-output-section">
                    <h5>📊 Bagian A: Ringkasan Margin</h5>
                    <div class="calc-out"><span class="lbl">Margin Profit Dasar</span><span class="v" id="outMarginDasar">---</span></div>
                    <div class="calc-out"><span class="lbl">Total Potongan (Platform)</span><span class="v neg" id="outTotalPotongan">---</span></div>
                    <div class="calc-out"><span class="lbl">Margin Kotor</span><span class="v" id="outMarginKotor">---</span></div>
                    <div class="calc-out"><span class="lbl">Margin Bersih (Setelah PPN)</span><span class="v" id="outMarginBersih">---</span></div>
                </div>

                <hr class="calc-divider">

                <!-- Bagian B: Target & Budget Iklan -->
                <div class="calc-output-section">
                    <h5>🛡️ Bagian B: Target & Budget Iklan</h5>
                    <div class="calc-out"><span class="lbl">Target Profit Rp</span><span class="v" id="outTargetProfitRp">---</span></div>
                    <div class="calc-out"><span class="lbl">Max CPA / Budget Iklan Ideal</span><span class="v" id="outCpa">---</span></div>
                    <div class="calc-out"><span class="lbl">Target ACOS Ideal</span><span class="v" id="outTargetAcos">---</span></div>
                    
                    <div class="calc-out style-target-roas" style="background:var(--md-surface-container-high);padding:10px;border-radius:4px;margin-top:10px; display:flex; justify-content:space-between; align-items:center;">
                        <span class="lbl fw-bold text-dark">Target ROAS Ideal</span>
                        <span class="v pos" style="font-size:18px;" id="outTargetRoas2">---</span>
                    </div>
                    <div class="calc-out style-roas-bep" style="background:var(--md-surface-container-highest);padding:10px;border-radius:4px;margin-top:5px; display:flex; justify-content:space-between; align-items:center;">
                        <span class="lbl fw-bold">ROAS BEP (Titik Impas)</span>
                        <span class="v warn" style="font-size:16px;" id="outRoasBep2">---</span>
                    </div>
                </div>

                <hr class="calc-divider">

                <!-- Bagian C: Analisis Keuntungan -->
                <div class="calc-output-section">
                    <h5>💰 Bagian C: Analisis Keuntungan</h5>
                    <div class="calc-out"><span class="lbl">Profit per Order (Net)</span><span class="v pos" id="outProfitNetOrder">---</span></div>
                    <div class="calc-out"><span class="lbl">Persentase Profit (Net)</span><span class="v pos" id="outProfitNetPct">---</span></div>
                </div>

                <!-- Bagian D: Simulasi Aktual / What-If (Collapsible inside Unified Card) -->
                <div id="panelWhatIf" style="display:none; border-top:1px solid var(--md-outline-variant); padding-top:20px; margin-top:20px;">
                    <div class="calc-output-section">
                        <div class="collapsible-header" data-target="whatIfPanelContent">
                            <h5 class="mb-0">⚡ Bagian D: Simulasi Aktual (What-If)</h5>
                            <i class="bi bi-chevron-down chevron"></i>
                        </div>
                        
                        <div id="whatIfPanelContent" class="collapsible-content">
                            <div class="calc-out"><span class="lbl">Budget Iklan Aktual</span><span class="v" id="wiBudget">---</span></div>
                            <div class="calc-out"><span class="lbl">Profit Aktual (per Order)</span><span class="v" id="wiProfit">---</span></div>
                            <div class="calc-out mt-2 mb-2">
                                <span class="lbl">Status</span>
                                <span id="wiStatus" class="chip">?</span>
                            </div>
                            <div id="wiSaran" style="font-size:12px; color:var(--md-on-surface-variant); line-height:1.4; margin-top:8px; padding:8px; background:var(--md-surface-container-high); border-radius:4px;">
                                Saran akan muncul di sini.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TABEL SIMULASI ROAS 1-20 (Collapsible Panel) -->
    <div class="calc-panel-unified mb-4">
        <div class="collapsible-header collapsed" data-target="simTableContent">
            <h4 class="mb-0" style="color:var(--md-on-surface); text-transform:none; font-size:16px;"><i class="bi bi-percent"></i> Bagian E: Tabel Simulasi Profitabilitas Berdasarkan ROAS (1.0 - 20.0)</h4>
            <i class="bi bi-chevron-down chevron"></i>
        </div>
        <div class="collapsible-summary" id="simTableSummary">Simulasi lengkap margin dan CPA ideal berdasarkan variasi target ROAS.</div>
        
        <div id="simTableContent" class="collapsible-content collapsed mt-3">
            <div class="sim-tbl-wrap">
                <table class="sim-tbl">
                    <thead>
                        <tr>
                            <th>ROAS</th>
                            <th>Max CPA (Budget Iklan)</th>
                            <th>Profit per Order</th>
                            <th>Status</th>
                            <th>Saran Penyesuaian Harga</th>
                        </tr>
                    </thead>
                    <tbody id="tblSimBody">
                        <!-- Digenerate oleh JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mobile Floating Bottom Bar -->
    <div class="mobile-floating-bar" id="mobileFloatingBar">
        <div class="roas-info">
            <span class="roas-lbl">Target ROAS Ideal</span>
            <span class="roas-val" id="mobileRoasVal">---</span>
        </div>
        <button type="button" class="btn-scroll-down" id="btnScrollToOutput">Lihat Hasil ↓</button>
    </div>
@endsection

@push('scripts')
<script>
    // Formatters
    const fmtRp = (num) => 'Rp ' + Math.round(num).toLocaleString('id-ID');
    const fmtPct = (num) => num.toFixed(1) + '%';
    const fmtX = (num) => num.toFixed(2);

    // Dapatkan elemen
    const el = (id) => document.getElementById(id);

    // Auto-calculate pada input
    document.querySelectorAll('.auto-calc').forEach(inp => {
        inp.addEventListener('input', calcRow);
    });

    // Enter key lookup support
    el('inpProductId').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            el('btnLookup').click();
        }
    });

    // Mobile scroll to output button
    el('btnScrollToOutput').addEventListener('click', () => {
        el('outputColumn').scrollIntoView({ behavior: 'smooth' });
    });

    // Collapsible Logic
    document.querySelectorAll('.collapsible-header').forEach(header => {
        header.addEventListener('click', () => {
            const targetId = header.dataset.target;
            const target = el(targetId);
            const isCollapsed = target.classList.contains('collapsed');
            
            if (isCollapsed) {
                target.classList.remove('collapsed');
                header.classList.remove('collapsed');
                header.classList.add('expanded');
            } else {
                target.classList.add('collapsed');
                header.classList.add('collapsed');
                header.classList.remove('expanded');
            }
        });
    });

    // Lookup AJAX
    el('btnLookup').addEventListener('click', async () => {
        const pid = el('inpProductId').value.trim();
        if (!pid) return alert('Masukkan Product ID terlebih dahulu!');
        
        el('btnLookup').disabled = true;
        el('btnLookup').innerText = 'Mencari...';
        el('productEmptyState').style.display = 'none';
        el('productInfoBox').style.display = 'none';
        el('productSkeleton').style.display = 'block';
        
        try {
            const res = await fetch(`{{ route('roas-calculator.lookup') }}?product_id=${pid}`);
            const data = await res.json();
            
            if (data.found) {
                // Sembunyikan loading, tampilkan box info
                el('productSkeleton').style.display = 'none';
                el('productInfoBox').style.display = 'block';
                el('badgeStore').innerText = data.store_name;
                el('lblProductName').innerText = data.product_name;
                el('lblProductName').title = data.product_name;
                
                const sv = data.selected_variant;
                el('lblVariantSku').innerText = sv.sku_code;
                el('lblVariantLabel').innerText = sv.variation_label;
                
                // Set form values
                el('hpp').value = sv.hpp;
                el('hargaJual').value = sv.harga_jual;
                el('diskon').value = 0; // reset diskon
                
                // Tampilkan tabel varian
                const tb = el('variantsTableBody');
                tb.innerHTML = '';
                data.all_variants.forEach(v => {
                    const isSelected = v.sku_code === sv.sku_code;
                    tb.innerHTML += `
                        <tr class="${isSelected ? 'selected' : ''}">
                            <td>${v.sku_code}</td>
                            <td>${v.variation_label}</td>
                            <td>${fmtRp(v.hpp)}</td>
                            <td>${v.promotion_price > 0 ? fmtRp(v.promotion_price) : '-'}</td>
                        </tr>
                    `;
                });
                
                // Set text summary varian
                el('variantsSummaryText').innerText = `${data.variant_count} Varian ditemukan. HPP tertinggi pada varian ${sv.sku_code} (${fmtRp(sv.hpp)}).`;

                // Auto-fill ROAS Aktual jika ada
                if (data.latest_roi && data.latest_roi > 0) {
                    el('roasAktual').value = data.latest_roi;
                    el('panelWhatIf').style.display = 'block';
                    // Auto-expand what-if jika ROAS terisi
                    const wHeader = document.querySelector('[data-target="whatIfPanelContent"]');
                    wHeader.classList.remove('collapsed');
                    wHeader.classList.add('expanded');
                    el('whatIfPanelContent').classList.remove('collapsed');
                } else {
                    el('roasAktual').value = '';
                    el('panelWhatIf').style.display = 'none';
                }
                
                // Trigger kalkulasi
                calcRow();
            } else {
                alert(data.message || 'Product ID tidak ditemukan');
                resetToEmptyState();
            }
        } catch (e) {
            console.error(e);
            alert('Terjadi kesalahan saat mencari data produk.');
            resetToEmptyState();
        } finally {
            el('btnLookup').disabled = false;
            el('btnLookup').innerText = 'Cari';
        }
    });

    function resetToEmptyState() {
        el('productSkeleton').style.display = 'none';
        el('productInfoBox').style.display = 'none';
        el('productEmptyState').style.display = 'flex';
        // Reset inputs
        el('hpp').value = 0;
        el('hargaJual').value = 0;
        el('diskon').value = 0;
        el('roasAktual').value = '';
        calcRow();
    }

    function getVal(id) {
        return parseFloat(el(id).value) || 0;
    }

    function updateSummaries(totalFeePersen, totalFeeNominalRp, roasBep) {
        // Platform fee summary
        if (totalFeePersen !== undefined && totalFeeNominalRp !== undefined) {
            el('feePanelSummary').innerText = `Total platform fee: ${totalFeePersen.toFixed(1)}% + Rp ${totalFeeNominalRp.toLocaleString('id-ID')}`;
        }
        // Sim table summary
        if (roasBep !== undefined && roasBep > 0) {
            el('simTableSummary').innerText = `Simulasi Break-Even Point (BEP) tercapai pada target ROAS ${roasBep.toFixed(2)}`;
        }
    }

    function calcRow() {
        const hargaJual = getVal('hargaJual');
        const hpp = getVal('hpp');
        const diskon = getVal('diskon');

        const hargaFinal = hargaJual - diskon;
        
        // Reset output jika belum diisi lengkap
        if (hargaFinal <= 0) {
            el('outHargaFinal').innerText = '---';
            el('outMarginKotorRp').innerText = '---';
            el('outBudgetIdealRp').innerText = '---';
            el('outProfitNetRp').innerText = '---';
            el('outProfitNetRp').className = 'v';
            el('outMarginDasar').innerText = '---';
            el('outTotalPotongan').innerText = '---';
            el('outMarginKotor').innerText = '---';
            el('outMarginBersih').innerText = '---';
            el('outTargetProfitRp').innerText = '---';
            el('outCpa').innerText = '---';
            el('outTargetAcos').innerText = '---';
            el('outAcos').innerText = '---';
            el('outTargetRoas').innerText = '---';
            el('outTargetRoas2').innerText = '---';
            el('outRoasBep').innerText = '---';
            el('outRoasBep2').innerText = '---';
            el('outProfitNetOrder').innerText = '---';
            el('outProfitNetOrder').className = 'v pos';
            el('outProfitNetPct').innerText = '---';
            el('outProfitNetPct').className = 'v pos';
            el('tblSimBody').innerHTML = '';
            el('panelWhatIf').style.display = 'none';
            el('mobileRoasVal').innerText = '---';
            return;
        }

        // Fee Persentase
        const fp = getVal('feePlatform');
        const fd = getVal('feeDinamis');
        const fg = getVal('feeGrowth');
        const fa = getVal('feeAffiliate');
        const fo = getVal('feeOps');
        const fl = getVal('feeLainPersen');
        const totalFeePersen = fp + fd + fg + fa + fo + fl;
        const totalFeePersenRp = hargaFinal * (totalFeePersen / 100);

        // Fee Nominal
        const fPemRp = getVal('feePemrosesanRp');
        const fLogRp = getVal('feeLogistikRp');
        const fGarRp = getVal('feeGaransiRp');
        const fPacRp = getVal('feePackingRp');
        const totalFeeNominalRp = fPemRp + fLogRp + fGarRp + fPacRp;

        // Hitung Margin
        const marginDasar = hargaFinal - hpp;
        const marginDasarPct = (marginDasar / hargaFinal) * 100;
        
        const totalPotongan = totalFeePersenRp + totalFeeNominalRp;
        const totalPotonganPct = (totalPotongan / hargaFinal) * 100;
        
        const marginKotor = marginDasar - totalPotongan;
        const marginKotorPct = (marginKotor / hargaFinal) * 100;

        // PPN
        const ppnPct = getVal('ppnPersen');
        const potonganUtkPpn = totalPotongan - fLogRp - fPacRp; 
        const ppnRp = potonganUtkPpn * (ppnPct / 100);
        
        const marginBersih = marginKotor - ppnRp;
        const marginBersihPct = (marginBersih / hargaFinal) * 100;

        // Target ROAS & Budget
        const tpPct = getVal('targetProfitPersen');
        const tpRp = hargaFinal * (tpPct / 100);
        
        const budgetIdeal = marginBersih - tpRp; // Max CPA
        const acosIdeal = budgetIdeal > 0 ? (budgetIdeal / hargaFinal) * 100 : 0;
        const roasIdeal = budgetIdeal > 0 ? (hargaFinal / budgetIdeal) : 0;
        
        const roasBep = marginBersih > 0 ? (hargaFinal / marginBersih) : 0;

        const profitNet = marginBersih - budgetIdeal;
        const profitNetPct = (profitNet / hargaFinal) * 100;

        // Update UI Text
        el('outHargaFinal').innerText = fmtRp(hargaFinal);
        el('outMarginKotorRp').innerText = fmtRp(marginKotor);
        el('outBudgetIdealRp').innerText = fmtRp(budgetIdeal);
        
        const ePN = el('outProfitNetRp');
        ePN.innerText = fmtRp(profitNet);
        ePN.className = 'v ' + (profitNet > 0 ? 'pos' : (profitNet < 0 ? 'neg' : ''));

        el('outMarginDasar').innerText = `${fmtRp(marginDasar)} (${fmtPct(marginDasarPct)})`;
        el('outTotalPotongan').innerText = `${fmtRp(totalPotongan)} (${fmtPct(totalPotonganPct)})`;
        el('outMarginKotor').innerText = `${fmtRp(marginKotor)} (${fmtPct(marginKotorPct)})`;
        el('outMarginBersih').innerText = `${fmtRp(marginBersih)} (${fmtPct(marginBersihPct)})`;

        el('outTargetProfitRp').innerText = fmtRp(tpRp);
        el('outCpa').innerText = `${fmtRp(budgetIdeal)} (${fmtPct(acosIdeal)})`;
        el('outTargetAcos').innerText = fmtPct(acosIdeal);
        el('outAcos').innerText = fmtPct(acosIdeal);
        
        const targetRoasVal = roasIdeal > 0 ? fmtX(roasIdeal) : 'N/A';
        el('outTargetRoas').innerText = targetRoasVal;
        el('outTargetRoas2').innerText = targetRoasVal;
        el('mobileRoasVal').innerText = targetRoasVal;
        
        el('outRoasBep').innerText = roasBep > 0 ? fmtX(roasBep) : 'N/A';
        el('outRoasBep2').innerText = roasBep > 0 ? fmtX(roasBep) : 'N/A';

        el('outProfitNetOrder').innerText = fmtRp(profitNet);
        el('outProfitNetOrder').className = 'v ' + (profitNet > 0 ? 'pos' : (profitNet < 0 ? 'neg' : ''));
        el('outProfitNetPct').innerText = fmtPct(profitNetPct);
        el('outProfitNetPct').className = 'v ' + (profitNetPct > 0 ? 'pos' : (profitNetPct < 0 ? 'neg' : ''));

        // Update Collapsible summaries dynamically
        updateSummaries(totalFeePersen, totalFeeNominalRp, roasBep);

        // Simulasi ROAS Aktual (What-If)
        const rAktual = getVal('roasAktual');
        const pWhatIf = el('panelWhatIf');
        if (rAktual > 0) {
            pWhatIf.style.display = 'block';
            const ba = hargaFinal / rAktual; // Budget/CPA Aktual
            const pa = marginBersih - ba;    // Profit Aktual
            
            el('wiBudget').innerText = fmtRp(ba);
            
            const eWp = el('wiProfit');
            eWp.innerText = fmtRp(pa);
            eWp.className = 'v ' + (pa > 0 ? 'pos' : 'neg');

            const ewS = el('wiStatus');
            const ewT = el('wiSaran');
            
            if (pa > tpRp) {
                ewS.className = 'chip aman';
                ewS.innerHTML = '<i class="bi bi-check-circle-fill"></i> AMAN';
                ewT.innerText = 'Performa sangat baik! Iklan menghasilkan profit melebihi target. Anda bisa scale up budget harian.';
            } else if (pa > 0) {
                ewS.className = 'chip waspada';
                ewS.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> WASPADA';
                
                const hargaSaran = (hpp + totalPotongan + ppnRp + ba + tpRp);
                ewT.innerHTML = `Profit di bawah target. Untuk mencapai profit target ${fmtPct(tpPct)}, harga jual perlu dinaikkan menjadi <strong>${fmtRp(hargaSaran)}</strong> (naik ${fmtRp(hargaSaran - hargaFinal)}).`;
            } else {
                ewS.className = 'chip rugi';
                ewS.innerHTML = '<i class="bi bi-x-circle-fill"></i> RUGI';
                
                const minHarga = (hpp + totalPotongan + ppnRp + ba);
                ewT.innerHTML = `Iklan boncos (rugi). ROAS BEP Anda adalah ${fmtX(roasBep)}. Untuk sekadar balik modal dengan performa iklan saat ini, harga jual minimal harus <strong>${fmtRp(minHarga)}</strong>.`;
            }
        } else {
            pWhatIf.style.display = 'none';
        }

        // Render Tabel Simulasi
        simTable(hargaFinal, marginBersih, tpRp, hpp, totalPotongan, ppnRp, rAktual);
    }

    function simTable(hf, mb, tpRp, hpp, tp, ppn, rAktual) {
        const tb = el('tblSimBody');
        tb.innerHTML = '';
        if (hf <= 0) return;

        // Base points
        let roasPoints = [
            1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0, 
            6.0, 7.0, 8.0, 9.0, 10.0, 
            12.0, 15.0, 20.0
        ];

        // Insert actual ROAS to table if it is not already in the list
        if (rAktual > 0 && !roasPoints.includes(rAktual)) {
            roasPoints.push(rAktual);
            roasPoints.sort((a, b) => a - b);
        }

        let html = '';
        roasPoints.forEach(r => {
            const budget = hf / r;
            const profit = mb - budget;
            
            let status = '';
            let sCls = '';
            let saran = '-';

            if (profit > tpRp) {
                status = '<span class="chip aman"><i class="bi bi-check-circle-fill"></i> AMAN</span>';
                sCls = 'r-aman';
            } else if (profit > 0) {
                status = '<span class="chip waspada"><i class="bi bi-exclamation-triangle-fill"></i> WASPADA</span>';
                sCls = 'r-waspada';
                const need = hpp + tp + ppn + budget + tpRp;
                saran = `Naikkan harga jadi ${fmtRp(need)}`;
            } else {
                status = '<span class="chip rugi"><i class="bi bi-x-circle-fill"></i> RUGI</span>';
                sCls = 'r-rugi';
                const bepNeed = hpp + tp + ppn + budget;
                saran = `Harga BEP: ${fmtRp(bepNeed)}`;
            }

            // Check if this row is the actual ROAS
            const isAktual = (rAktual > 0 && Math.abs(r - rAktual) < 0.001);
            if (isAktual) {
                sCls = 'r-aktual';
                saran = `<strong>Performa Anda</strong> (BEP ROAS: ${fmtX(hf / mb)})`;
            }

            html += `
                <tr class="${sCls}">
                    <td>${r.toFixed(2)}${isAktual ? ' ★' : ''}</td>
                    <td>${fmtRp(budget)}</td>
                    <td class="${profit > 0 ? 'pos' : 'neg'}" style="font-weight:700;">${fmtRp(profit)}</td>
                    <td class="text-center">${status}</td>
                    <td style="font-family:var(--bs-font-sans-serif);font-size:11px;">${saran}</td>
                </tr>
            `;
        });
        
        tb.innerHTML = html;
    }

    // Init state
    calcRow();
</script>
@endpush

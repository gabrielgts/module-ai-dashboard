/**
 * Gtstudio AiDashboard — main JS module.
 *
 * Responsibilities:
 *   1. Load dashboard snapshot from the PHP AJAX endpoint.
 *   2. Render KPI cards, tables, stock alerts.
 *   3. Initialise Chart.js charts (revenue trend + order-status donut).
 *   4. Handle AI Insights panel (GetInsights endpoint).
 *   5. Handle AI Chat drawer (Chat endpoint).
 *
 * Loaded via x-magento-init so it runs after DOM ready and RequireJS is ready.
 *
 * TODO: load Chart.js via requirejs-config.js alias once added to the module.
 *       For now it must be available globally (CDN script tag in the layout or
 *       a local copy at view/adminhtml/web/js/vendor/chart.umd.min.js).
 */
define(['jquery', 'chartjs'], function ($, Chart) {
    'use strict';

    var cfg = window.AiDashboardConfig || {};
    var charts = {};
    var currentStoreId = 0;

    function money(v) {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: 'USD',     // TODO: inject store currency from block
            maximumFractionDigits: 2
        }).format(v || 0);
    }

    function pct(v) {
        var sign = v >= 0 ? '+' : '';
        return sign + parseFloat(v || 0).toFixed(1) + '%';
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function showSection(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = '';
    }

    function hideSection(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    /**
     * Returns an HTML string for a slope indicator arrow.
     * Threshold of ±0.5 keeps "flat" from being too sensitive.
     */
    function slopeHtml(slope) {
        var v = parseFloat(slope) || 0;
        if (v > 0.5)  return '<span class="aid-trend aid-trend--up">↑ Growing</span>';
        if (v < -0.5) return '<span class="aid-trend aid-trend--down">↓ Declining</span>';
        return '<span class="aid-trend aid-trend--flat">→ Flat</span>';
    }

    function renderKpis(data) {
        var o = data.orders;
        var c = data.customers;
        var p = data.products;

        setText('kpi-today-revenue', money(o.today_revenue));
        setText('kpi-today-orders', o.today_count + ' orders');
        setText('kpi-month-revenue', money(o.month_revenue));
        setText('kpi-growth', pct(o.growth_percent) + ' vs prior month');
        setText('kpi-avg-order', money(o.avg_order_value));
        setText('kpi-month-orders', o.month_count + ' orders this month');
        setText('kpi-new-customers', c.new_today);
        setText('kpi-repeat-rate', parseFloat(c.repeat_rate || 0).toFixed(1) + '% repeat rate');
        setText('kpi-pending', o.by_status.pending || 0);
        setText('kpi-processing', (o.by_status.processing || 0) + ' processing');
        setText('kpi-out-of-stock', p.out_of_stock);
        setText('kpi-low-stock', p.low_stock_count + ' low stock');

        setText('aid-last-updated-time', data.built_at
            ? new Date(data.built_at).toLocaleString()
            : '—'
        );

        // Slope indicators
        var revSlopeEl = document.getElementById('aid-revenue-slope');
        if (revSlopeEl) revSlopeEl.innerHTML = slopeHtml(o.revenue_trend_slope);

        var acqSlopeEl = document.getElementById('aid-acquisition-slope');
        if (acqSlopeEl) acqSlopeEl.innerHTML = slopeHtml(c.acquisition_trend_slope);
    }

    function renderCouponCard(coupon) {
        if (!coupon) return;

        setText('kpi-coupons-today', coupon.used_today || 0);
        setText('kpi-coupons-month', coupon.used_month || 0);
        setText('kpi-coupons-discount', money(coupon.total_discount_month));

        var tbody = document.getElementById('aid-coupons-tbody');
        if (!tbody) return;

        var top = coupon.top_coupons || [];
        if (!top.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="aid-table__empty">No coupons used this month.</td></tr>';
            return;
        }

        tbody.innerHTML = top.map(function (c) {
            return '<tr>' +
                '<td class="aid-table__mono">' + escHtml(c.code) + '</td>' +
                '<td>' + c.uses + '</td>' +
                '<td>' + money(c.total_discount) + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderRevenueChart(trend) {
        var canvas = document.getElementById('aid-chart-revenue');
        if (!canvas) return;

        if (charts.revenue) charts.revenue.destroy();

        var labels = trend.map(function (d) { return d.date; });
        var revenues = trend.map(function (d) { return d.revenue; });
        var orders = trend.map(function (d) { return d.orders; });

        charts.revenue = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenues,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124,58,237,0.08)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        yAxisID: 'yRevenue'
                    },
                    {
                        label: 'Orders',
                        data: orders,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [4, 4],
                        tension: 0.4,
                        pointRadius: 2,
                        yAxisID: 'yOrders'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label === 'Revenue'
                                    ? money(ctx.parsed.y)
                                    : ctx.parsed.y + ' orders';
                            }
                        }
                    }
                },
                scales: {
                    yRevenue: {
                        type: 'linear', position: 'left',
                        ticks: { callback: function (v) { return money(v); } }
                    },
                    yOrders: {
                        type: 'linear', position: 'right',
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    }

    function renderStatusChart(byStatus) {
        var canvas = document.getElementById('aid-chart-status');
        if (!canvas) return;

        if (charts.status) charts.status.destroy();

        var palette = {
            pending: '#f59e0b',
            processing: '#3b82f6',
            complete: '#22c55e',
            canceled: '#ef4444',
            holded: '#a855f7',
            closed: '#6b7280'
        };

        var labels = Object.keys(byStatus);
        var values = labels.map(function (k) { return byStatus[k]; });
        var colors = labels.map(function (k) { return palette[k] || '#94a3b8'; });

        charts.status = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: values, backgroundColor: colors, hoverOffset: 6 }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    function renderProductsTable(products) {
        var tbody = document.getElementById('aid-products-tbody');
        if (!tbody) return;

        if (!products || !products.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="aid-table__empty">No data.</td></tr>';
            return;
        }

        tbody.innerHTML = products.slice(0, 10).map(function (p, i) {
            return '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td class="aid-table__name">' + escHtml(p.name) + '</td>' +
                '<td class="aid-table__mono">' + escHtml(p.sku) + '</td>' +
                '<td>' + parseFloat(p.qty_sold).toFixed(0) + '</td>' +
                '<td>' + money(p.revenue) + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderCustomersTable(customers) {
        var tbody = document.getElementById('aid-customers-tbody');
        if (!tbody) return;

        if (!customers || !customers.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="aid-table__empty">No data.</td></tr>';
            return;
        }

        tbody.innerHTML = customers.map(function (c, i) {
            return '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + escHtml(c.name) + '<br><small>' + escHtml(c.email) + '</small></td>' +
                '<td>' + c.total_orders + '</td>' +
                '<td>' + money(c.lifetime_value) + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderStockAlerts(lowStock, outOfStock) {
        var ul = document.getElementById('aid-stock-alerts');
        if (!ul) return;

        if (!lowStock || !lowStock.length) {
            ul.innerHTML = '<li class="aid-alerts__ok">All products are well-stocked.</li>';
            return;
        }

        ul.innerHTML = lowStock.map(function (s) {
            var cls = s.qty <= 0 ? 'aid-alert--danger' : 'aid-alert--warn';
            var label = s.qty <= 0 ? 'OUT OF STOCK' : s.qty + ' left';
            var badge = s.anomaly
                ? '<span class="aid-alert__badge aid-alert__badge--drain">⚡ FAST DRAIN</span>'
                : '';
            return '<li class="aid-alert ' + cls + (s.anomaly ? ' aid-alert--anomaly' : '') + '">' +
                '<span class="aid-alert__name">' + escHtml(s.name) + badge + '</span>' +
                '<span class="aid-alert__sku">' + escHtml(s.sku) + '</span>' +
                '<span class="aid-alert__qty">' + label + '</span>' +
                '</li>';
        }).join('');
    }

    function renderSegments(segments) {
        if (!segments) return;

        var tiers = [
            { key: 'vip', label: 'VIP', cls: 'aid-seg--vip' },
            { key: 'active', label: 'Active', cls: 'aid-seg--active' },
            { key: 'at_risk', label: 'At-Risk', cls: 'aid-seg--at-risk' }
        ];

        tiers.forEach(function (t) {
            var tier = segments[t.key] || { count: 0, avg_ltv: 0 };
            setText('aid-seg-count-' + t.key, tier.count);
            setText('aid-seg-ltv-' + t.key, 'Avg LTV: ' + money(tier.avg_ltv));
        });
    }

    function renderRecentOrders(orders) {
        var tbody = document.getElementById('aid-recent-orders-tbody');
        if (!tbody) return;

        var statusClass = {
            pending: 'aid-status--pending',
            processing: 'aid-status--processing',
            complete: 'aid-status--complete',
            canceled: 'aid-status--canceled',
            holded: 'aid-status--holded'
        };

        if (!orders || !orders.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="aid-table__empty">No recent orders.</td></tr>';
            return;
        }

        tbody.innerHTML = orders.map(function (o) {
            var cls = statusClass[o.status] || '';
            return '<tr>' +
                '<td class="aid-table__mono">' + escHtml(o.increment_id) + '</td>' +
                '<td>' + escHtml(o.customer_name) + '</td>' +
                '<td>' + (o.items_count || 0) + '</td>' +
                '<td>' + money(o.grand_total) + '</td>' +
                '<td><span class="aid-status ' + cls + '">' + escHtml(o.status) + '</span></td>' +
                '<td>' + escHtml(o.created_at) + '</td>' +
                '</tr>';
        }).join('');
    }

    var AiDashboard = {
        load: function (force) {
            hideSection('aid-content');
            hideSection('aid-error');
            showSection('aid-loading');

            var base = force ? cfg.refreshUrl : cfg.dataUrl;
            var url = base + (base.indexOf('?') === -1 ? '?' : '&') + 'store_id=' + currentStoreId;

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function (resp) {
                    hideSection('aid-loading');
                    if (!resp.success) {
                        AiDashboard.showError(resp.message || 'Unknown error');
                        return;
                    }

                    var d = resp.data;
                    renderKpis(d);
                    renderCouponCard(d.orders.coupon_metrics || {});
                    renderRevenueChart(d.orders.revenue_trend || []);
                    renderStatusChart(d.orders.by_status || {});
                    renderProductsTable(d.products.top_sellers || []);
                    renderCustomersTable(d.customers.top_by_ltv || []);
                    renderStockAlerts(d.products.low_stock_list || [], d.products.out_of_stock);
                    renderRecentOrders(d.orders.recent_orders || []);
                    renderSegments(d.customers.segments || {});

                    showSection('aid-content');
                },
                error: function (xhr) {
                    hideSection('aid-loading');
                    AiDashboard.showError('Request failed (' + xhr.status + ')');
                }
            });
        },

        showError: function (msg) {
            setText('aid-error-msg', msg);
            showSection('aid-error');
        }
    };

    window.AiDashboard = AiDashboard;

    function initInsights() {
        var btn = document.getElementById('aid-get-insights-btn');
        var body = document.getElementById('aid-insights-body');
        if (!btn || !body) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            body.innerHTML = '<div class="aid-spinner aid-spinner--sm"></div>';

            $.ajax({
                url: cfg.insightsUrl,
                type: 'POST',
                dataType: 'json',
                data: { form_key: window.FORM_KEY },
                success: function (resp) {
                    btn.disabled = false;
                    if (resp.success) {
                        // TODO: use a markdown renderer (e.g. marked.js) for richer output
                        body.innerHTML = '<div class="aid-markdown">' + escHtml(resp.content) + '</div>';
                    } else {
                        body.innerHTML = '<p class="aid-error-inline">' + escHtml(resp.message) + '</p>';
                    }
                },
                error: function () {
                    btn.disabled = false;
                    body.innerHTML = '<p class="aid-error-inline">Request failed.</p>';
                }
            });
        });
    }

    function initChat() {
        var drawer = document.getElementById('aid-chat-drawer');
        var openBtn = document.getElementById('aid-chat-open-btn');
        var closeBtn = document.getElementById('aid-chat-close-btn');
        var input = document.getElementById('aid-chat-input');
        var sendBtn = document.getElementById('aid-chat-send-btn');
        var msgs = document.getElementById('aid-chat-messages');
        var tokenEl = document.getElementById('aid-chat-tokens');
        if (!drawer) return;

        openBtn && openBtn.addEventListener('click', function () {
            drawer.classList.remove('aid-chat-drawer--closed');
        });

        closeBtn && closeBtn.addEventListener('click', function () {
            drawer.classList.add('aid-chat-drawer--closed');
        });

        function appendMessage(role, text) {
            var div = document.createElement('div');
            div.className = 'aid-chat__msg aid-chat__msg--' + role;
            // TODO: render markdown for assistant messages
            div.textContent = text;
            msgs.appendChild(div);
            msgs.scrollTop = msgs.scrollHeight;
        }

        function send() {
            var msg = input.value.trim();
            if (!msg) return;
            input.value = '';
            appendMessage('user', msg);
            sendBtn.disabled = true;

            $.ajax({
                url: cfg.chatUrl,
                type: 'POST',
                dataType: 'json',
                data: { message: msg, form_key: window.FORM_KEY },
                success: function (resp) {
                    sendBtn.disabled = false;
                    if (resp.success) {
                        appendMessage('assistant', resp.content);
                        if (resp.tokens && tokenEl) {
                            tokenEl.textContent = 'Tokens: ' + resp.tokens.input + ' in / ' + resp.tokens.output + ' out';
                        }
                    } else {
                        appendMessage('assistant', 'Error: ' + (resp.message || 'Unknown error'));
                    }
                },
                error: function () {
                    sendBtn.disabled = false;
                    appendMessage('assistant', 'Request failed. Please try again.');
                }
            });
        }

        sendBtn && sendBtn.addEventListener('click', send);
        input && input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
        });
    }

    function initStoreSwitcher() {
        if (!cfg.hasStoreSwitcher) return;

        var select = document.getElementById('aid-store-switcher');
        if (!select) return;

        select.addEventListener('change', function () {
            currentStoreId = parseInt(this.value, 10) || 0;
            AiDashboard.load();
        });
    }

    function initRefreshBtn() {
        var btn = document.getElementById('aid-refresh-btn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            AiDashboard.load(true);
        });
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getSectionOrder() {
        var order = [];
        $('#aid-sections .aid-sortable-section').each(function () {
            var id = $(this).data('section-id');
            if (id) order.push(id);
        });
        return order;
    }

    function saveSectionOrder() {
        if (!cfg.preferencesUrl) return;

        $.ajax({
            url: cfg.preferencesUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                sections_order: getSectionOrder(),
                form_key: window.FORM_KEY
            }
        });
    }

    function restoreSectionOrder() {
        var saved = cfg.sectionsOrder;
        if (!Array.isArray(saved) || !saved.length) return;

        var container = document.getElementById('aid-sections');
        if (!container) return;

        saved.forEach(function (id) {
            var el = container.querySelector('[data-section-id="' + id + '"]');
            if (el) container.appendChild(el);
        });
    }

    function initSortable() {
        var $container = $('#aid-sections');
        if (!$container.length || typeof $container.sortable !== 'function') return;

        restoreSectionOrder();

        $container.sortable({
            handle: '.aid-drag-handle',
            axis: 'y',
            tolerance: 'pointer',
            placeholder: 'aid-sortable-placeholder',
            forcePlaceholderSize: true,
            opacity: 0.75,
            cursor: 'grabbing',
            stop: function () { saveSectionOrder(); }
        });
    }

    return function () {
        AiDashboard.load();
        initInsights();
        initChat();
        initRefreshBtn();
        initStoreSwitcher();
        initSortable();
    };
});

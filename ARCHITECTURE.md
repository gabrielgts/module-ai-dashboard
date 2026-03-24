# Gtstudio_AiDashboard — Architecture

## Overview

Replaces the stock Magento admin dashboard with a modern, AI-powered analytics hub. All heavy
data queries run on a background cron and are served from a dedicated cache type to eliminate
real-time database pressure. An embedded AI assistant (the `store_assistant` agent) provides
natural-language insights powered by the existing `Gtstudio_AiDataQuery` tool ecosystem.

---

## Module Dependencies

```
Gtstudio_AiDashboard
  ├── Gtstudio_AiConnector      (AI provider abstraction)
  ├── Gtstudio_AiAgents         (agent runner + tool executor pool)
  ├── Gtstudio_AiDataQuery      (order/customer/product analytics tools)
  ├── Magento_Backend           (admin routing, auth)
  ├── Magento_Sales             (order collection)
  ├── Magento_Customer          (customer collection)
  ├── Magento_Catalog           (product collection)
  └── Magento_CatalogInventory  (stock data)
```

---

## Directory Structure

```
Gtstudio/AiDashboard/
│
├── Api/
│   ├── Data/
│   │   ├── DashboardSnapshotInterface.php   ← Top-level DTO: orders + customers + products
│   │   ├── OrderMetricsInterface.php         ← All order KPIs and chart data
│   │   ├── CustomerMetricsInterface.php      ← Customer KPIs and trend data
│   │   └── ProductMetricsInterface.php       ← Product KPIs, top sellers, stock alerts
│   └── DashboardDataServiceInterface.php     ← getSnapshot() / buildSnapshot() / invalidateCache()
│
├── Block/
│   └── Adminhtml/
│       └── Dashboard.php                     ← Provides URL helpers to template
│
├── Controller/
│   └── Adminhtml/
│       ├── Dashboard/
│       │   └── Index.php                     ← Renders page (overrides /admin/dashboard/index)
│       └── Ajax/
│           ├── GetData.php                   ← Returns cached snapshot as JSON
│           ├── GetInsights.php               ← Calls store_assistant agent, returns insights
│           └── Chat.php                      ← Conversational AI chat via store_assistant
│
├── Cron/
│   ├── BuildDashboardData.php               ← Full rebuild — daily at 00:05
│   └── RefreshTodayData.php                 ← Incremental today-only refresh — hourly
│
├── Model/
│   ├── Cache/
│   │   └── Dashboard.php                    ← Custom cache type (existing)
│   ├── Data/
│   │   ├── DashboardSnapshot.php            ← Implements DashboardSnapshotInterface
│   │   ├── OrderMetrics.php                 ← Implements OrderMetricsInterface
│   │   ├── CustomerMetrics.php              ← Implements CustomerMetricsInterface
│   │   └── ProductMetrics.php               ← Implements ProductMetricsInterface
│   └── Service/
│       ├── DashboardDataService.php         ← Orchestrates collectors + cache
│       ├── OrderMetricsCollector.php        ← Queries order/sales data
│       ├── CustomerMetricsCollector.php     ← Queries customer data
│       ├── ProductMetricsCollector.php      ← Queries product + inventory data
│       └── CacheManager.php                ← Serialize/deserialize snapshot ↔ cache
│
├── Setup/
│   └── Patch/
│       └── Data/
│           └── CreateStoreAssistantAgentPatch.php  ← Seeds store_assistant agent in DB
│
├── etc/
│   ├── cache.xml                            ← Dashboard cache type registration
│   ├── crontab.xml                          ← Two cron jobs (full + incremental)
│   ├── di.xml                               ← Interface → implementation preferences
│   ├── module.xml                           ← Module declaration + sequence
│   ├── acl.xml                              ← ACL resources
│   └── adminhtml/
│       ├── routes.xml                       ← Admin route "aidashboard"
│       └── menu.xml                         ← No new menu entry; overrides existing Dashboard
│
└── view/
    └── adminhtml/
        ├── layout/
        │   ├── adminhtml_dashboard_index.xml    ← Overrides Magento default dashboard layout
        │   └── aidashboard_dashboard_index.xml  ← Layout for our own route (fallback)
        ├── templates/
        │   └── dashboard/
        │       └── index.phtml                  ← Full dashboard HTML + Alpine.js + Chart.js
        └── web/
            ├── js/
            │   ├── dashboard.js                 ← Data loading, chart init, chat logic
            │   └── requirejs-config.js          ← Registers Chart.js alias
            └── css/
                └── dashboard.css                ← KPI cards, chart panels, chat drawer styles
```

---

## Data Flow

```
Browser                  PHP (admin request)           Cache / DB
  │                            │                           │
  │── GET /admin/dashboard ──▶ │                           │
  │                      render index.phtml (empty shell)  │
  │◀─────────── HTML ─────────│                           │
  │                            │                           │
  │── XHR /aidashboard/ajax/getdata ──▶                   │
  │                     DashboardDataService.getSnapshot() │
  │                            │── cache hit? ────────────▶│
  │                            │◀── JSON blob ────────────│
  │◀────── metrics JSON ───────│                           │
  │  (render charts + tables)  │                           │
  │                            │                           │
  │── XHR /aidashboard/ajax/getinsights ──▶               │
  │                     AgentRunInterface.run(             │
  │                       'store_assistant', question)     │
  │                            │── tools execute ─────────▶│
  │◀────── insights markdown ──│                           │
  │                            │                           │
Cron (00:05 daily)             │                           │
  BuildDashboardData.execute() │                           │
     DashboardDataService      │                           │
       .buildSnapshot()        │                           │
       → OrderMetricsCollector ─────────────────────────▶ DB
       → CustomerMetricsCollector ──────────────────────▶ DB
       → ProductMetricsCollector ───────────────────────▶ DB
       .cacheManager.save()    ──────────────────────────▶ Cache
                               │                           │
Cron (0 * * * * hourly)        │                           │
  RefreshTodayData.execute()   │                           │
     Rebuilds today-only keys  ──────────────────────────▶ Cache
```

---

## Cache Strategy

| Cache key                              | TTL      | Rebuilt by           |
|----------------------------------------|----------|----------------------|
| `dashboard_snapshot_v1`                | 3600 s   | BuildDashboardData   |
| `dashboard_today_v1`                   | 1800 s   | RefreshTodayData     |
| `dashboard_ai_insights_v1`             | 7200 s   | On-demand (GetInsights) |

Cache type: `Gtstudio\AiDashboard\Model\Cache\Dashboard` (TYPE_IDENTIFIER = `dashboard_cache_tag`)

---

## store_assistant Agent

Created via `CreateStoreAssistantAgentPatch`. The agent is registered in `gtstudio_ai_agent`
and uses the same `ToolExecutorPool` registered by `Gtstudio_AiDataQuery`.

| Field       | Value |
|-------------|-------|
| code        | `store_assistant` |
| tools       | `order_analytics`, `customer_lifetime_value`, `product_performance`, `query_entity` |
| cron_enabled| false (interactive only) |
| purpose     | Dashboard AI chat, insights panel, natural-language analytics |

---

## Dashboard UI Sections

```
┌────────────────────────────────────────────────────────────────────┐
│  AI Dashboard                     Last updated: ...  [↺] [✦ AI]   │
├──────────┬──────────┬──────────┬──────────┬──────────┬────────────┤
│ Today    │ Today    │ Month    │ New Cust │ Avg Order│ Pending    │
│ Revenue  │ Orders   │ Revenue  │ Today    │ Value    │ Orders     │
├──────────┴──────────┴──────────┴──────────┴──────────┴────────────┤
│  Revenue Trend (30 days — line chart)  │  Orders by Status (donut) │
│                                        │                           │
├────────────────────────────────────────┴───────────────────────────┤
│  Top Selling Products (table, 10 rows) │  Top Customers by LTV     │
│  SKU │ Name │ Qty Sold │ Revenue       │  Name │ Orders │ LTV      │
├────────────────────────────────────────┼───────────────────────────┤
│  Low Stock Alerts  (badge list)        │  AI Insights Panel        │
│  ⚠ Product A: 3 units left            │  "Get AI Insights" button │
│  ⚠ Product B: 0 units (out of stock)  │  Rendered markdown output │
├────────────────────────────────────────┴───────────────────────────┤
│  Recent Orders (table, last 10)                                    │
│  # │ Customer │ Items │ Total │ Status │ Date                      │
└────────────────────────────────────────────────────────────────────┘
                                      [AI Assistant Chat Drawer →]
```

---

## Metrics Collected

### Order Metrics (`OrderMetricsCollector`)
- Today / this week / this month: order count + revenue
- Revenue trend: last 30 days (per-day totals for line chart)
- Orders by status: `pending`, `processing`, `complete`, `canceled`, `holded`
- Average order value (month)
- Revenue growth % vs prior month
- Recent orders list (last 10)

### Customer Metrics (`CustomerMetricsCollector`)
- New customers: today / week / month
- Total registered customers
- Top 5 customers by lifetime value
- Repeat customer rate (%)
- Acquisition trend: last 30 days (new customer count per day)

### Product Metrics (`ProductMetricsCollector`)
- Top 10 products by qty sold (last 30 days)
- Revenue by top 5 categories
- Low stock products (below configurable threshold, default 10)
- Out of stock count
- Total active product count

---

## TODO / Planned Features

### php-ml Integration (php-ml/php-ml)

Candidate features where php-ml's algorithms are a genuine fit — lightweight, in-process, no
time-series dependency. For complex forecasting (inventory replenishment, demand prediction)
use the existing `Gtstudio_AiConnector` LLM pipeline instead — it handles non-linearity and
seasonality without training infrastructure.

- [x] **Customer tier segmentation (k-Means)** ✓ implemented
  Clusters customers by RFM (Recency, Frequency, Monetary) into tiers: VIP / Active / At-Risk.
  - Algorithm: `Phpml\Clustering\KMeans` (k=3, KMeans++ init)
  - Input: per-customer `[days_since_last_order, order_count, lifetime_value]` — min-max normalised, recency inverted
  - Run in: `CustomerMetricsCollector::collectSegments()` during cron rebuild (max 2000 customers)
  - Service: `Model/Service/Ml/CustomerSegmentationAnalyzer.php`
  - Output: `segments` on `CustomerMetricsInterface` → `{vip, active, at_risk}` each with `count`, `avg_ltv`, `customers[]`

- [x] **KPI trend direction (OLS slope)** ✓ implemented
  Detects whether a rolling N-day metric is trending up or down.
  - Algorithm: `Phpml\Regression\LeastSquares` (day index as X, metric value as Y)
  - Input: existing `revenue_trend` / `acquisition_trend` arrays already in cache
  - Service: `Model/Service/Ml/TrendSlopeAnalyzer.php`
  - Output: `revenue_trend_slope` on `OrderMetricsInterface`; `acquisition_trend_slope` on `CustomerMetricsInterface`
  - Display: directional arrow + "trending" label on KPI cards

- [x] **Anomaly detection on stock depletion rate** ✓ implemented
  Flags SKUs whose depletion rate (qty_sold_30d / current_qty) is > 2σ above mean.
  - Algorithm: `Phpml\Math\Statistic\Mean` + `StandardDeviation::population()`
  - Input: cross-referenced `low_stock_list` × `top_sellers` from `ProductMetricsCollector`
  - Service: `Model/Service/Ml/StockAnomalyAnalyzer.php`
  - Output: `depletion_rate` float + `anomaly` bool added to each item in `getLowStockProducts()`

> **Hard limits to respect for all php-ml usage**
> - Train only inside cron jobs — never during a web request
> - Dataset per computation must fit comfortably in PHP memory (<50 MB)
> - Do not use for time-series forecasting, demand prediction, or inventory replenishment —
>   those belong in `GetInsights` / `Chat` via the LLM pipeline

---

## Extension Points

- **Add a new metric group**: Implement a new collector, inject into `DashboardDataService`
  via `di.xml` argument injection
- **Override chart colours / layout**: override `view/adminhtml/templates/dashboard/index.phtml`
- **Use a different AI agent**: override `Block/Adminhtml/Dashboard.php::getAgentCode()`
- **Adjust cache TTLs**: configure via `Stores > Configuration > AI Studio > AI Dashboard > Cache TTLs`
- **Adjust low-stock threshold**: configure via `Stores > Configuration > AI Studio > AI Dashboard > Data Collection`

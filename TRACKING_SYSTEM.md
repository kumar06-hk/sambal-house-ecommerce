# Order Tracking & Auto-Advancement System

This document explains how the live order tracking, refund tracker, and return tracker work under the hood — including how statuses advance automatically and where admin approval is required.

---

## Overview

All three trackers (order, refund, return) share the same core pattern:

- Time-based steps advance automatically every **60 seconds** after each milestone timestamp
- Advancement is driven by **MySQL `TIMESTAMPDIFF`**, not PHP `time()`, to avoid timezone mismatches on XAMPP Windows
- No cron job is needed — advancement is triggered whenever a relevant page is loaded
- **Refund and return acceptance require explicit admin approval** — these steps do not auto-advance

---

## The Three Trackers

### 1. Order Tracker (`track_order.php`)

**Steps:**
```
Order Placed → Order Paid → Order Shipped Out → Order Received → Order Completed
```

All steps advance automatically every 60 seconds after payment.

**Database columns used:**

| Column | Set when |
|---|---|
| `created_at` | Order placed |
| `paid_at` | FPX payment confirmed |
| `shipped_at` | Auto — 60s after `paid_at` |
| `received_at` | Auto — 60s after `shipped_at` |
| `completed_at` | Auto — 60s after `received_at` |

**Status ENUM progression:**
```
PENDING → PAID → SHIPPED → RECEIVED → COMPLETED
```

---

### 2. Refund Tracker (`refund_track.php`)

Triggered when a customer cancels a **PAID** order. Cancelling a `PENDING` order (never paid) does **not** enter this flow — no money was taken so no refund is owed. All refund queries guard against this with `AND paid_at IS NOT NULL`.

**Steps:**
```
Cancellation Requested → [Admin Approves] → Refunded
```

**Step 1 → 2 requires admin action.** The customer's page shows "Awaiting Admin Approval" with no countdown until the admin clicks "Approve Refund" in the admin orders panel.

**Step 2 → 3 is automatic** — 60 seconds after admin sets `cancellation_accepted_at`.

**Database columns used:**

| Column | Set when |
|---|---|
| `cancelled_at` | Customer cancels order |
| `cancellation_accepted_at` | **Admin clicks "Approve Refund"** |
| `refunded_at` | Auto — 60s after `cancellation_accepted_at` |

Order status stays `CANCELLED` throughout — the refund flow is tracked separately via these timestamp columns.

---

### 3. Return Tracker (`return_track.php`)

Triggered when a customer requests a return on a COMPLETED order.

**Steps:**
```
Return Requested → [Admin Approves] → Refunded
```

**Step 1 → 2 requires admin action.** The customer's page shows "Awaiting Admin Review" with no countdown until the admin clicks "Approve Return" in the admin orders panel.

**Step 2 → 3 is automatic** — 60 seconds after admin sets `return_accepted_at`.

**Database columns used:**

| Column | Set when |
|---|---|
| `return_requested_at` | Customer clicks Return Item on a COMPLETED order |
| `return_accepted_at` | **Admin clicks "Approve Return"** |
| `return_refunded_at` | Auto — 60s after `return_accepted_at` |

Order status stays `RETURNED` throughout — the return flow is tracked via its own timestamp columns.

---

## How Auto-Advancement Works

### The timezone-safe pattern

All elapsed time checks happen entirely inside MySQL:

```sql
TIMESTAMPDIFF(SECOND, paid_at, NOW()) >= 60
```

This avoids a common XAMPP Windows bug where `PHP time()` and `MySQL NOW()` operate in different timezones, causing countdowns to show values like `360:51` instead of `0:59`.

### Where advancement is triggered

Advancement runs as plain `UPDATE` queries at the **PHP level**, before the page renders. This means the page always shows the correct state immediately — no JavaScript flash of stale data.

| Page loaded | What advances |
|---|---|
| `orders.php` | ALL in-progress orders for the user (order + refund + return auto steps only) |
| `track_order.php` | That specific order's progression |
| `refund_track.php` | That order's refund step (step 2→3 only) |
| `return_track.php` | That order's return step (step 2→3 only) |

### Example advancement query (order tracker)

```sql
UPDATE orders
SET status = 'SHIPPED', shipped_at = DATE_ADD(paid_at, INTERVAL 60 SECOND)
WHERE user_id = ?
  AND status IN ('PAID', 'PROCESSING')
  AND paid_at IS NOT NULL
  AND TIMESTAMPDIFF(SECOND, paid_at, NOW()) >= 60
```

The same pattern is repeated for each step transition. Note the timestamp is set to `DATE_ADD(prev_col, INTERVAL 60 SECOND)` — not `NOW()` — so the recorded time is always exactly 60 seconds after the previous step regardless of when the page is visited.

---

## Admin Approval Actions

The admin panel (`admin/orders.php`) shows action buttons for orders that require approval:

| Order state | Button shown | What it does |
|---|---|---|
| RETURNED + `return_accepted_at IS NULL` | **Approve Return** | Sets `return_accepted_at = NOW()` |
| CANCELLED + `paid_at IS NOT NULL` + `cancellation_accepted_at IS NULL` | **Approve Refund** | Sets `cancellation_accepted_at = NOW()` |

Once approved, the 60-second auto-advance to the refunded state kicks in on the customer's next poll.

The admin orders page also **auto-polls every 15 seconds** via `orders_poll.php`. If a new order arrives or a new return request is submitted, a SweetAlert2 toast appears and the page auto-reloads after 1.8 seconds.

---

## Live Polling (JavaScript)

After the PHP render, the tracker pages also run a JavaScript polling loop for real-time visual updates:

| Behaviour | Detail |
|---|---|
| Background poll | Every 20 seconds via `setInterval` |
| Immediate poll on load | Fires instantly if `nextIn <= 0` (already overdue) |
| Countdown bar | Shows remaining seconds until next auto step (step 2→3 only for refund/return) |
| Step pop animation | CSS keyframe animation on each newly completed icon |
| Toast notification | SweetAlert2 toast fires on page load for the current step, and again live when a step advances |

The polling calls a JSON API endpoint (`order_progress_api.php`, `refund_progress_api.php`, or `return_progress_api.php`) which runs the same advancement logic and returns the updated step data.

---

## API Endpoints

| Endpoint | Purpose |
|---|---|
| `order_progress_api.php` | Advances and returns order tracker state |
| `refund_progress_api.php` | Advances step 2→3 only; returns refund tracker state |
| `return_progress_api.php` | Advances step 2→3 only; returns return tracker state |
| `admin/orders_poll.php` | Returns order count, max ID, and pending return count for admin live polling |

All endpoints require the user to be logged in and verify `user_id` ownership before making any changes.

---

## Database Migration Files

| File | What it adds |
|---|---|
| `database/migrate_order_tracking.sql` | `paid_at`, `shipped_at`, `received_at`, `completed_at` |
| `database/migrate_cancellation_tracking.sql` | `cancelled_at`, `cancellation_accepted_at`, `refunded_at` |
| `database/migrate_return_tracking.sql` | `return_requested_at`, `return_accepted_at`, `return_refunded_at` |

These have already been applied to the live database. The base schema in `database/sambal_house.sql` includes all columns for a clean fresh install.

---

## Why No Cron Job

A cron job would require server-level scheduling (not available in standard XAMPP) and adds deployment complexity. The API-driven approach achieves the same result:

- Statuses advance on demand when any relevant page is loaded
- MySQL handles all time calculations, so it works correctly regardless of PHP/server timezone settings
- No background processes, no daemon, no scheduler needed

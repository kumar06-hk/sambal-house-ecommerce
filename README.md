# Sambal House — E-Commerce Web Application

A PHP/MySQL e-commerce web application built for CIT6224 (Web Application Development), Multimedia University.

## My contribution (Frontend Developer & UI/UX Designer)

I led frontend development and UI/UX design across the customer-facing site:

**Product Catalogue & Filtering**
- Product catalogue page with live search (debounced 300ms)
- Multi-filter system: price range, stock status, category, sort order
- Filter + sort combination with animated card reflow
- Results counter and "no products found" empty state with reset button
- Login-required gate for non-authenticated visitors

**Product Detail Page**
- Two-column layout: image lightbox + product info
- Breadcrumb navigation, inline rating row linking to reviews
- Price box with quantity stepper, stock/origin/description info rows
- "You May Also Like" recommendation grid

**Shopping Cart & Checkout**
- Cart page with quantity stepper and stock cap enforcement
- Live cart item count in the navbar
- Checkout page with saved-address autofill

**Receipts & Invoices**
- Customer receipt page for paid orders, with client-side PDF download (jsPDF) — no server dependency
- Print-friendly receipt layout
- Admin order detail view with shipping & item breakdown
- Customer purchase summary card (total orders, lifetime spend, last order)

**Full Site Styling & UI System**
- Hand-written CSS, zero frameworks
- Dark-theme CSS variable system, card hover animations, gradient styling
- Fully responsive layout across all pages
- Reusable page-header component, status badge system, flash alerts
- Responsive navbar with mobile hamburger menu

## Built by teammates

Backend infrastructure, admin panel logic, payment gateway integration, email/mailer system, security hardening, and the order tracking system were built by teammates as part of this group project.

## Tech stack

PHP, MySQL, HTML, CSS, JavaScript, jsPDF

## Setup

1. Requires a local PHP + MySQL environment (e.g. XAMPP).
2. Import the schema from `database/sambal_house_schema.sql` into MySQL.
3. Copy `config/app.example.php` to `config/app.php` and fill in your own SMTP credentials.
4. `config/db.php` uses default local XAMPP credentials (`root` / no password) — adjust for your environment if different.
5. Place the project in your web server's document root and access via `public/index.php`.

## Note on database file

`database/sambal_house_schema.sql` contains only table structure (schema) — sample data rows have been removed to protect team members' test data used during development.

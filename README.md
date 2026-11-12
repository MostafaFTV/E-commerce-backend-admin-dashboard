# The Kitchen Boutique — Commerce Backend & Admin Platform

**Laravel API · Next.js Admin · Inventory & Order Workflows · AI-Assisted Shopping Architecture**

A portfolio-oriented, sanitized source snapshot of the backend and administrative application developed for **The Kitchen Boutique (TKB)**, a premium kitchenware e-commerce platform. This repository focuses on the engineering behind the storefront: structured catalog data, commerce workflows, access-controlled operations, content management, automated verification, and the architecture of an optional shopping assistant.

> **Repository scope:** This is a **backend + admin monorepo**, not the complete customer-facing storefront or a production deployment package. It is intended for code review, technical demonstration, and local development. Production data, credentials, deployment infrastructure, licensed assets, and private business knowledge are not part of this public snapshot.

## At a glance

| Area | Implementation |
| --- | --- |
| Backend | PHP 8.4+, Laravel 13, Eloquent ORM, REST-style JSON API |
| Admin | Next.js 16.3.5, React, TypeScript, Tailwind CSS |
| Data layer | Relational schema, migrations, model relationships, database-backed workflows |
| Authentication & access | Customer/admin authentication flows, Laravel Sanctum integration, roles and permissions |
| Localization | Persian/English content support; Persian RTL administration |
| Commerce | Catalog, variants, inventory, cart, checkout, orders, payments, shipping |
| Operations | Customers, staff, CMS, support tickets, activity logs, notifications |
| AI subsystem | Luna shopping assistant, consultation state, catalog grounding, provider abstraction, usage/budget safeguards, optional research pipeline |
| Verification | Laravel feature/unit tests, TypeScript checking, Next.js production build |

## Repository layout

```text
.
├── backend/
│   ├── app/
│   │   ├── Console/            # Maintenance and scheduled commands
│   │   ├── Contracts/          # Service/provider contracts
│   │   ├── Enums/              # Domain states
│   │   ├── Http/               # Controllers, middleware, requests, resources
│   │   ├── Models/             # Eloquent models
│   │   ├── Services/           # Commerce and assistant business logic
│   │   └── Support/            # Search, locale and date utilities
│   ├── bootstrap/              # Application bootstrap
│   ├── config/                 # Environment-driven configuration
│   ├── database/               # Migrations and model factories
│   ├── resources/luna/         # Sanitized public demonstration guidance
│   ├── routes/                 # Public, customer and admin API routes
│   └── tests/                  # Unit and feature tests
├── admin/
│   ├── src/app/                # Pages, layouts and server route handlers
│   ├── src/components/         # Admin forms, controls and views
│   └── src/lib/                # Admin API clients, types and utilities
├── .gitignore
└── README.md
```

The `admin/` application communicates with the Laravel backend through its configured API integration. The backend remains authoritative for persisted business data, validation, permissions, inventory, and commerce state changes. The Next.js application provides the operator interface and supporting server-side route handlers.

## Architecture and engineering approach

### Separation of responsibilities

- **HTTP layer:** Laravel controllers and request classes handle API entry points, validation, authorization, and responses. API resources define serialized output.
- **Domain layer:** Dedicated services encapsulate responsibilities such as cart updates, checkout, inventory, order status changes, payment reservation, cashback, and wallet accounting.
- **Persistence layer:** Eloquent models and migrations maintain relationships and business records.
- **Admin layer:** Next.js pages and reusable components provide management workflows, with supporting API utilities and typed data structures.
- **External integrations:** Provider-specific behavior is isolated behind services or contracts rather than being treated as the source of truth for catalog, stock, or payment state.

This structure is designed to keep financial and inventory decisions on the server and to make business rules testable independently of the UI.

### Localization and interface direction

The data model and API include support for localized content, including Persian and English fields across relevant catalog and CMS entities. The admin interface is primarily Persian and rendered right-to-left (`lang="fa"`, `dir="rtl"`). The public copy uses a system-font fallback in place of the original licensed Dana font assets.

## Implemented functional areas

### 1. Catalog and merchandising

- Products, categories, nested collections, brands, and configurable attributes/attribute values.
- Product variants with explicit lifecycle and availability states.
- Product image records, ordering, and attribute-aware media associations.
- Localized names and descriptions, catalog search/indexing helpers, and SEO-related fields.
- Administrative tools for product editing and spreadsheet-based product import/export workflows.
- Content-driven homepage and navigation data served through backend APIs.

**Design principle:** Product publication, variant eligibility, and stock decisions are determined by server-side catalog rules—not inferred by the UI or an AI provider.

### 2. Inventory and order processing

- Inventory records and movement-related logic.
- Cart operations for guests and authenticated customers, including supported non-variant products.
- Checkout sessions and address/shipping selections.
- Orders, line items, order-state history, and guarded status transitions.
- Payment abstractions, callback handling, pending-payment expiration, and related reconciliation safeguards.
- Tests addressing inventory conflicts, rate limits, authorization boundaries, and state consistency.

The codebase models **ordering, payment, and inventory as coordinated but distinct concerns**. This separation makes it possible to enforce rules on the backend rather than depending on a particular frontend interaction.

### 3. Accounts, administration, and access control

- Customer registration/login-related API flows and profile management.
- OTP and password-related service components.
- Administrative authentication plus staff, roles, and permissions management.
- Permission and role middleware; active-user checks.
- Customer records, addresses, wishlist, and order-related account features.
- Operational activity logs and administrative notification interfaces.

### 4. Promotions and financial account features

- Discount-code management and customer discount associations.
- Cashback rules, wallet credits/allocations/transactions, and reversal/spending logic.
- Supporting migrations and automated tests for accounting-oriented workflows.

These are application features, **not** a claim that a live payment provider or financial integration is enabled in the public snapshot.

### 5. Content and customer operations

- Homepage sections, banners, navigation menus, and page content.
- Contact-page content, customer contact messages, and FAQ management.
- Stores and shipping-method administration.
- Support tickets, replies, ticket status actions, and operational customer-service views.
- Admin pages covering catalog, inventory, orders, customers, staff, permissions, promotions, and CMS workflows.

## Luna — shopping assistant architecture

The backend includes an optional shopping-assistant subsystem named **Luna**. The public version demonstrates the integration boundaries and safety-oriented architecture without publishing private behavioral knowledge or production credentials.

### Key architectural elements

- **Provider abstraction:** Mock and external-provider implementations are separated so most behavior can be exercised without live API calls.
- **Catalog grounding:** Server-owned catalog data determines which products exist, what can be recommended, and which inventory or availability claims are valid.
- **Consultation continuity:** Server-managed conversation/session state and structured consultation preferences support multi-turn interactions.
- **Language behavior:** Persian, English, and transliterated Persian (Finglish) are addressed in prompt and language-handling logic.
- **Response boundaries:** Product identifiers, URLs, availability, and commercial claims are subject to server-side rules rather than unconditional trust in generated text.
- **Usage controls:** Budget reservation/reconciliation, daily quota logic, usage diagnostics, and failure classification are represented by dedicated components and tests.
- **Research pipeline:** On-demand product research modules include request triggering, evidence verification, fact publication, and processing commands. An external research provider is **not required** for normal offline tests.
- **Defensive behavior:** The subsystem includes tests for prompt separation, provider failure handling, session consistency, product identity, and unsupported product claims.

### Public configuration

The public environment example keeps external shopping-assistant use disabled or locally controlled by default. Mock functionality is intended for offline development; connecting a live provider requires independently obtained credentials, explicit configuration, and operational review.

Files under `backend/resources/luna/` contain **sanitized demonstration material**. They are not the production knowledge base, and this repository should not be interpreted as a full release of the original assistant's private instruction assets.

## API organization

The Laravel routes are separated into public/customer-facing API operations and administrative operations. The exact method, payload, authentication requirement, and response structure should be read from the route declarations, controllers, request classes, and API resources rather than inferred from this overview.

Representative API areas include:

| API area | Responsibility |
| --- | --- |
| Catalog | Products, categories, collections, brands and related content |
| Storefront content | Homepage, menus, pages, FAQ, contact and stores |
| Customer account | Authentication, profile, addresses, wishlist and orders |
| Shopping flow | Cart, checkout, shipping and payments |
| Admin operations | Catalog editing, inventory, orders, customers, staff and access control |
| Operational tools | Promotions, cashback, support, notifications and activity logs |
| Assistant | Shopping consultation and associated backend safeguards |

Not every operation is suitable for anonymous access. Administrative endpoints are expected to be used with the backend's authentication and authorization controls.

## Security-oriented design

The included code and test suite address several security-sensitive boundaries:

- Backend authorization rather than UI-only permission checks.
- Rate limiting for selected cart, wishlist, payment, and support-related endpoints.
- Trusted-host/trusted-proxy and forwarding-header handling.
- Input validation, session/state checks, and error-response safety.
- Separation between trusted catalog facts and untrusted customer or provider-generated content.
- Safeguards for assistant budgets and token/usage handling.
- Guarded order/payment transitions and inventory consistency.

**Scope warning:** Passing tests and a source scan are not a penetration test, security certification, or authorization to deploy this snapshot to production. Real deployments still require configuration review, dependency updates, transport security, backups, monitoring, and an assessment of any external services.

## Local development

### Prerequisites

- **Backend:** PHP 8.4 or newer, Composer, and the PHP extensions required by `backend/composer.json`.
- **Database:** A compatible relational database; the local test workflow has been exercised with SQLite in memory.
- **Admin:** A Node.js/npm version compatible with the package manifest and lockfile.

No credentials, prepopulated production database, uploaded customer media, or operational service accounts are supplied.

### Backend setup

From `backend/`:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

On Windows PowerShell, replace `cp` with `Copy-Item .env.example .env` if preferred. Review `.env.example` before running migrations and configure the desired database connection; do not point this public copy at a production database.

For a local SQLite database, configure the corresponding `DB_CONNECTION` and `DB_DATABASE` values in your untracked `.env`. Whether additional application integrations are operational depends on the credentials and services explicitly configured.

### Admin setup

From `admin/`:

```bash
npm ci
cp .env.example .env.local
npm run dev
```

Configure the API address using the variables documented in `admin/.env.example`. Start the backend before testing data-driven admin screens. Authentication-protected views require valid local users and permissions; this snapshot does not ship real staff accounts.

### Build and verification

Backend:

```bash
php artisan test
```

For the public snapshot's offline test run, the environment used `APP_ENV=testing`, SQLite `:memory:`, and `SHOPPING_ASSISTANT_ENABLED=true`, with no live GapGPT credential. Some test cases also supply fake provider credentials internally and use HTTP fakes; do not insert real credentials to run the test suite.

Admin:

```bash
npx tsc --noEmit
npm run build
```

**Verified public-staging snapshot (12 November 2026):**

| Verification | Result |
| --- | --- |
| Laravel suite | **1,566 passed**, **1 skipped**, 6,801 assertions; exit code 0 |
| Admin dependency installation | `npm ci` completed successfully |
| Admin TypeScript check | Passed |
| Admin Next.js production build | Passed |

The skipped backend test is an optional live-provider scenario. Results are specific to the tested local snapshot and environment; they do not guarantee an identical result on all machines.

## Public-release boundaries

This repository is deliberately narrower than the complete commercial project. It **does not include**:

- Production `.env` files, real API keys, tokens, private SSH keys, or provider credentials.
- Production infrastructure and deployment workflows.
- Original repository history or internal deployment documentation.
- Customer databases, orders, personal data, operational logs, or backups.
- Uploaded catalog photography, production media libraries, or proprietary font binaries.
- Private Luna knowledge/instruction sources and proprietary research material.
- The separate customer-facing storefront application.

The public copy has been prepared with `.gitignore` exclusions for generated caches, installed dependencies, build outputs, and local environment files. Before publishing, perform an actual Git-index review and an independent check for sensitive data and redistribution rights. A filename/pattern scan alone cannot guarantee that all private or third-party material has been removed.

## Engineering highlights

For reviewers interested in implementation details, useful starting points include:

- `backend/app/Services/` — business-rule orchestration for catalog, commerce, payments, wallets and assistant behavior.
- `backend/app/Http/` — request validation, route handling, security middleware, and response resources.
- `backend/database/migrations/` — evolution of the domain schema.
- `backend/tests/Feature/` — behavior-driven API and integration-oriented coverage.
- `admin/src/app/(panel)/` — management screens and nested routes.
- `admin/src/components/` — reusable operators' interfaces and workflow forms.
- `admin/src/lib/` — backend communication and shared type definitions.

## Status and intended use

**Status:** Sanitized portfolio staging, with backend tests and admin build verified locally. Not production-deployable as supplied.

The repository demonstrates the architecture and implementation of a substantial e-commerce management system. It is provided for technical review and local experimentation, not as a turnkey hosted service or as a representation that production integrations, customer accounts, payment processing, and external AI services are active.

---

*The Kitchen Boutique (TKB) — Backend & Admin engineering portfolio snapshot.*

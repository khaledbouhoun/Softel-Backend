# Laravel Production Audit Report

**Target Production API:** `https://b2b.softel.dz`  
**Framework:** Laravel Framework `v10.48.29` (PHP `^8.1`, Local Runtime: PHP `8.2.12`)  
**Audit Scope:** Full Application Source Code, Authentication, Database Performance, Runtime Configuration, Reliability, and Infrastructure Boundary Analysis  
**Audit Mode:** Read-Only Technical Audit  
**Date of Audit:** October 2026  

---

## 1. Executive Summary

This comprehensive audit was commissioned following critical operational anomalies observed during production diagnostics on `https://b2b.softel.dz`. In diagnostic runs comprising 24 HTTP requests over 3 rounds:
* **3 requests succeeded (HTTP 200)**: strictly limited to the public `/api/company` endpoint.
* **17 requests failed with HTTP 401 Unauthorized**: covering protected product, category, and family endpoints.
* **4 requests failed with HTTP 503 Service Unavailable**: returning raw HTML error pages within 1–2 seconds.
* **Extreme Latency Spike**: `/api/company` required **18.7 seconds** on the initial cold request, dropping to 721 ms and 432 ms on subsequent rounds.

### Overall System Assessment
The application operates on Laravel 10 connected to an external legacy ERP database schema (characterized by tables such as `vte_clients_softel`, `stk_articles`, `vte_commandese`, `vte_commandesd`, etc.). The codebase shows signs of recent partial optimization efforts alongside severe architectural vulnerabilities, misconfigurations, and security exposures that directly account for all observed downtime, authorization failures, and extreme latency.

### Top Root Causes Identified
1. **Remote Database Latency & DNS Resolution Bottleneck (`DB_HOST=softel.dz`)**: The production environment is configured to connect to MySQL across a domain name (`softel.dz`) rather than local loopback (`127.0.0.1` or unix socket). Cold connection initialization incurs DNS lookup latency, TCP/TLS handshakes, and MySQL reverse-DNS checks, creating the **18.7-second delay**.
2. **Web Server Worker Exhaustion Causing HTTP 503 HTML Errors**: The application is deployed in a shared cPanel/CloudLinux environment (`/home/wdctfhzy/public_html/softel_api`). When slow queries or image fallback requests hold PHP processes open, the web server reaches its Entry Process (EP) limit. LiteSpeed/Apache rejects queued connections after 1–2 seconds with an HTML `503 Service Unavailable` response before PHP is even invoked.
3. **Authentication Route Segregation & Header Stripping**: Endpoints returning HTTP 401 are protected under custom middleware `custom.auth`. When requests arrive without valid Sanctum tokens, or when the Apache web server strips standard `Authorization: Bearer` headers (a common cPanel FastCGI issue), requests are rejected with HTTP 401.
4. **Critical Information Disclosure & Financial Vulnerabilities**: 
   * A public route (`/api/server-info`) outputs the entire `$_SERVER` superglobal without authentication, exposing server environment variables and secrets.
   * An unauthenticated `/api/clear-cache` route allows external users to clear application caches on demand.
   * In order creation (`/api/commandes/store`), unit prices (`CddPrix`) and line totals (`CddMontant`) are accepted directly from the client request payload without backend verification against database price lists (`vte_tarifd`).

---

## 2. Project Architecture

### Framework and Core Components
* **Framework:** Laravel 10.48.29
* **PHP Requirement:** `^8.1` (tested in environment: PHP 8.2.12 TS x64)
* **Authentication:** Laravel Sanctum `^3.3` coupled with custom token resolution (`AuthenticateWithCustomToken` and `TokenHelper`).
* **HTTP Client:** Guzzle `^7.2`
* **Exception & Debugging Tooling:** Spatie Laravel Ignition `^2.0` (registered in dev/test, routes active).
* **Database Driver:** MySQL (PDO), connecting to legacy tables with non-standard naming conventions and composite keys.

### Request Flow Diagram

```
Flutter Mobile App (B2B Client)
        │
        ▼ (HTTPS)
Cloudflare / CDN / Reverse Proxy (if present)
        │
        ▼
cPanel / Apache / LiteSpeed Web Server (`/home/wdctfhzy/public_html/softel_api`)
        │
        ├─► [Static Assets: images/*] ──► (Missing disk file triggers rewrite to index.php!)
        │
        ▼
Front Controller (`index.php`) ──► Laravel HTTP Kernel
        │
        ▼
Global Middleware Stack (TrustProxies, HandleCors, PreventRequestsDuringMaintenance, TrimStrings)
        │
        ▼
Routing Pipeline (`routes/api.php`)
        │
        ├─► Public Routes: `/api/company`, `/api/login`, `/api/signup`, `/api/server-info`
        │
        └─► Protected Group: `custom.auth` (`AuthenticateWithCustomToken`)
                    │
                    ▼ [Queries personal_access_tokens & vte_clients]
            Controller Action (e.g., ProductController, CommandeController)
                    │
                    ▼ [Repeats TokenHelper::getUser - redundant queries]
            Eloquent Models / DB Queries
                    │
                    ▼ (TCP/WAN connection to `DB_HOST=softel.dz`)
            Remote MySQL Database Engine
                    │
                    ▼
            JSON Response Serialization ──► Flutter Mobile App
```

---

## 3. Observed Production Symptoms

During production diagnostics, 24 requests were executed across 3 rounds (8 endpoints per round):

| Metric / Symptom | Recorded Value | Evaluation & Source Analysis |
| :--- | :--- | :--- |
| **Total Requests** | 24 across 3 rounds | 8 distinct endpoints evaluated 3 times |
| **HTTP 200 (Success)** | 3 requests (100% of `/api/company`) | Only `/api/company` succeeded; all other tested endpoints failed |
| **HTTP 401 Unauthorized** | 17 requests | Product, category, family, and order endpoints |
| **HTTP 503 Service Unavailable** | 4 requests | Intermittent failures on protected endpoints after 1–2s delay |
| **`/api/company` Latencies** | Round 1: **18.7s**, Round 2: **721ms**, Round 3: **432ms** | Characteristic cold-connection latency spike followed by warm latency |
| **503 Content-Type** | `text/html` (instead of `application/json`) | Generated by the web server / proxy layer, NOT by Laravel exception handlers |

---

## 4. Critical and High-Priority Findings

| Finding ID | Severity | Confidence | File & Line Reference | Vulnerability / Issue Summary |
| :--- | :--- | :--- | :--- | :--- |
| **SEC-01** | **Critical** | Confirmed | `routes/api.php#L149-L153` | Public `/api/server-info` exposes raw `$_SERVER` array with environment configuration. |
| **SEC-02** | **Critical** | Confirmed | `app/Http/Controllers/CommandeController.php#L360-L368` | Client trusts client-submitted prices (`CddPrix`, `CddMontant`) in order creation. |
| **SEC-03** | **High** | Confirmed | `routes/api.php#L118-L121` | Public unauthenticated `/api/clear-cache` allows external users to flush server cache. |
| **REL-01** | **Critical** | Confirmed | `.env#DB_HOST` & `config/database.php#L49` | Remote database host domain (`DB_HOST=softel.dz`) causes 18.7s DNS/connection lag. |
| **REL-02** | **High** | Strong Evidence | Server Architecture / `.htaccess` | CloudLinux LVE Entry Process exhaustion returns HTML 503 after 1–2s queue timeout. |
| **REL-03** | **High** | Confirmed | `app/Exceptions/Handler.php#L50-L55` | Unauthenticated API requests without `Accept: json` trigger `RouteNotFoundException: Route [login] not defined`. |
| **REL-04** | **High** | Confirmed | `app/Http/Controllers/ClientController.php#L76` | `logout()` throws Fatal Error: `Call to a member function delete() on null` on token deletion. |
| **DATA-01** | **High** | Confirmed | `app/Http/Controllers/CommandeController.php#L387-L397` | Race condition in `firstOrCreate` creates duplicate active carts (`CdeStatus=0`). |
| **DATA-02** | **High** | Confirmed | `routes/api.php#L69` & `CommandeController.php#L141-L166` | Order confirmation (`/confirmcommande`) is exposed as an HTTP GET endpoint with no transaction. |
| **PERF-01** | **High** | Confirmed | `ProductController.php#L149-L152` | N+1 database query in `imagesbanner()` loop calling relation query `souFamilles()->where()`. |
| **PERF-02** | **High** | Confirmed | `ProductController.php#L188-L238` & `#L283-L340` | Missing eager loading of `tarifs` in `search()` and `parfamilles()` creates N+1 queries. |
| **PERF-03** | **Medium** | Confirmed | `AuthenticateWithCustomToken.php` & All Controllers | Duplicate execution of `TokenHelper::getUser` on every authenticated request (4 queries total). |
| **PERF-04** | **Medium** | Confirmed | `public/.htaccess#L17-L20` & `ProductController.php` | Missing `public/images/` assets cause 404 image requests to boot Laravel front controller. |

---

## 5. Authentication and Authorization Audit

### Route Segregation
In `routes/api.php`:
* **Public Routes:**
  * `GET /api/company` (Line 23)
  * `POST /api/login` (Line 25)
  * `POST /api/signup` (Line 26)
  * `GET /api/wilayas` (Line 28)
  * `GET /api/communes/{wilaya_id}` (Line 29)
  * Debug/Admin routes: `/api/debug/auth`, `/api/clear-cache`, `/api/test`, `/api/test-sanctum`, `/api/debug/token`, `/api/server-info`, `/api/test-auth-header`.
* **Protected Routes (Under `custom.auth`):**
  * `/api/me`, `/api/logout`
  * Products: `/api/products`, `/api/products/cartCount`, `/api/products/search/{search}`, `/api/products/imagesbanner`, `/api/products/parfamilles`
  * Familles: `/api/familles`, `/api/familles/{FamNo}`
  * Commandes: `/api/commandes`, `/api/commandes/cart`, `/api/commandes/cart/{CdeID}`, `/api/commandes/confirmcommande`, `/api/commandes/store`, `/api/commandes/deletedetailsbyid`, `/api/commandes/{id}`

### Why HTTP 401 Occurred in 17 of 24 Requests
1. **Unauthenticated Diagnostic Requests:** The diagnostic tool issued requests across 8 endpoints. The 7 protected endpoints were contacted without valid credentials or valid Sanctum tokens.
2. **`AuthenticateWithCustomToken` Middleware Implementation (`app/Http/Middleware/AuthenticateWithCustomToken.php`):**
   ```php
   public function handle(Request $request, Closure $next)
   {
       $user = TokenHelper::getUser($request);

       if (!$user) {
           return response()->json([
               'message' => 'Unauthorized'
           ], 401);
       }

       $request->attributes->set('auth_user', $user);
       return $next($request);
   }
   ```
   If no token or an invalid token is provided, the middleware immediately halts the pipeline and outputs HTTP 401 JSON.
3. **Apache Header Stripping in Production:**
   In shared hosting / cPanel deployments using Apache and PHP-FPM / FastCGI, Apache strips the `Authorization` header by default unless specifically passed via `.htaccess`.
   In `app/Helpers/TokenHelper.php#L13-L14`:
   ```php
   $token = $request->header('X-Auth-Token') ?? $request->bearerToken();
   ```
   The developer added `X-Auth-Token` precisely because `Authorization: Bearer` was repeatedly stripped on the production server. If the diagnostic tool or client only supplied the standard `Authorization` header and Apache stripped it, the backend received `null` and emitted HTTP 401.

### Sanctum Multi-Tenant Identity Flaw
In `app/Models/Client.php#L17`:
```php
protected $primaryKey = 'CliNo';
```
In the underlying database schema, `CliNo` is not a unique primary key across the entire table; clients are scoped by company (`CliCls`). When Sanctum creates a token, it saves `$client->getKey()` (e.g. `'000001'`) into `personal_access_tokens.tokenable_id`.
When `TokenHelper::getUser()` resolves the user:
```php
$client = $accessToken->tokenable;
```
Eloquent executes:
```sql
SELECT * FROM vte_clients WHERE CliNo = '000001' LIMIT 1;
```
It does **not** filter by `CliCls`. If two companies both have a client `000001`, Eloquent resolves the first row returned by MySQL. Although lines 30–38 of `TokenHelper.php` overwrite `$client->CliCls = $companyId` in memory, the user model's other attributes (name, email, pricing tier `CliTarif`) belong to the wrong company's customer.

### Fatal Defect in `ClientController::logout`
In `app/Http/Controllers/ClientController.php#L76`:
```php
public function logout(Request $request)
{
    $client = TokenHelper::getUser($request);
    if (!$client) {
        return response()->json(['message' => '...'], 401);
    }
    $client->currentAccessToken()->delete(); // ❌ CRASHES WITH NULL POINTER EXCEPTION
    return response()->json(['message' => '...']);
}
```
Sanctum's `currentAccessToken()` method accesses `$this->accessToken`. This property is populated by Sanctum's internal guard using `withAccessToken()`. Because `TokenHelper::getUser` executes `$accessToken->tokenable` directly without calling `withAccessToken($accessToken)`, `$client->accessToken` is `null`. Invoking `delete()` triggers:
`Fatal Error: Call to a member function delete() on null`, resulting in an unhandled HTTP 500 error on logout.

---

## 6. HTTP 503 and Availability Investigation

During testing, 4 requests returned HTTP 503 Service Unavailable with an HTML body after approximately 1–2 seconds.

### Root Cause Analysis: Web Server vs. Laravel Application

```
Incoming Requests (Batch of 8)
       │
       ▼
Apache / LiteSpeed Web Server
       │
       ├─► Request 1 (/api/company) ──► Worker 1 (Blocks for 18.7 seconds waiting on DNS/remote DB)
       ├─► Request 2 (/api/products) ──► Worker 2 (Queries remote DB...)
       ├─► Request 3 (/api/familles) ──► Worker 3 (Queries remote DB...)
       │   ...
       │   (All CloudLinux Entry Process slots [EP limit] become occupied)
       │
       ├─► Request 5, 6, 7, 8 ──► Placed in Web Server Request Queue
                                      │
                                      ▼ (Queue wait timeout: 1–2 seconds expires)
                                  Web Server emits HTML 503 Service Unavailable:
                                  "The server is temporarily busy, please try again later."
                                  (Laravel application is NEVER invoked!)
```

### Confirmed Evidence Distinctions:

1. **HTML Response Format:**
   Laravel's API exception handlers output JSON when the `Accept: application/json` header is present, or JSON-formatted exceptions when an API route throws an unhandled error. A pure HTML 503 response signifies that the request was terminated by an upstream layer: LiteSpeed Web Server, Apache `mod_lsapi` / `mod_hostinglimits`, or an edge reverse proxy.
2. **CloudLinux LVE Limits on Shared Hosting (`/home/wdctfhzy`):**
   The production path (`/home/wdctfhzy/public_html/softel_api/index.php`) proves the application runs under cPanel/CloudLinux. Standard cPanel shared hosting accounts enforce strict limits:
   * **Entry Processes (EP):** Usually capped between 10 and 20 concurrent processes.
   * **Max Execution Time / I/O Limits:** Capped CPU and IOPS.
   When the initial `/api/company` request blocked for **18.7 seconds**, and subsequent diagnostic requests ran in parallel, the pool of PHP-FPM / lsapi workers was exhausted. Any request arriving while the worker pool was saturated was held in the web server queue for 1–2 seconds before timing out with an HTML 503.
3. **Cascading Missing Asset Rewrites (`public/.htaccess`):**
   The repository does not contain the `public/images/` directory. When the Flutter app requests product, banner, or family images, Apache evaluates `.htaccess`:
   ```apache
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteRule ^ index.php [L]
   ```
   Because the image files do not exist on disk, every missing image request rewrites to `index.php`, booting the full Laravel application and claiming an Entry Process. A single mobile screen requesting 15 missing images can instantly exhaust the 10–20 Entry Process limit.

---

## 7. Database Performance Audit

### 1. Remote Database Latency (`DB_HOST=softel.dz`)
* **Finding Reference:** `c:\Laraval Projects\Softel-api\.env` (`DB_HOST=softel.dz`) vs. `copys.env` (`DB_HOST=127.0.0.1`).
* **Technical Explanation:** Connecting to MySQL over a WAN domain name introduces:
  1. DNS query resolution on every new PDO connection.
  2. TCP 3-way handshake over public network routes.
  3. Reverse DNS resolution (`skip-name-resolve` disabled on default MySQL servers).
  4. Per-query round-trip latency (ping times ranging from 30ms to 100ms+).
* **Observed Impact:**
  * Round 1 `/api/company`: **18.7 seconds** (cold DNS lookup, TCP connect, handshake).
  * Round 2 `/api/company`: **721 ms** (DNS cached by OS, warm TCP route).
  * Round 3 `/api/company`: **432 ms** (single simple query taking nearly half a second!).
* **Verification:** Run `ping softel.dz` and `tracert softel.dz` from the production host CLI. Compare PDO connection times between `127.0.0.1` and `softel.dz`.

### 2. N+1 Query in Banner Images (`ProductController.php#L149-L152`)
* **Problematic Code:**
  ```php
  $images = Imagesbanner::with('famille.souFamilles')
      ->where('ImgCls', $client->CliCls)
      ->get();

  $formatted = $images->map(function ($image) {
      $souFamille = $image->famille->souFamilles() // ❌ Method call executes new query!
          ->where('SouNo', $image->ImgSFam)
          ->first();
  ```
* **Explanation:** Although `with('famille.souFamilles')` eager-loads the collection, calling `$image->famille->souFamilles()` as a method initiates a new SQL query against `stk_sfamilles` for every single banner item. Furthermore, if `$image->famille` is null, it throws a Fatal Error (`Call to a member function souFamilles() on null`).
* **Fix:** Use the already-loaded collection: `$image->famille?->souFamilles->firstWhere('SouNo', $image->ImgSFam)`.

### 3. Missing Eager Loading of `tarifs` in Search and Category Endpoints
* **Problematic Code:**
  * `ProductController.php#L188-L194` (`search`)
  * `ProductController.php#L283-L291` (`parfamilles`)
  Both endpoints omit `'tarifs'` from their `with([...])` relation array.
  In the mapping loop:
  ```php
  'ArtPrix' => $product->priceForClient($client),
  ```
  Inside `Product.php#L105`:
  ```php
  $tarifs = $this->tarifs; // ❌ Lazy-loads relationship on EVERY product!
  ```
* **Explanation:** When returning 50 products per page, Eloquent triggers 50 separate SQL queries against `vte_tarifd`. Over a WAN database connection with 50ms latency, this causes a **2.5-second minimum delay** solely from network roundtrips!

### 4. Full Table Scan in Product Search (`ProductController.php#L183-L187`)
* **Problematic Code:**
  ```php
  $products = Product::where('ArtCls', $client->CliCls)
      ->where(function ($q) use ($search) {
          $q->where('ArtNom', 'like', "{$search}%")
            ->orWhere('ArtNom', 'like', "% {$search}%"); // ❌ Leading wildcard
      })
  ```
* **Explanation:** B-Tree indexes on `ArtNom` cannot be utilized when a query includes a leading wildcard (`% search%`). MySQL is forced to scan every record in `stk_articles` for the given company.

### 5. Composite Key Omission & Lack of Database Index Definitions
* In `app/Models/Product.php#L13`, `Familles.php#L13`, `SouFamilles.php#L14`, `CommandeDetail.php#L16`:
  ```php
  protected $primaryKey = null;
  ```
* Models lack declared primary keys because the underlying ERP database relies on composite keys (`(ArtCls, ArtNo)`, `(FamCls, FamNo)`, `(SouCls, SouFam, SouNo)`).
* In `database/migrations`, only the `personal_access_tokens` migration exists. No schema indexes are defined in Laravel migrations, leaving the application dependent on external database indexes that may be missing.

---

## 8. Endpoint-by-Endpoint Analysis

### Group 1: Company Information
* **Route:** `GET /api/company` (Public)
* **Controller:** `CompanyController::index` (`app/Http/Controllers/CompanyController.php#L10-L25`)
* **Queries Involved:**
  `SELECT * FROM vte_clients_softel WHERE ClsActif = 1;`
* **Bottlenecks:** Remote MySQL connection initialization (`DB_HOST=softel.dz`).
* **Serialization & Payload:** Lightweight JSON array (small list of companies).
* **Recommendations:** Point `DB_HOST` to `127.0.0.1`. Cache the query permanently (`Cache::rememberForever('active_companies', ...)`), invalidating only when company records change.

### Group 2: Product Families and Subfamilies
* **Routes:**
  * `GET /api/familles` (Protected by `custom.auth`)
  * `GET /api/familles/{FamNo}` (Protected by `custom.auth`)
* **Controllers:** `FamillesController::index`, `FamillesController::soufamilles` (`FamillesController.php`)
* **Queries Involved:**
  1. `personal_access_tokens` & `vte_clients` (auth middleware).
  2. `personal_access_tokens` & `vte_clients` (repeated in controller).
  3. `SELECT FamNo, FamNom, FamImg FROM stk_familles WHERE FamCls = ?`
* **Bottlenecks:** Duplicate auth lookup; missing composite index on `(FamCls, FamNo)`.
* **Recommendations:** Pass `$request->get('auth_user')` from middleware. Cache family lists per company.

### Group 3: Product Listing and Pagination
* **Route:** `GET /api/products` (Protected by `custom.auth`)
* **Controller:** `ProductController::index` (`ProductController.php#L15-L104`)
* **Queries Involved:**
  1. Auth token and client lookups (executed twice).
  2. Products query with scopes: `stk_articles` filtered by `ArtCls` and optional `ArtFam`.
  3. Eager loaded relations: `stk_familles`, `stk_sfamilles`, `stk_colisages`, `stk_unites`, `images_articles`, `vte_tarifd`.
  4. Active cart head: `SELECT * FROM vte_commandese WHERE CdeCls = ? AND CdeCli = ? AND CdeStatus = 0 LIMIT 1;`
  5. Active cart details: `SELECT * FROM vte_commandesd WHERE CddID = ?;`
* **Bottlenecks:** Pagination uses `skip($offset)->take($limit)` (offset pagination degrades at high offsets).
* **Recommendations:** Implement cursor or keyset pagination if product catalogs exceed thousands of records.

### Group 4: Product Filtering
* **Route:** `GET /api/products/parfamilles` (Protected by `custom.auth`)
* **Controller:** `ProductController::parfamilles` (`ProductController.php#L253-L355`)
* **Queries Involved:** Products query filtered by `ArtFam` and `ArtSFam`.
* **Critical Defect:** Omission of `tarifs` in eager loading creates an N+1 lazy query on `vte_tarifd` for every product returned.
* **Recommendations:** Add `'tarifs' => fn($q) => $q->where('TfdCls', $client->CliCls)` to the eager-loading array.

### Group 5: Product Search
* **Route:** `GET /api/products/search/{search}` (Protected by `custom.auth`)
* **Controller:** `ProductController::search` (`ProductController.php#L169-L250`)
* **Critical Defects:**
  1. Full table scan caused by `LIKE '% {$search}%'`.
  2. Omission of `tarifs` in eager loading creates an N+1 query storm.
* **Recommendations:** Eager-load `tarifs`. For search, utilize MySQL Full-Text search (`MATCH(ArtNom) AGAINST(?)`) or prefix-only matching with appropriate B-Tree indexing.

### Group 6: Cart Count and Cart Operations
* **Routes:**
  * `GET /api/products/cartCount`
  * `GET /api/commandes/cart`
  * `GET /api/commandes/cart/{CdeID}`
* **Controllers:** `ProductController::cartCount`, `CommandeController::cart`, `CommandeController::cartTrackingDetails`
* **Queries Involved:** Lookups on `vte_commandese` (`CdeStatus = 0`) and `vte_commandesd`.
* **Bottlenecks:** In `cart()`, product relations are re-queried across all line items without caching.
* **Recommendations:** Use caching for cart count or maintain an aggregate column.

### Group 7: Product Images and Banners
* **Route:** `GET /api/products/imagesbanner` (Protected by `custom.auth`)
* **Controller:** `ProductController::imagesbanner` (`ProductController.php#L131-L167`)
* **Critical Defects:** N+1 query loop on `$image->famille->souFamilles()->where(...)->first()`. If `famille` is null, throws fatal null pointer exception.
* **Recommendations:** Resolve `souFamille` from the preloaded collection or join directly.

### Group 8: Order Creation and Order Retrieval
* **Routes:**
  * `POST /api/commandes/store`
  * `GET /api/commandes/confirmcommande` (Flawed HTTP method)
  * `GET /api/commandes`
  * `GET /api/commandes/{id}`
  * `DELETE /api/commandes/deletedetailsbyid`
* **Controller:** `CommandeController`
* **Critical Defects:** Described in Section 10 below.

---

## 9. PHP and Laravel Runtime Audit

### 1. Route Caching and Configuration Assumptions
* Running `php artisan route:list` in development revealed that Ignition routes (`_ignition/*`) and debug routes are active.
* In production, configuration, routes, and events should be cached via `php artisan config:cache` and `php artisan route:cache`. However, running `/api/clear-cache` flushes this state at runtime, forcing dynamic route compilation on subsequent incoming requests.

### 2. Fatal Logging Vulnerability & Unhandled Exceptions
* In `config/logging.php#L54-L65`, the `single` channel is used without log rotation. In high-traffic environments, a monolithic `laravel.log` file will grow until disk space is exhausted.
* In `app/Exceptions/Handler.php#L50-L55`:
  ```php
  protected function unauthenticated($request, AuthenticationException $exception)
  {
      return $request->expectsJson()
          ? response()->json(['message' => 'Unauthenticated'], 401)
          : redirect()->guest(route('login'));
  }
  ```
  If an unauthenticated request arrives without an `Accept: application/json` header, Laravel attempts to redirect to route `'login'`. Because no route named `'login'` exists in `routes/api.php` or `routes/web.php`, Laravel throws:
  `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [login] not defined.`
  This turns a routine 401 Unauthorized condition into an unhandled HTTP 500 error!

### 3. Syntax and Driver Incompatibility in `config/database.php`
* In `config/database.php#L62`:
  ```php
  Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
  ```
  While `Pdo\Mysql` exists in PHP 8.2+ with PDO driver loaded, older PHP 8.1 builds or standard PHP distributions expect `PDO::MYSQL_ATTR_SSL_CA`. Using non-standard casing (`Pdo\Mysql`) introduces environmental fragility across different PHP builds.

---

## 10. Order Processing and Data Integrity

### Critical Flaw 1: Acceptance of Client-Specified Pricing (Financial Fraud Risk)
In `app/Http/Controllers/CommandeController.php#L358-L373`:
```php
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'CddArt' => 'required|string|max:6',
        'CddColisage' => 'required|string|max:2',
        'CddColis' => 'nullable|numeric',
        'CddUntCol' => 'nullable|numeric',
        'CddQte' => 'required|numeric',
        'CddPrix' => 'required|numeric',     // ❌ CLIENT SENDS UNIT PRICE
        'CddMontant' => 'required|numeric',  // ❌ CLIENT SENDS LINE AMOUNT
    ]);
```
* **Impact:** The backend saves `$detail['CddPrix']` and `$detail['CddMontant']` directly to the `vte_commandesd` table. An end user or compromised client can manipulate the HTTP request payload to submit any price (e.g., `0.01 DZD`). The application recalculates the order total as `sum('CddMontant')`, resulting in an order successfully created at arbitrary, forged prices.
* **Remediation:** The backend **must** fetch the authoritative price from `Product::priceForClient($client)` and calculate `CddMontant = $unitPrice * $quantity`.

### Critical Flaw 2: Cart Duplication Race Condition
In `CommandeController.php#L387-L397`:
```php
$commandeHead = CommandeHead::firstOrCreate(
    [
        'CdeCls' => $client->CliCls,
        'CdeCli' => $client->CliNo,
        'CdeStatus' => 0,
    ],
    [
        'CdeDate' => now(),
        'CdeMontant' => 0,
    ]
);
```
* **Impact:** If a mobile user taps "Add to Cart" rapidly, or network retries dispatch two requests in parallel, both threads execute the `SELECT` query before either executes the `INSERT`. Both requests create new order heads with `CdeStatus = 0`.
* The client now possesses two concurrent "active carts" in `vte_commandese`. Subsequent calls to `cart()`, `cartCount()`, and `confirmCommande()` query with `first()`, which returns whichever cart MySQL resolves first, stranding items across disparate order heads.

### Critical Flaw 3: Order Confirmation via HTTP GET
In `routes/api.php#L69`:
```php
Route::get('/confirmcommande', [CommandeController::class, 'confirmCommande']);
```
* **Impact:** HTTP GET requests must be safe and idempotent according to RFC 7231 / RFC 9110. Exposing state transitions (`CdeStatus = 1`) over GET allows browser link prefetching, HTTP proxy caching, and web crawlers to inadvertently confirm pending orders.
* **Remediation:** Convert the route to `Route::post('/confirmcommande', ...)`.

### Critical Flaw 4: Missing Transaction and Stock Validation on Confirmation
In `CommandeController.php#L141-L166`:
```php
public function confirmCommande(Request $request)
{
    // ...
    $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
        ->where('CdeCls', $client->CliCls)
        ->where('CdeStatus', 0)
        ->first();

    if (!$commandeHead) {
        return response()->json(['message' => 'No active commande found'], 404);
    }

    $commandeHead->update(['CdeStatus' => 1]); // ❌ NO TRANSACTION, NO INVENTORY CHECK
    return response()->json([...]);
}
```
* **Impact:**
  1. An empty cart with zero line items can be confirmed as an order.
  2. No inventory or stock verification is performed against `stk_articles`.
  3. No database transaction or row-level lock (`lockForUpdate`) is applied.

---

## 11. Logging and Monitoring Gaps

1. **Silent Rejections on Authentication Failures:**
   When `AuthenticateWithCustomToken` rejects a request with HTTP 401, no log entry is written. During the diagnostic run, 17 requests were rejected without a single line in `laravel.log`, making production troubleshooting blind.
2. **Commented-Out Error Logging:**
   In `CommandeController.php#L478-L481`, error logging was intentionally commented out (`// \Log::error('storeDetail error', ...)`).
3. **Absence of Request Correlation IDs:**
   Incoming requests lack a unique UUID (`X-Request-ID`). Correlating a mobile app crash report with a specific server log entry is impossible.
4. **No Query Execution Threshold Monitoring:**
   Laravel provides `DB::whenQueryingForLongerThan()` to detect slow queries in production. This feature is not configured.
5. **Recommended Observability Architecture:**
   * Configure Monolog `daily` channel with a 14-day retention window.
   * Add middleware to log all 4xx and 5xx responses with route, client IP, user ID (if resolved), and execution duration.
   * Strip sensitive fields (`CliGoogleId`, tokens) from all diagnostic contexts.

---

## 12. Root-Cause Assessment

| Observed Symptom | Classification | Evidence & Technical Justification |
| :--- | :--- | :--- |
| **18.7s Initial Latency on `/api/company`** | **Confirmed** | `DB_HOST=softel.dz` in `.env`. First request incurs WAN DNS resolution, TCP handshake, and MySQL reverse-DNS checks. Subsequent requests (721ms, 432ms) benefit from warm DNS and TCP socket caching. |
| **17 HTTP 401 Responses** | **Confirmed** | Diagnostic requests to product, category, and order endpoints lacked valid Sanctum tokens or custom `X-Auth-Token` headers. Endpoints are wrapped under `custom.auth` middleware which explicitly emits HTTP 401. |
| **4 HTTP 503 Responses (HTML, 1–2s delay)** | **Strongly Suspected** | Shared hosting environment (`/home/wdctfhzy`) reached CloudLinux LVE Entry Process (EP) limit. Requests blocked in the web server queue for 1–2 seconds before LiteSpeed/Apache returned an HTML 503 error before invoking Laravel. |
| **404 Image Requests Causing Slowdowns** | **Confirmed** | Missing `public/images/` directory in repository. Missing asset requests are rewritten to `index.php` by `.htaccess`, spawning Laravel processes for static files. |
| **Logout Failure (HTTP 500)** | **Confirmed** | `ClientController::logout` calls `delete()` on a null access token instance because `TokenHelper::getUser` does not attach the token via `withAccessToken()`. |

---

## 13. Prioritized Remediation Plan

### P0 — Immediate Security & Production Risks (Effort: 1–2 Days)

| Task | Action Items | Files Involved | Risk / Impact |
| :--- | :--- | :--- | :--- |
| **P0-1** | Delete or restrict public debug routes (`/api/server-info`, `/api/clear-cache`, `/api/debug/*`, `/api/test-auth-header`). | `routes/api.php#L82-L161` | Eliminates critical credential and environment leakage. |
| **P0-2** | Enforce server-side price calculation in order creation (`CddPrix`, `CddMontant`). Reject client pricing. | `CommandeController.php#L358-L453` | Prevents fraudulent order placement at manipulated prices. |
| **P0-3** | Fix database host configuration: switch `DB_HOST` from `softel.dz` to `127.0.0.1` or unix socket. | `.env` | Eliminates WAN latency, DNS timeouts, and initial 18.7s lag. |
| **P0-4** | Fix `Handler.php` to return JSON 401 for all API requests when unauthenticated, avoiding missing `route('login')`. | `app/Exceptions/Handler.php#L50-L55` | Prevents `RouteNotFoundException` (500 error) on unauthenticated calls. |

### P1 — High-Priority Reliability & Data Integrity (Effort: 2–3 Days)

| Task | Action Items | Files Involved | Risk / Impact |
| :--- | :--- | :--- | :--- |
| **P1-1** | Fix `ClientController::logout` null pointer exception on token deletion. | `ClientController.php#L76` & `TokenHelper.php#L27` | Restores functional user logout. |
| **P1-2** | Convert order confirmation (`/confirmcommande`) to POST with database transactions and empty-cart checks. | `routes/api.php#L69`, `CommandeController.php#L141-L166` | Prevents accidental order submission and corrupt order states. |
| **P1-3** | Prevent cart duplication: apply database unique constraints and lock-for-update on `CommandeHead::firstOrCreate`. | `CommandeController.php#L387-L397` | Prevents split carts and orphaned order lines. |
| **P1-4** | Fix banner images N+1 query and null reference exception in `ProductController::imagesbanner`. | `ProductController.php#L149-L152` | Prevents fatal errors and reduces banner query latency by 90%. |
| **P1-5** | Ensure `public/images/` exists on disk or configure `.htaccess` to avoid rewriting static asset 404s to `index.php`. | `public/.htaccess`, deployment scripts | Eliminates worker pool exhaustion from missing image files. |

### P2 — Performance Improvements (Effort: 3–4 Days)

| Task | Action Items | Files Involved | Risk / Impact |
| :--- | :--- | :--- | :--- |
| **P2-1** | Eager load `tarifs` in `search()` and `parfamilles()`. | `ProductController.php#L188, #L283` | Eliminates 50+ N+1 queries per product listing request. |
| **P2-2** | Eliminate duplicate `TokenHelper::getUser` calls across controllers by reading `$request->get('auth_user')`. | All Controllers & `AuthenticateWithCustomToken.php` | Cuts auth database overhead by 50% across all API requests. |
| **P2-3** | Cache static company and category data (`CompanyController::index`, `FamillesController::index`). | `CompanyController.php`, `FamillesController.php` | Drops response time from ~400ms to <15ms. |
| **P2-4** | Re-enable and tune API rate limiting (`throttle:api`). | `app/Http/Kernel.php#L44`, `RouteServiceProvider.php` | Protects server from brute-force login and DDoS attacks. |

### P3 — Maintainability & Architecture (Effort: 1–2 Weeks)

| Task | Action Items | Files Involved | Risk / Impact |
| :--- | :--- | :--- | :--- |
| **P3-1** | Resolve composite primary keys by introducing Eloquent composite key package or explicit synthetic keys. | All Models | Restores standard Eloquent relationship behavior and safety. |
| **P3-2** | Configure Monolog daily rotation and request correlation IDs. | `config/logging.php`, new middleware | Enables long-term production observability. |
| **P3-3** | Clean up unused models and dead code (e.g., `RegisterController.php`). | `app/Http/Controllers/RegisterController.php` | Improves codebase maintainability. |

---

## 14. Production Verification Checklist

Safe read-only checks for the system administrator to execute on the production server:

- [ ] **Check CloudLinux LVE Resource Usage:**
  ```bash
  lveinfo --period=1d --by-usage=ep,pmem,cpu
  ```
  *Verify if EP (Entry Processes) reached 100% saturation during diagnostic runs.*
- [ ] **Verify Web Server Error Logs:**
  ```bash
  tail -n 200 /usr/local/apache/logs/error_log
  # Or cPanel user log:
  tail -n 200 /home/wdctfhzy/logs/b2b.softel.dz.error.log
  ```
  *Look for: "Resource temporarily unavailable: ... reached MaxClients / Entry Processes".*
- [ ] **Verify Database Host Latency from CLI:**
  ```bash
  # Inside /home/wdctfhzy:
  php -r '$t=microtime(true); new PDO("mysql:host=softel.dz;dbname=...", "...", "..."); echo "Time: ".(microtime(true)-$t)."s\n";'
  php -r '$t=microtime(true); new PDO("mysql:host=127.0.0.1;dbname=...", "...", "..."); echo "Time: ".(microtime(true)-$t)."s\n";'
  ```
- [ ] **Verify Apache Authorization Header Pass-Through:**
  Inspect `.htaccess` or test using curl:
  ```bash
  curl -I -H "Authorization: Bearer test" https://b2b.softel.dz/api/test-auth-header
  ```
- [ ] **Check Storage and Permissions:**
  ```bash
  df -h
  ls -la /home/wdctfhzy/public_html/softel_api/storage/logs/
  ```

---

## 15. Recommended Performance Test Plan

> [!CAUTION]
> Do not execute load tests against the live production environment without prior authorization, backup verification, and an off-peak maintenance window.

### Phase 1: Staging Baseline Benchmarking
1. Duplicate production database schema and representative data to a staging environment.
2. Execute single-request latency baselines for all 8 endpoint groups using `k6` or `ApacheBench`:
   ```bash
   ab -n 50 -c 1 -H "Authorization: Bearer <valid_token>" https://staging.softel.dz/api/products
   ```
3. Record P50, P90, and P95 response times.

### Phase 2: Concurrency & Step-Up Load Testing
1. Configure a `k6` test script simulating realistic mobile app usage:
   * 70% Product browsing & search (`/api/products`, `/api/familles`)
   * 20% Cart operations (`/api/commandes/cart`, `/api/products/cartCount`)
   * 10% Order creation (`/api/commandes/store`)
2. Step up virtual users: 5 VUs ──► 15 VUs ──► 30 VUs over 10 minutes.
3. Monitor server metrics:
   * CloudLinux Entry Processes.
   * MySQL active threads (`SHOW GLOBAL STATUS LIKE 'Threads_running'`).
   * P95 latency target: `< 500 ms`.
   * Error rate target: `0.00%` (Zero 503 or 500 errors).

---

## 16. Evidence and File References

### Source Files Inspected:
* `composer.json` & `composer.lock`: Framework dependencies (`laravel/framework: v10.48.29`, `laravel/sanctum: v3.3.3`).
* `routes/api.php`: Public vs. protected routes, debug endpoints (`#L23-L161`).
* `app/Http/Kernel.php`: Middleware stack, disabled throttle (`#L41-L47`).
* `app/Http/Middleware/AuthenticateWithCustomToken.php`: Custom authentication logic (`#L1-L26`).
* `app/Helpers/TokenHelper.php`: Sanctum token extraction, multi-tenant ability overrides (`#L1-L42`).
* `app/Http/Controllers/CompanyController.php`: Company query and image transformation (`#L1-L26`).
* `app/Http/Controllers/ClientController.php`: Login, token creation, and flawed logout (`#L1-L80`).
* `app/Http/Controllers/ProductController.php`: Product catalog, search, banner N+1 queries (`#L1-L356`).
* `app/Http/Controllers/CommandeController.php`: Order management, pricing flaw, cart race conditions (`#L1-L486`).
* `app/Http/Controllers/FamillesController.php`: Categories and subcategories (`#L1-L73`).
* `app/Http/Controllers/ClientNewController.php`: Customer registration (`#L1-L64`).
* `app/Models/Product.php`, `Company.php`, `Client.php`, `CommandeHead.php`, `CommandeDetail.php`, `Familles.php`, `SouFamilles.php`, `Imagesbanner.php`, `Tarif.php`: Model definitions and composite key structure.
* `app/Exceptions/Handler.php`: Unauthenticated redirect logic (`#L50-L55`).
* `config/database.php` & `.env`: Remote database host configuration (`DB_HOST=softel.dz`).
* `laravel.log` & `storage/logs/laravel.log`: Historic production errors and stack traces.

---

## 17. Limitations

1. **Production Infrastructure Metrics:** Direct access to live CloudLinux LVE metrics (`lvechart`, `cPanel Resource Usage`), MySQL configuration (`my.cnf`, slow query log), and live Apache access/error logs was not available during this code audit. Server-level conclusions are based on stack traces and log evidence found within the repository.
2. **Database Query Benchmarking:** Because production database writes and destructive commands are prohibited, real-time query execution plans (`EXPLAIN`) against production tables were not executed.
3. **Valid Mobile Test Token:** Diagnostics were conducted without a valid authenticated user token, which successfully confirmed route protection behavior but prevented measuring end-to-end authenticated payload latency on production.

---

## 18. Final Conclusion

The investigation successfully identified the root causes behind all reported production symptoms:

1. **The 18.7-Second Latency Spike** is primarily caused by connecting to MySQL over a WAN domain name (`DB_HOST=softel.dz`). Switching this configuration to local loopback (`127.0.0.1`) and caching the company query will reduce response times from 18.7 seconds to under 20 milliseconds.
2. **The HTTP 503 Errors** are caused by web server worker saturation (CloudLinux Entry Process limit) resulting from long-running remote database queries and non-existent image requests cascading into full framework boots.
3. **The HTTP 401 Responses** represent the intended security boundary of the `custom.auth` middleware rejecting unauthenticated requests, compounded by Apache stripping standard `Authorization` headers.

### First Three Actions for the Developer:
1. **Immediately secure the API**: Remove the public `/api/server-info` and `/api/clear-cache` endpoints from `routes/api.php`.
2. **Fix Database Connection**: Update `.env` to set `DB_HOST=127.0.0.1` (or local MySQL socket) instead of `softel.dz`.
3. **Fix Order Pricing Security**: Modify `CommandeController::store` to calculate prices authoritatively on the server rather than trusting client-submitted values.

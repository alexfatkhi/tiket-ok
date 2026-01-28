# Penjelasan File routes/web.php

## Daftar Isi
- [Overview](#overview)
- [Import Controller](#import-controller)
- [Route Public](#route-public)
- [Route Authenticated User](#route-authenticated-user)
- [Route Admin](#route-admin)
- [Route Events & Orders](#route-events--orders)
- [Diagram Alur Routing](#diagram-alur-routing)

---

## Overview

File `routes/web.php` adalah file konfigurasi routing utama di Laravel yang mendefinisikan semua URL endpoint aplikasi web dan menghubungkannya ke controller yang sesuai.

```
┌─────────────────────────────────────────────────────────────────┐
│                    FUNGSI routes/web.php                        │
├─────────────────────────────────────────────────────────────────┤
│  1. Mendefinisikan URL endpoint aplikasi                        │
│  2. Menghubungkan URL ke Controller method                      │
│  3. Menerapkan Middleware (auth, admin, dll)                    │
│  4. Mengelompokkan route dengan prefix dan name                 │
│  5. Memberikan nama route untuk kemudahan referensi             │
└─────────────────────────────────────────────────────────────────┘
```

---

## Import Controller

```php
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\TiketController;
use App\Http\Controllers\Admin\HistoriesController;
use App\Http\Controllers\User\HomeController;
use App\Http\Controllers\User\EventController as UserEventController;
use App\Http\Controllers\User\OrderController;
```

### Penjelasan Import

| Import | Penjelasan |
|--------|------------|
| `Route` | Facade Laravel untuk mendefinisikan routing |
| `ProfileController` | Controller untuk manajemen profil user |
| `DashboardController` | Controller dashboard admin |
| `CategoryController` | Controller CRUD kategori (admin) |
| `AdminEventController` | Controller CRUD event (admin) - menggunakan alias karena ada 2 EventController |
| `TiketController` | Controller CRUD tiket (admin) |
| `HistoriesController` | Controller riwayat transaksi (admin) |
| `HomeController` | Controller halaman utama (user) |
| `UserEventController` | Controller detail event (user) - menggunakan alias |
| `OrderController` | Controller pemesanan tiket (user) |

### Penggunaan Alias

```php
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\User\EventController as UserEventController;
```

**Alasan:** Ada 2 class dengan nama sama (`EventController`) di namespace berbeda. Alias mencegah konflik nama.

---

## Route Public

### Halaman Utama (Home)

```php
Route::get('/', [HomeController::class, 'index'])->name('home');
```

| Komponen | Nilai | Penjelasan |
|----------|-------|------------|
| Method | `GET` | HTTP method untuk mengambil halaman |
| URI | `/` | URL root/homepage |
| Controller | `HomeController::class` | Class controller yang menangani |
| Method | `'index'` | Method di controller yang dipanggil |
| Name | `'home'` | Nama route untuk referensi (`route('home')`) |

**Alur Request:**

```
Browser                    Laravel                      Controller
   │                          │                            │
   │  GET /                   │                            │
   │─────────────────────────►│                            │
   │                          │  HomeController@index      │
   │                          │───────────────────────────►│
   │                          │                            │
   │                          │◄───────────────────────────│
   │                          │  return view('home')       │
   │◄─────────────────────────│                            │
   │  HTML Response           │                            │
```

### Dashboard (Auth Required)

```php
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
```

| Komponen | Nilai | Penjelasan |
|----------|-------|------------|
| Method | `GET` | HTTP method |
| URI | `/dashboard` | URL dashboard |
| Handler | Closure/Anonymous function | Langsung return view tanpa controller |
| Middleware | `['auth', 'verified']` | User harus login DAN email terverifikasi |
| Name | `'dashboard'` | Nama route |

**Alur Middleware:**

```
Request GET /dashboard
        │
        ▼
┌───────────────────┐     Tidak      ┌─────────────────┐
│ Middleware: auth  │───────────────►│ Redirect /login │
│ User login?       │                └─────────────────┘
└─────────┬─────────┘
          │ Ya
          ▼
┌───────────────────┐     Tidak      ┌─────────────────────┐
│Middleware:verified│───────────────►│ Redirect /verify-   │
│ Email verified?   │                │ email               │
└─────────┬─────────┘                └─────────────────────┘
          │ Ya
          ▼
┌───────────────────┐
│ return view(      │
│   'dashboard'     │
│ )                 │
└───────────────────┘
```

---

## Route Authenticated User

### Profile Management

```php
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
```

### Penjelasan Struktur

**`Route::middleware('auth')->group()`** - Mengelompokkan route dengan middleware yang sama.

```
┌─────────────────────────────────────────────────────────────────┐
│                    ROUTE GROUP: auth                            │
├─────────────────────────────────────────────────────────────────┤
│  Semua route di dalam group ini memerlukan user LOGIN           │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ GET    /profile  → ProfileController@edit               │   │
│  │                    Menampilkan form edit profil         │   │
│  ├─────────────────────────────────────────────────────────┤   │
│  │ PATCH  /profile  → ProfileController@update             │   │
│  │                    Menyimpan perubahan profil           │   │
│  ├─────────────────────────────────────────────────────────┤   │
│  │ DELETE /profile  → ProfileController@destroy            │   │
│  │                    Menghapus akun user                  │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### Tabel Route Profile

| Method | URI | Controller@Method | Name | Fungsi |
|--------|-----|-------------------|------|--------|
| GET | `/profile` | `ProfileController@edit` | `profile.edit` | Form edit profil |
| PATCH | `/profile` | `ProfileController@update` | `profile.update` | Update profil |
| DELETE | `/profile` | `ProfileController@destroy` | `profile.destroy` | Hapus akun |

---

## Route Admin

### Konfigurasi Route Group Admin

```php
Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
    // ... routes di dalam group
});
```

### Penjelasan Komponen Group

| Komponen | Nilai | Penjelasan |
|----------|-------|------------|
| `middleware('admin')` | `admin` | Middleware custom untuk cek role admin |
| `prefix('admin')` | `admin` | Semua URI diawali `/admin` |
| `name('admin.')` | `admin.` | Semua nama route diawali `admin.` |

**Contoh Pengaruh Group:**

```
Tanpa Group                          Dengan Group
─────────────────────────────────────────────────────
URI: /categories                     URI: /admin/categories
Name: categories.index               Name: admin.categories.index
Middleware: (none)                   Middleware: admin
```

### Route di Dalam Group Admin

#### 1. Dashboard Admin

```php
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
```

| Komponen | Nilai Asli | Nilai Setelah Group |
|----------|------------|---------------------|
| URI | `/` | `/admin` |
| Name | `dashboard` | `admin.dashboard` |

#### 2. Category Management (Resource)

```php
Route::resource('categories', CategoryController::class);
```

**`Route::resource()`** otomatis membuat 7 route CRUD:

| Method | URI | Controller@Method | Name | Fungsi |
|--------|-----|-------------------|------|--------|
| GET | `/admin/categories` | `@index` | `admin.categories.index` | List semua kategori |
| GET | `/admin/categories/create` | `@create` | `admin.categories.create` | Form tambah |
| POST | `/admin/categories` | `@store` | `admin.categories.store` | Simpan baru |
| GET | `/admin/categories/{id}` | `@show` | `admin.categories.show` | Detail kategori |
| GET | `/admin/categories/{id}/edit` | `@edit` | `admin.categories.edit` | Form edit |
| PUT/PATCH | `/admin/categories/{id}` | `@update` | `admin.categories.update` | Update data |
| DELETE | `/admin/categories/{id}` | `@destroy` | `admin.categories.destroy` | Hapus data |

#### 3. Event Management (Resource)

```php
Route::resource('events', AdminEventController::class);
```

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | `/admin/events` | `@index` | `admin.events.index` |
| GET | `/admin/events/create` | `@create` | `admin.events.create` |
| POST | `/admin/events` | `@store` | `admin.events.store` |
| GET | `/admin/events/{id}` | `@show` | `admin.events.show` |
| GET | `/admin/events/{id}/edit` | `@edit` | `admin.events.edit` |
| PUT/PATCH | `/admin/events/{id}` | `@update` | `admin.events.update` |
| DELETE | `/admin/events/{id}` | `@destroy` | `admin.events.destroy` |

#### 4. Tiket Management (Resource)

```php
Route::resource('tickets', TiketController::class);
```

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | `/admin/tickets` | `@index` | `admin.tickets.index` |
| POST | `/admin/tickets` | `@store` | `admin.tickets.store` |
| PUT/PATCH | `/admin/tickets/{id}` | `@update` | `admin.tickets.update` |
| DELETE | `/admin/tickets/{id}` | `@destroy` | `admin.tickets.destroy` |

#### 5. Histories (Manual Route)

```php
Route::get('/histories', [HistoriesController::class, 'index'])->name('histories.index');
Route::get('/histories/{id}', [HistoriesController::class, 'show'])->name('histories.show');
```

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | `/admin/histories` | `@index` | `admin.histories.index` |
| GET | `/admin/histories/{id}` | `@show` | `admin.histories.show` |

### Diagram Route Admin

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         ROUTE GROUP: ADMIN                                   │
│                                                                             │
│  Middleware: admin (cek role === 'admin')                                   │
│  Prefix: /admin                                                             │
│  Name Prefix: admin.                                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ GET /admin → DashboardController@index                                │ │
│  │ Name: admin.dashboard                                                 │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ RESOURCE /admin/categories → CategoryController                       │ │
│  │ 7 routes: index, create, store, show, edit, update, destroy           │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ RESOURCE /admin/events → AdminEventController                         │ │
│  │ 7 routes: index, create, store, show, edit, update, destroy           │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ RESOURCE /admin/tickets → TiketController                             │ │
│  │ 7 routes: index, create, store, show, edit, update, destroy           │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ GET /admin/histories → HistoriesController@index                      │ │
│  │ GET /admin/histories/{id} → HistoriesController@show                  │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Route Events & Orders

### Event Detail (Public)

```php
Route::get('/events/{event}', [UserEventController::class, 'show'])->name('events.show');
```

| Komponen | Nilai | Penjelasan |
|----------|-------|------------|
| Method | `GET` | Mengambil data |
| URI | `/events/{event}` | `{event}` adalah parameter dinamis (ID event) |
| Controller | `UserEventController@show` | Method show di controller |
| Name | `events.show` | Nama route |

**Route Model Binding:**

```php
// Di Controller
public function show(Event $event)  // Laravel otomatis inject model Event
{
    return view('events.show', compact('event'));
}
```

Laravel otomatis:
1. Mengambil nilai `{event}` dari URL (misal: `/events/5`)
2. Query `Event::findOrFail(5)`
3. Inject hasil query ke parameter `$event`

### Order Routes

```php
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
```

| Method | URI | Controller@Method | Name | Fungsi |
|--------|-----|-------------------|------|--------|
| GET | `/orders` | `@index` | `orders.index` | List riwayat pembelian user |
| GET | `/orders/{order}` | `@show` | `orders.show` | Detail pesanan |
| POST | `/orders` | `@store` | `orders.store` | Buat pesanan baru (AJAX) |

### Alur Route Pembelian Tiket

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      ALUR PEMBELIAN TIKET                                    │
└─────────────────────────────────────────────────────────────────────────────┘

[1] User akses halaman event
    GET /events/{event}
         │
         ▼
    ┌─────────────────────────┐
    │ UserEventController     │
    │ @show                   │
    │                         │
    │ - Load event + tikets   │
    │ - Return view           │
    └─────────────────────────┘
         │
         ▼
[2] User pilih tiket & checkout
    POST /orders (AJAX)
    Body: { event_id, items: [...] }
         │
         ▼
    ┌─────────────────────────┐
    │ OrderController         │
    │ @store                  │
    │                         │
    │ - Validasi input        │
    │ - Cek stok              │
    │ - Create order          │
    │ - Return JSON           │
    └─────────────────────────┘
         │
         ▼
[3] Redirect ke riwayat
    GET /orders
         │
         ▼
    ┌─────────────────────────┐
    │ OrderController         │
    │ @index                  │
    │                         │
    │ - Load orders user      │
    │ - Return view           │
    └─────────────────────────┘
         │
         ▼
[4] User lihat detail order
    GET /orders/{order}
         │
         ▼
    ┌─────────────────────────┐
    │ OrderController         │
    │ @show                   │
    │                         │
    │ - Load order + details  │
    │ - Return view           │
    └─────────────────────────┘
```

---

## Include File Auth

```php
require __DIR__.'/auth.php';
```

**Penjelasan:**
- `__DIR__` = direktori saat ini (`routes/`)
- Meng-include file `routes/auth.php`
- File `auth.php` berisi route autentikasi (login, register, logout, dll)
- Dipisah untuk organisasi kode yang lebih baik

**Route di auth.php:**

```
GET  /register           → RegisteredUserController@create
POST /register           → RegisteredUserController@store
GET  /login              → AuthenticatedSessionController@create
POST /login              → AuthenticatedSessionController@store
POST /logout             → AuthenticatedSessionController@destroy
GET  /forgot-password    → PasswordResetLinkController@create
POST /forgot-password    → PasswordResetLinkController@store
GET  /reset-password     → NewPasswordController@create
POST /reset-password     → NewPasswordController@store
GET  /verify-email       → EmailVerificationPromptController
POST /email/verification → EmailVerificationNotificationController
```

---

## Diagram Alur Routing

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         DIAGRAM ALUR ROUTING                                 │
└─────────────────────────────────────────────────────────────────────────────┘

                              HTTP Request
                                   │
                                   ▼
                    ┌──────────────────────────┐
                    │     routes/web.php       │
                    └──────────────┬───────────┘
                                   │
        ┌──────────────────────────┼──────────────────────────┐
        │                          │                          │
        ▼                          ▼                          ▼
┌───────────────┐        ┌───────────────┐        ┌───────────────┐
│ PUBLIC ROUTES │        │  AUTH ROUTES  │        │ ADMIN ROUTES  │
├───────────────┤        ├───────────────┤        ├───────────────┤
│               │        │ middleware:   │        │ middleware:   │
│ GET /         │        │   auth        │        │   admin       │
│ GET /events/* │        │               │        │ prefix:       │
│               │        │ GET /profile  │        │   /admin      │
│               │        │ PATCH /profile│        │               │
│               │        │ DELETE /profile│       │ /admin/       │
│               │        │               │        │ /admin/events │
│               │        │ GET /orders   │        │ /admin/       │
│               │        │ GET /orders/* │        │   categories  │
│               │        │ POST /orders  │        │ /admin/tickets│
│               │        │               │        │ /admin/       │
│               │        │               │        │   histories   │
└───────┬───────┘        └───────┬───────┘        └───────┬───────┘
        │                        │                        │
        ▼                        ▼                        ▼
┌───────────────┐        ┌───────────────┐        ┌───────────────┐
│ User          │        │ User          │        │ Admin         │
│ Controllers   │        │ Controllers   │        │ Controllers   │
├───────────────┤        ├───────────────┤        ├───────────────┤
│ HomeController│        │ProfileControll│        │DashboardContro│
│ UserEvent     │        │ OrderControlle│        │CategoryControl│
│  Controller   │        │               │        │AdminEventContr│
│               │        │               │        │TiketController│
│               │        │               │        │HistoriesContr │
└───────────────┘        └───────────────┘        └───────────────┘
```

---

## Ringkasan Semua Route

| Method | URI | Controller | Middleware | Name |
|--------|-----|------------|------------|------|
| GET | `/` | HomeController@index | - | home |
| GET | `/dashboard` | Closure | auth, verified | dashboard |
| GET | `/profile` | ProfileController@edit | auth | profile.edit |
| PATCH | `/profile` | ProfileController@update | auth | profile.update |
| DELETE | `/profile` | ProfileController@destroy | auth | profile.destroy |
| GET | `/admin` | DashboardController@index | admin | admin.dashboard |
| GET | `/admin/categories` | CategoryController@index | admin | admin.categories.index |
| POST | `/admin/categories` | CategoryController@store | admin | admin.categories.store |
| GET | `/admin/categories/{id}` | CategoryController@show | admin | admin.categories.show |
| PUT | `/admin/categories/{id}` | CategoryController@update | admin | admin.categories.update |
| DELETE | `/admin/categories/{id}` | CategoryController@destroy | admin | admin.categories.destroy |
| GET | `/admin/events` | AdminEventController@index | admin | admin.events.index |
| GET | `/admin/events/create` | AdminEventController@create | admin | admin.events.create |
| POST | `/admin/events` | AdminEventController@store | admin | admin.events.store |
| GET | `/admin/events/{id}` | AdminEventController@show | admin | admin.events.show |
| GET | `/admin/events/{id}/edit` | AdminEventController@edit | admin | admin.events.edit |
| PUT | `/admin/events/{id}` | AdminEventController@update | admin | admin.events.update |
| DELETE | `/admin/events/{id}` | AdminEventController@destroy | admin | admin.events.destroy |
| POST | `/admin/tickets` | TiketController@store | admin | admin.tickets.store |
| PUT | `/admin/tickets/{id}` | TiketController@update | admin | admin.tickets.update |
| DELETE | `/admin/tickets/{id}` | TiketController@destroy | admin | admin.tickets.destroy |
| GET | `/admin/histories` | HistoriesController@index | admin | admin.histories.index |
| GET | `/admin/histories/{id}` | HistoriesController@show | admin | admin.histories.show |
| GET | `/events/{event}` | UserEventController@show | - | events.show |
| GET | `/orders` | OrderController@index | - | orders.index |
| GET | `/orders/{order}` | OrderController@show | - | orders.show |
| POST | `/orders` | OrderController@store | - | orders.store |

---

*Dokumentasi penjelasan file routes/web.php untuk aplikasi BengTix*

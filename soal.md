# Penjelasan Alur Logika MVC - Sistem Pembelian Tiket

## Daftar Isi
- [1. Route: Menangani Request Pembelian](#1-route-menangani-request-pembelian)
- [2. Controller: Validasi Kuota dan Penyimpanan Data](#2-controller-validasi-kuota-dan-penyimpanan-data)
- [3. Model: Interaksi Eloquent](#3-model-interaksi-eloquent)
- [4. Reasoning: Alasan Penggunaan Pola Kode](#4-reasoning-alasan-penggunaan-pola-kode)

---

## 1. Route: Menangani Request Pembelian

### Definisi Route

File: `routes/web.php`

```php
// Orders
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
```

### Penjelasan Alur Route

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        ALUR REQUEST PEMBELIAN                                │
└─────────────────────────────────────────────────────────────────────────────┘

[Client Browser]
       │
       │  POST /orders
       │  Content-Type: application/json
       │  X-CSRF-TOKEN: xxx
       │  Body: {
       │    "event_id": 1,
       │    "items": [
       │      {"tiket_id": 1, "jumlah": 2},
       │      {"tiket_id": 2, "jumlah": 1}
       │    ]
       │  }
       │
       ▼
┌─────────────────┐
│  Laravel Router │
│  routes/web.php │
└────────┬────────┘
         │
         │  Route::post('/orders', [OrderController::class, 'store'])
         │
         ▼
┌─────────────────────────────────────┐
│         MIDDLEWARE STACK            │
│  1. VerifyCsrfToken                 │  ──► Validasi CSRF Token
│  2. StartSession                    │  ──► Mulai session
│  3. AuthenticateSession             │  ──► Cek user login
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│    OrderController@store            │
│    app/Http/Controllers/User/       │
│    OrderController.php              │
└─────────────────────────────────────┘
```

### Cara Route Bekerja

| Komponen | Penjelasan |
|----------|------------|
| **Method POST** | Digunakan karena operasi pembelian mengubah state server (membuat data baru) |
| **URI `/orders`** | Mengikuti konvensi RESTful untuk resource "orders" |
| **Controller** | `OrderController::class` mengarahkan ke controller yang menangani logika |
| **Method `store`** | Konvensi Laravel untuk method yang menyimpan data baru |
| **Name `orders.store`** | Memudahkan generate URL dengan `route('orders.store')` |

### Request Flow Detail

```
1. User klik "Checkout" di halaman event
           │
           ▼
2. JavaScript mengirim AJAX POST request
   fetch('/orders', {
       method: 'POST',
       headers: {
           'Content-Type': 'application/json',
           'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
       },
       body: JSON.stringify(data)
   })
           │
           ▼
3. Laravel Router mencocokkan URL pattern
   Route::post('/orders', ...) ✓ MATCH
           │
           ▼
4. Middleware dijalankan secara berurutan
           │
           ▼
5. Controller method dipanggil
   OrderController@store($request)
```

---

## 2. Controller: Validasi Kuota dan Penyimpanan Data

### Kode Controller Lengkap

File: `app/Http/Controllers/User/OrderController.php`

```php
public function store(Request $request)
{
    // TAHAP 1: VALIDASI INPUT
    $data = $request->validate([
        'event_id' => 'required|exists:events,id',
        'items' => 'required|array|min:1',
        'items.*.tiket_id' => 'required|integer|exists:tikets,id',
        'items.*.jumlah' => 'required|integer|min:1',
    ]);

    $user = Auth::user();

    try {
        // TAHAP 2: DATABASE TRANSACTION
        $order = DB::transaction(function () use ($data, $user) {
            $total = 0;

            // TAHAP 3: VALIDASI STOK DENGAN PESSIMISTIC LOCKING
            foreach ($data['items'] as $it) {
                $t = Tiket::lockForUpdate()->findOrFail($it['tiket_id']);
                if ($t->stok < $it['jumlah']) {
                    throw new \Exception("Stok tidak cukup untuk tipe: {$t->tipe}");
                }
                $total += ($t->harga ?? 0) * $it['jumlah'];
            }

            // TAHAP 4: BUAT ORDER
            $order = Order::create([
                'user_id' => $user->id,
                'event_id' => $data['event_id'],
                'order_date' => Carbon::now(),
                'total_harga' => $total,
            ]);

            // TAHAP 5: BUAT DETAIL ORDER & KURANGI STOK
            foreach ($data['items'] as $it) {
                $t = Tiket::findOrFail($it['tiket_id']);
                $subtotal = ($t->harga ?? 0) * $it['jumlah'];
                DetailOrder::create([
                    'order_id' => $order->id,
                    'tiket_id' => $t->id,
                    'jumlah' => $it['jumlah'],
                    'subtotal_harga' => $subtotal,
                ]);

                // Kurangi stok
                $t->stok = max(0, $t->stok - $it['jumlah']);
                $t->save();
            }

            return $order;
        });

        // TAHAP 6: RETURN RESPONSE
        session()->flash('success', 'Pesanan berhasil dibuat.');
        return response()->json(['ok' => true, 'order_id' => $order->id, 'redirect' => route('orders.index')]);
    } catch (\Exception $e) {
        return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
    }
}
```

### Penjelasan Setiap Tahap

#### TAHAP 1: Validasi Input

```php
$data = $request->validate([
    'event_id' => 'required|exists:events,id',
    'items' => 'required|array|min:1',
    'items.*.tiket_id' => 'required|integer|exists:tikets,id',
    'items.*.jumlah' => 'required|integer|min:1',
]);
```

| Rule | Penjelasan |
|------|------------|
| `required` | Field wajib diisi |
| `exists:events,id` | Event ID harus ada di tabel `events` |
| `array\|min:1` | Items harus array dengan minimal 1 item |
| `items.*.tiket_id` | Validasi nested array untuk setiap tiket_id |
| `exists:tikets,id` | Tiket ID harus ada di tabel `tikets` |
| `integer\|min:1` | Jumlah harus integer minimal 1 |

**Diagram Validasi:**

```
Input Request
     │
     ▼
┌────────────────────────────────────┐
│         VALIDASI INPUT             │
├────────────────────────────────────┤
│                                    │
│  event_id: 1                       │
│  ├── required ✓                    │
│  └── exists:events,id ✓            │
│                                    │
│  items: [...]                      │
│  ├── required ✓                    │
│  ├── array ✓                       │
│  └── min:1 ✓                       │
│                                    │
│  items.0.tiket_id: 1               │
│  ├── required ✓                    │
│  ├── integer ✓                     │
│  └── exists:tikets,id ✓            │
│                                    │
│  items.0.jumlah: 2                 │
│  ├── required ✓                    │
│  ├── integer ✓                     │
│  └── min:1 ✓                       │
│                                    │
└────────────────────────────────────┘
     │
     ▼
Validasi PASSED ──► Lanjut ke proses berikutnya
     │
     ▼
Validasi FAILED ──► Return 422 dengan error messages
```

#### TAHAP 2: Database Transaction

```php
$order = DB::transaction(function () use ($data, $user) {
    // ... semua operasi database di sini
});
```

**Mengapa Transaction Penting?**

```
TANPA TRANSACTION (BERBAHAYA):
──────────────────────────────
1. Create Order      ✓ SUCCESS
2. Create DetailOrder ✓ SUCCESS
3. Kurangi Stok      ✗ ERROR (misal: koneksi putus)

Hasil: Order & DetailOrder tersimpan, tapi stok tidak berkurang!
       Data menjadi INKONSISTEN!


DENGAN TRANSACTION (AMAN):
──────────────────────────
1. BEGIN TRANSACTION
2. Create Order      ✓
3. Create DetailOrder ✓
4. Kurangi Stok      ✗ ERROR
5. ROLLBACK          ◄── Semua perubahan dibatalkan!

Hasil: Tidak ada data yang tersimpan.
       Data tetap KONSISTEN!
```

#### TAHAP 3: Validasi Stok dengan Pessimistic Locking

```php
$t = Tiket::lockForUpdate()->findOrFail($it['tiket_id']);
if ($t->stok < $it['jumlah']) {
    throw new \Exception("Stok tidak cukup untuk tipe: {$t->tipe}");
}
```

**Apa itu `lockForUpdate()`?**

```
SKENARIO: 2 User membeli tiket yang sama secara bersamaan
          Stok tersedia: 1 tiket

TANPA LOCK (Race Condition):
────────────────────────────
User A                          User B
  │                               │
  ├── Baca stok: 1               ├── Baca stok: 1
  │                               │
  ├── Stok >= 1? ✓               ├── Stok >= 1? ✓
  │                               │
  ├── Kurangi stok: 1-1=0        ├── Kurangi stok: 1-1=0
  │                               │
  └── Simpan: stok=0             └── Simpan: stok=0

Hasil: 2 tiket terjual padahal stok cuma 1! (OVERSELLING)


DENGAN lockForUpdate() (AMAN):
──────────────────────────────
User A                          User B
  │                               │
  ├── LOCK & Baca stok: 1        ├── MENUNGGU... (blocked)
  │                               │
  ├── Stok >= 1? ✓                │
  │                               │
  ├── Kurangi stok: 0             │
  │                               │
  ├── Simpan & RELEASE LOCK      ├── LOCK & Baca stok: 0
  │                               │
  │                               ├── Stok >= 1? ✗ ERROR!
  │                               │
  └── Order berhasil             └── "Stok tidak cukup"

Hasil: Hanya 1 tiket terjual. Data KONSISTEN!
```

#### TAHAP 4 & 5: Buat Order dan Detail Order

```php
// Buat Order (header)
$order = Order::create([
    'user_id' => $user->id,
    'event_id' => $data['event_id'],
    'order_date' => Carbon::now(),
    'total_harga' => $total,
]);

// Buat Detail Order (items) & kurangi stok
foreach ($data['items'] as $it) {
    $t = Tiket::findOrFail($it['tiket_id']);
    $subtotal = ($t->harga ?? 0) * $it['jumlah'];

    DetailOrder::create([
        'order_id' => $order->id,
        'tiket_id' => $t->id,
        'jumlah' => $it['jumlah'],
        'subtotal_harga' => $subtotal,
    ]);

    $t->stok = max(0, $t->stok - $it['jumlah']);
    $t->save();
}
```

**Diagram Penyimpanan Data:**

```
┌─────────────────────────────────────────────────────────────┐
│                    PROSES PENYIMPANAN                        │
└─────────────────────────────────────────────────────────────┘

Input:
  event_id: 1
  items: [
    {tiket_id: 1, jumlah: 2},  // VIP @ Rp 500.000
    {tiket_id: 2, jumlah: 3}   // Regular @ Rp 200.000
  ]

                    │
                    ▼
┌─────────────────────────────────────┐
│           HITUNG TOTAL              │
│                                     │
│  VIP:     2 × 500.000 = 1.000.000   │
│  Regular: 3 × 200.000 =   600.000   │
│  ─────────────────────────────────  │
│  TOTAL:               = 1.600.000   │
└─────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────┐
│         INSERT: orders              │
├─────────────────────────────────────┤
│  id: 1                              │
│  user_id: 5                         │
│  event_id: 1                        │
│  order_date: 2026-01-16 10:00:00    │
│  total_harga: 1600000               │
└─────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────┐
│      INSERT: detail_orders          │
├─────────────────────────────────────┤
│  Record 1:                          │
│    order_id: 1                      │
│    tiket_id: 1 (VIP)                │
│    jumlah: 2                        │
│    subtotal_harga: 1000000          │
│                                     │
│  Record 2:                          │
│    order_id: 1                      │
│    tiket_id: 2 (Regular)            │
│    jumlah: 3                        │
│    subtotal_harga: 600000           │
└─────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────┐
│        UPDATE: tikets (stok)        │
├─────────────────────────────────────┤
│  Tiket VIP:                         │
│    stok: 100 - 2 = 98               │
│                                     │
│  Tiket Regular:                     │
│    stok: 200 - 3 = 197              │
└─────────────────────────────────────┘
```

#### TAHAP 6: Return Response

```php
// Success
return response()->json([
    'ok' => true,
    'order_id' => $order->id,
    'redirect' => route('orders.index')
]);

// Error
return response()->json([
    'ok' => false,
    'message' => $e->getMessage()
], 422);
```

---

## 3. Model: Interaksi Eloquent

### Relasi Event - Kategori (Many-to-One)

```
┌─────────────────┐         ┌─────────────────┐
│     Event       │         │    Kategori     │
├─────────────────┤         ├─────────────────┤
│ id              │         │ id              │
│ judul           │   M:1   │ nama            │
│ kategori_id ────┼────────►│                 │
│ ...             │         │                 │
└─────────────────┘         └─────────────────┘
```

**Kode Model:**

```php
// app/Models/Event.php
class Event extends Model
{
    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }
}

// app/Models/Kategori.php
class Kategori extends Model
{
    protected $fillable = ['nama'];
}
```

**Contoh Penggunaan:**

```php
// Ambil kategori dari event
$event = Event::find(1);
$namaKategori = $event->kategori->nama;  // "Konser"

// Ambil semua event dengan kategori (Eager Loading)
$events = Event::with('kategori')->get();
foreach ($events as $event) {
    echo $event->judul . ' - ' . $event->kategori->nama;
}
```

### Relasi Event - Tiket (One-to-Many)

```
┌─────────────────┐         ┌─────────────────┐
│     Event       │         │     Tiket       │
├─────────────────┤         ├─────────────────┤
│ id              │◄────────┼ event_id        │
│ judul           │   1:M   │ tipe            │
│ ...             │         │ harga           │
│                 │         │ stok            │
└─────────────────┘         └─────────────────┘
```

**Kode Model:**

```php
// app/Models/Event.php
class Event extends Model
{
    public function tikets()
    {
        return $this->hasMany(Tiket::class);
    }
}

// app/Models/Tiket.php
class Tiket extends Model
{
    protected $fillable = ['event_id', 'tipe', 'harga', 'stok'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
```

**Contoh Penggunaan:**

```php
// Ambil semua tiket dari event
$event = Event::find(1);
foreach ($event->tikets as $tiket) {
    echo $tiket->tipe . ': Rp ' . $tiket->harga;
}

// Ambil event dari tiket
$tiket = Tiket::find(1);
echo $tiket->event->judul;  // "Konser Rock"

// Ambil harga tiket termurah per event
$events = Event::withMin('tikets', 'harga')->get();
foreach ($events as $event) {
    echo $event->judul . ' - Mulai dari Rp ' . $event->tikets_min_harga;
}
```

### Relasi Order - DetailOrder - Tiket

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│     Order       │       │   DetailOrder   │       │     Tiket       │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id              │◄──────┼ order_id        │       │ id              │
│ user_id         │  1:M  │ tiket_id ───────┼──────►│ tipe            │
│ event_id        │       │ jumlah          │  M:1  │ harga           │
│ total_harga     │       │ subtotal_harga  │       │ stok            │
└─────────────────┘       └─────────────────┘       └─────────────────┘
```

**Kode Model:**

```php
// app/Models/Order.php
class Order extends Model
{
    public function detailOrders()
    {
        return $this->hasMany(DetailOrder::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// app/Models/DetailOrder.php
class DetailOrder extends Model
{
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function tiket()
    {
        return $this->belongsTo(Tiket::class);
    }
}
```

**Contoh Penggunaan dengan Eager Loading:**

```php
// Ambil order dengan semua relasi (untuk halaman detail order)
$order = Order::with(['detailOrders.tiket', 'event'])->find(1);

echo "Event: " . $order->event->judul;
echo "Total: Rp " . $order->total_harga;

foreach ($order->detailOrders as $detail) {
    echo $detail->tiket->tipe . ' x ' . $detail->jumlah;
    echo ' = Rp ' . $detail->subtotal_harga;
}
```

### Diagram Lengkap Relasi Model

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         DIAGRAM RELASI MODEL                                 │
└─────────────────────────────────────────────────────────────────────────────┘

                              ┌─────────────┐
                              │   User      │
                              ├─────────────┤
                              │ id          │
                              │ name        │
                              │ email       │
                              │ role        │
                              └──────┬──────┘
                                     │
                    ┌────────────────┼────────────────┐
                    │ 1:M            │ 1:M            │
                    ▼                ▼                │
             ┌─────────────┐  ┌─────────────┐        │
             │   Event     │  │   Order     │        │
             ├─────────────┤  ├─────────────┤        │
             │ id          │  │ id          │        │
             │ user_id ────┼──│ user_id ────┼────────┘
             │ judul       │◄─┼─event_id    │
             │ kategori_id │  │ total_harga │
             │ ...         │  └──────┬──────┘
             └──────┬──────┘         │
                    │                │ 1:M
        ┌───────────┼───────────┐    │
        │           │           │    ▼
        │ M:1       │ 1:M       │  ┌─────────────┐
        ▼           ▼           │  │ DetailOrder │
 ┌─────────────┐ ┌─────────────┐│  ├─────────────┤
 │  Kategori   │ │   Tiket     ││  │ id          │
 ├─────────────┤ ├─────────────┤│  │ order_id    │
 │ id          │ │ id          │└─►│ tiket_id    │
 │ nama        │ │ event_id    │   │ jumlah      │
 └─────────────┘ │ tipe        │◄──│ subtotal_   │
                 │ harga       │M:1│   harga     │
                 │ stok        │   └─────────────┘
                 └─────────────┘
```

---

## 4. Reasoning: Alasan Penggunaan Pola Kode

### 4.1 Database Transaction

**Kode:**
```php
$order = DB::transaction(function () use ($data, $user) {
    // operasi database
});
```

**Alasan Penggunaan:**

| Aspek | Penjelasan |
|-------|------------|
| **Atomicity** | Semua operasi berhasil atau semua gagal. Tidak ada kondisi setengah jadi. |
| **Consistency** | Data tetap konsisten. Stok tiket selalu sesuai dengan jumlah order yang berhasil. |
| **Isolation** | Transaksi terisolasi dari transaksi lain yang berjalan bersamaan. |
| **Durability** | Setelah commit, data dijamin tersimpan permanen. |

**Skenario Tanpa Transaction:**

```
MASALAH: Koneksi putus di tengah proses

1. Order::create()        ✓ Tersimpan
2. DetailOrder::create()  ✓ Tersimpan
3. Kurangi stok          ✗ GAGAL (koneksi putus)

Akibat:
- Order tercatat, uang masuk
- Stok tidak berkurang
- Tiket bisa dijual lagi ke orang lain (OVERSELLING)
- Kerugian finansial!
```

**Dengan Transaction:**

```
1. BEGIN TRANSACTION
2. Order::create()        ✓
3. DetailOrder::create()  ✓
4. Kurangi stok          ✗ GAGAL
5. ROLLBACK              ◄── Semua dibatalkan

Akibat:
- Tidak ada data yang tersimpan
- User mendapat pesan error
- User bisa mencoba lagi
- Data tetap KONSISTEN
```

### 4.2 Pessimistic Locking (`lockForUpdate()`)

**Kode:**
```php
$t = Tiket::lockForUpdate()->findOrFail($it['tiket_id']);
```

**Alasan Penggunaan:**

| Aspek | Penjelasan |
|-------|------------|
| **Race Condition Prevention** | Mencegah 2 transaksi membaca stok yang sama sebelum dikurangi |
| **Data Integrity** | Menjamin stok tidak pernah negatif atau oversold |
| **Serialization** | Memaksa transaksi berjalan secara berurutan untuk record yang sama |

**Ilustrasi Race Condition:**

```
TANPA LOCK - Race Condition Terjadi:
────────────────────────────────────
Timeline    User A              User B              Database (stok=1)
────────────────────────────────────────────────────────────────────
T1          SELECT stok         -                   stok = 1
T2          -                   SELECT stok         stok = 1
T3          stok >= 1? ✓        -                   -
T4          -                   stok >= 1? ✓        -
T5          UPDATE stok=0       -                   stok = 0
T6          -                   UPDATE stok=0       stok = 0 (?)
────────────────────────────────────────────────────────────────────
Hasil: 2 tiket terjual, padahal stok cuma 1! BUG!


DENGAN lockForUpdate() - Race Condition Dicegah:
────────────────────────────────────────────────
Timeline    User A              User B              Database (stok=1)
────────────────────────────────────────────────────────────────────
T1          LOCK & SELECT       -                   stok = 1 (LOCKED)
T2          stok >= 1? ✓        SELECT (BLOCKED)    menunggu...
T3          UPDATE stok=0       (menunggu)          stok = 0
T4          COMMIT & UNLOCK     -                   stok = 0 (UNLOCKED)
T5          -                   LOCK & SELECT       stok = 0
T6          -                   stok >= 1? ✗        ERROR: Stok habis
────────────────────────────────────────────────────────────────────
Hasil: Hanya 1 tiket terjual. BENAR!
```

### 4.3 Eloquent Relationships

**Kode:**
```php
public function tikets()
{
    return $this->hasMany(Tiket::class);
}

public function kategori()
{
    return $this->belongsTo(Kategori::class);
}
```

**Alasan Penggunaan:**

| Aspek | Manfaat |
|-------|---------|
| **Readability** | `$event->tikets` lebih mudah dibaca daripada raw SQL |
| **Maintainability** | Perubahan relasi cukup di satu tempat (model) |
| **Eager Loading** | `Event::with('tikets')` mencegah N+1 query problem |
| **Type Safety** | IDE bisa memberikan autocomplete dan type hints |
| **Security** | Otomatis escaped, mencegah SQL injection |

**Perbandingan dengan Raw SQL:**

```php
// TANPA Eloquent (verbose dan rawan error)
$events = DB::select('
    SELECT e.*, k.nama as kategori_nama
    FROM events e
    LEFT JOIN kategoris k ON e.kategori_id = k.id
');

// DENGAN Eloquent (clean dan aman)
$events = Event::with('kategori')->get();
```

### 4.4 Request Validation

**Kode:**
```php
$data = $request->validate([
    'event_id' => 'required|exists:events,id',
    'items' => 'required|array|min:1',
    'items.*.tiket_id' => 'required|integer|exists:tikets,id',
    'items.*.jumlah' => 'required|integer|min:1',
]);
```

**Alasan Penggunaan:**

| Aspek | Manfaat |
|-------|---------|
| **Security** | Mencegah data invalid/malicious masuk ke sistem |
| **Data Integrity** | Memastikan foreign key valid (`exists:events,id`) |
| **Early Failure** | Gagal cepat sebelum proses berat dimulai |
| **Clean Code** | Validasi terpusat, tidak tersebar di business logic |
| **Error Messages** | Laravel otomatis generate pesan error yang user-friendly |

### 4.5 AJAX Response Pattern

**Kode:**
```php
// Success
return response()->json([
    'ok' => true,
    'order_id' => $order->id,
    'redirect' => route('orders.index')
]);

// Error
return response()->json([
    'ok' => false,
    'message' => $e->getMessage()
], 422);
```

**Alasan Penggunaan:**

| Aspek | Manfaat |
|-------|---------|
| **User Experience** | Tidak perlu reload halaman penuh |
| **Feedback Cepat** | User langsung tahu berhasil/gagal |
| **Error Handling** | Pesan error spesifik bisa ditampilkan |
| **Flexibility** | Frontend bisa memproses response sesuai kebutuhan |
| **Performance** | Transfer data lebih kecil dibanding reload HTML |

### 4.6 Ringkasan Pattern untuk Stabilitas Sistem

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    POLA KODE UNTUK STABILITAS SISTEM                         │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│  1. DATABASE TRANSACTION                                                     │
│     ├── Menjamin atomicity (all-or-nothing)                                 │
│     ├── Mencegah data inkonsisten                                           │
│     └── Rollback otomatis saat error                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│  2. PESSIMISTIC LOCKING                                                      │
│     ├── Mencegah race condition                                             │
│     ├── Menjamin stok tidak oversold                                        │
│     └── Serialisasi akses ke resource kritis                                │
├─────────────────────────────────────────────────────────────────────────────┤
│  3. ELOQUENT ORM                                                             │
│     ├── Abstraksi database yang aman                                        │
│     ├── Mencegah SQL injection                                              │
│     └── Eager loading untuk performa                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│  4. REQUEST VALIDATION                                                       │
│     ├── Validasi input sebelum proses                                       │
│     ├── Mencegah data invalid masuk sistem                                  │
│     └── Early failure untuk efisiensi                                       │
├─────────────────────────────────────────────────────────────────────────────┤
│  5. EXCEPTION HANDLING                                                       │
│     ├── Try-catch untuk error recovery                                      │
│     ├── Pesan error informatif ke user                                      │
│     └── Logging untuk debugging                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│  6. SEPARATION OF CONCERNS (MVC)                                             │
│     ├── Route: Routing & middleware                                         │
│     ├── Controller: Business logic                                          │
│     ├── Model: Data & relationships                                         │
│     └── View: Presentation                                                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

*Dokumentasi ini menjelaskan alur logika MVC pada sistem pembelian tiket BengTix*

# Dokumentasi Fitur Lokasi

Dokumentasi file-file yang ditambahkan untuk fitur **Manajemen Lokasi** pada aplikasi ticketing.

---

## Daftar File yang Ditambahkan

### 1. Model
**File:** `app/Models/Lokasi.php`

```php
class Lokasi extends Model
{
    protected $table = 'lokasi_events';
    protected $fillable = ['nama'];

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
```

- Menggunakan tabel `lokasi_events`
- Field: `id`, `nama`, `created_at`, `updated_at`
- Relasi: hasMany ke Event

---

### 2. Migration
**File:** `database/migrations/2026_01_27_174237_create_lokasi_events_table.php`

```php
Schema::create('lokasi_events', function (Blueprint $table) {
    $table->id();
    $table->string('nama');
    $table->timestamps();
});
```

**Cara menjalankan:**
```bash
php artisan migrate
```

---

### 3. Controller
**File:** `app/Http/Controllers/Admin/LokasiController.php`

Method yang tersedia:
| Method | Route | Fungsi |
|--------|-------|--------|
| `index()` | GET `/admin/lokasi` | Menampilkan daftar lokasi |
| `store()` | POST `/admin/lokasi` | Menyimpan lokasi baru |
| `update()` | PUT `/admin/lokasi/{id}` | Mengupdate lokasi |
| `destroy()` | DELETE `/admin/lokasi/{id}` | Menghapus lokasi |

---

### 4. View
**File:** `resources/views/admin/lokasi/index.blade.php`

Fitur pada halaman:
- Tabel daftar lokasi
- Tombol "Tambah Lokasi" (modal)
- Tombol "Edit" per baris (modal)
- Tombol "Hapus" per baris (modal konfirmasi)
- Toast notification untuk feedback sukses

---

### 5. Route
**File:** `routes/web.php`

```php
Route::resource('lokasi', LokasiController::class);
```

Route yang dihasilkan:
| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/lokasi` | `admin.lokasi.index` |
| POST | `/admin/lokasi` | `admin.lokasi.store` |
| PUT | `/admin/lokasi/{lokasi}` | `admin.lokasi.update` |
| DELETE | `/admin/lokasi/{lokasi}` | `admin.lokasi.destroy` |

---

### 6. Sidebar Menu
**File:** `resources/views/components/admin/sidebar.blade.php`

Ditambahkan menu "Manajemen Lokasi" dengan icon pin/lokasi di sidebar admin.

---

## Struktur File

```
ticketing-app/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Admin/
│   │           └── LokasiController.php
│   └── Models/
│       └── Lokasi.php
├── database/
│   └── migrations/
│       └── 2026_01_27_174237_create_lokasi_events_table.php
├── resources/
│   └── views/
│       ├── admin/
│       │   └── lokasi/
│       │       └── index.blade.php
│       └── components/
│           └── admin/
│               └── sidebar.blade.php (modified)
└── routes/
    └── web.php (modified)
```

---

## Akses Fitur

URL: `http://localhost:8000/admin/lokasi`

Hanya dapat diakses oleh user dengan role admin.

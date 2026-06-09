# Dokumentasi Role-Based Access Control (RBAC)

Dokumen ini menjelaskan implementasi sistem keamanan berbasis peran (Role-Based Access Control) pada aplikasi **SCOPE**. Implementasi ini dirancang menggunakan pendekatan hierarki level jabatan untuk memastikan skalabilitas dan keamanan data, khususnya dalam pengelolaan data Asakai dan Dashboard.

## 1. Hierarki Jabatan (Role Levels)

Sistem menggunakan pemetaan level (angka) yang merepresentasikan tingkatan jabatan di perusahaan. Semakin kecil angkanya, semakin tinggi wewenangnya.

| ID Level | Slug / Kode Role | Nama Jabatan di Perusahaan | Keterangan |
| :--- | :--- | :--- | :--- |
| 1 | `superadmin` | Super Administrator | Akses penuh ke seluruh sistem (Bypass). |
| 2 | `president-director` | President Director | Top Executive. Berwenang melihat semua data & menentukan target. |
| 3 | `division-head` | Division Head | Top Executive. Berwenang melihat semua data & menentukan target. |
| 4 | `general-manager` | General Manager | Top Management. Berwenang melihat semua data & menentukan target. |
| 5 | `manager` | Manager | Top Management. Berwenang menentukan target departemen. |
| 6 | `supervisor` | Supervisor | Middle Management. Berwenang menginput data harian/operasional. |
| 7 | `leader` | Leader | Middle Management. (Operasional spesifik) |
| 8 | `staff` | Staff | Operational. Berwenang melihat data (Read-Only) yang bersifat publik/transparan. |

> [!NOTE]
> Penggunaan **Level** (seperti `Level ≤ 5`) dalam kode lebih disarankan daripada menyebut nama peran secara hardcode. Ini mencegah *bug* jika di masa depan terdapat penambahan jabatan baru di tengah struktur hierarki.

---

## 2. Pemetaan Hak Akses (Feature Mapping)

### A. Modul Asakai Board
Modul ini mengedepankan prinsip transparansi (semua bisa melihat), namun menerapkan restriksi ketat pada manipulasi data berdasarkan wewenang struktural.

*   **`asakai-board` (Akses Lihat/Read-Only)**
    *   **Level yang diizinkan:** Semua pengguna terautentikasi (Level 1-8).
    *   **Fungsi:** Melihat grafik Asakai, daftar alasan (reasons), dan data historis.
*   **`asakai-input` (Akses Input Operasional)**
    *   **Level yang diizinkan:** Level ≤ 6 (Supervisor, Manager, GM, Div Head, PresDir).
    *   **Fungsi:** Menginput data chart harian, memperbarui chart, dan menambahkan/mengubah data *reasons* (penyebab deviasi).
*   **`asakai-content` (Akses Manajerial Strategis)**
    *   **Level yang diizinkan:** Level ≤ 5 (Manager, GM, Div Head, PresDir).
    *   **Fungsi:** Menetapkan target KPI bulanan/tahunan (Targets) dan menghapus data historis (Delete operations).

### B. Modul Dashboard per Departemen
Akses dibatasi berdasarkan gabungan **Level Jabatan** dan **Kode Departemen (Dept Code)**.
*(Top Management Level ≤ 5 memiliki akses Bypass ke semua dashboard departemen).*

| Feature Code | Wewenang Akses | Endpoint API yang Dilindungi |
| :--- | :--- | :--- |
| `inventory` | Level ≤ 5 **ATAU** Dept: `WH, BRZ, CHS, NYL` | `/api/dashboard/inventory`, `/api/dashboard/inventory-rev` |
| `inventory-movement`| Level ≤ 5 **ATAU** Dept: `WH, BRZ, CHS, NYL` | `/api/dashboard/warehouse`, `/api/dashboard/warehouse-rev` |
| `production` | Level ≤ 5 **ATAU** Dept: `CHS, BRZ, NYL` | `/api/dashboard/production` |
| `sales` | Level ≤ 5 **ATAU** Dept: `MKT, PUR` | `/api/dashboard/sales`, `/api/dashboard/procurement`, dll |
| `hr` | Level ≤ 5 **ATAU** Dept: `HRD, GA` | `/api/dashboard/hr` |
| `planning-manage` | Level ≤ 5 **ATAU** Dept: `PPIC, TOP` | `/api/production-plan`, `/api/wh-delivery-plan` |

---

## 3. Implementasi Kode

Sistem RBAC diterapkan di dua sisi (Backend dan Frontend) untuk memastikan keamanan mutlak pada API sekaligus memberikan pengalaman pengguna (UX) yang baik di antarmuka (menyembunyikan tombol yang tidak berhak diklik).

### Error Handling
Jika terjadi pelanggaran hak akses (pelaku tidak memiliki level yang mencukupi):
1.  **Sisi Backend (API):** Middleware akan memblokir *request* dan mengembalikan status HTTP `403 Forbidden` dengan format JSON standar: `{"success": false, "message": "Forbidden - You do not have permission to access this feature"}`. Jika token tidak ada/tidak valid, dikembalikan `401 Unauthenticated`.
2.  **Sisi Frontend (UI):** Fungsi `hasAccess('feature')` akan mereturn `false`. *Best practice* di frontend adalah menggunakan fungsi ini untuk menyembunyikan (hide) atau menonaktifkan (disable) tombol aksi (misal: tombol "Add Target"). Jika request tetap lolos ke API dan mendapat `403`, interseptor/catch block Axios/Fetch akan menampilkan notifikasi (Toast/Alert) *'Akses Ditolak'* kepada pengguna.

### A. Kode Backend (`app/Http/Middleware/FeatureMiddleware.php`)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureMiddleware
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // 1. Ekstraksi Data User
        // ... (Kode ekstraksi user & department) ...

        // 2. [ERROR HANDLING] Cegat jika tidak memiliki peran
        if (!$roleSlug) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // 3. Pengecekan Izin
        if ($this->hasAccess($roleSlug, $deptCode, $feature)) {
            return $next($request);
        }

        // 4. [ERROR HANDLING] Cegat jika level tidak mencukupi
        return response()->json(['success' => false, 'message' => 'Forbidden - You do not have permission to access this feature'], 403);
    }

    private function getRoleLevel(?string $roleSlug): int
    {
        if (!$roleSlug) return 99; // Default no level (tidak memiliki akses)
        
        $levels = [
            'superadmin' => 1,
            'president-director' => 2,
            'division-head' => 3,
            'general-manager' => 4,
            'manager' => 5,
            'supervisor' => 6,
            'leader' => 7,
            'staff' => 8,
        ];
        return $levels[$roleSlug] ?? 99;
    }

    private function hasAccess(?string $roleSlug, ?string $deptCode, string $feature): bool
    {
        if ($roleSlug === 'superadmin') return true;

        $roleLevel = $this->getRoleLevel($roleSlug);
        $isTopManagement = $roleLevel <= 5; // Berlaku untuk Manager s.d President Director

        switch ($feature) {
            case 'asakai-board':
                return true; // Read-only untuk semua
            case 'asakai-input':
                return $roleLevel <= 6; // Akses Input: Supervisor ke atas
            case 'asakai-content':
                return $isTopManagement; // Akses Target/Hapus: Manager ke atas
            
            // ... (Pengecekan departemen lainnya) ...
            default:
                return false;
        }
    }
}
```

### B. Kode Frontend (`src/context/AuthContext.tsx`)

Logika yang identik direplikasi di *Client-Side* untuk kebutuhan rendering komponen reaktif di React.

```typescript
const hasAccess = (feature: string): boolean => {
    if (!user) return false;

    const roleSlug = user.role?.slug || '';
    const deptCode = user.department?.code || '';

    // Superadmin bypass
    if (roleSlug === 'superadmin') return true;

    // Mapping Hierarki Level
    const levels: Record<string, number> = {
        'superadmin': 1,
        'president-director': 2,
        'division-head': 3,
        'general-manager': 4,
        'manager': 5,
        'supervisor': 6,
        'leader': 7,
        'staff': 8,
    };
    
    const roleLevel = levels[roleSlug] || 99;
    const isTopManagement = roleLevel <= 5;

    switch (feature) {
        case 'asakai-board':
            return true; // Semua orang dapat melihat
        case 'asakai-input':
            return roleLevel <= 6; // Hanya Supervisor dan ke atas yang dapat input data
        case 'asakai-content':
            return isTopManagement; // Hanya Manager dan ke atas yang kelola target
        
        // ... (Pengecekan departemen lainnya) ...
        default:
            return false;
    }
};
```

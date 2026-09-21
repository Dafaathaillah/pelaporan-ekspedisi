# Escort Readiness Countdown Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengunci action mulai escort unit ekspedisi sampai tujuh jam setelah kedatangan dan menampilkan countdown yang sama kepada Driver, Koordinator, Admin Purchasing, serta Developer.

**Architecture:** `ExpeditionReport` menjadi sumber aturan readiness dengan accessor turunan dari `arrived_at`. `DriverEscortController` menegakkan aturan di dalam transaksi, sedangkan komponen Vue bersama menghitung tampilan countdown setiap detik dari timestamp server dan dipakai oleh halaman Driver serta halaman monitoring yang sudah ada.

**Tech Stack:** PHP 8.2, Laravel 12, PHPUnit 11, Inertia.js 2, Vue 3, Tailwind CSS, Node test runner, Vite 7.

**Spec:** `docs/superpowers/specs/2026-09-21-escort-readiness-countdown-design.md`

## Global Constraints

- Masa tunggu tepat tujuh jam dihitung dari `arrived_at` untuk unit ekspedisi biasa.
- Laporan dengan `internal_ijin_lintas_id` dikecualikan dan tetap memakai validasi tanggal escort yang ada.
- Backend menjadi otoritas; disabled state UI bukan satu-satunya pengaman.
- Countdown berubah menjadi ready tanpa reload.
- Tidak menambah kolom database, override, konfigurasi durasi, atau push notification baru.

---

### Task 1: Domain readiness dan payload seluruh role

**Files:**
- Modify: `app/Models/ExpeditionReport.php`
- Modify: `app/Http/Controllers/ExpeditionController.php`
- Test: `tests/Feature/DriverEscortWorkflowTest.php`
- Test: `tests/Feature/ArrivalPlanningTest.php`

**Interfaces:**
- Produces: `$report->ready_escort_at: Carbon|null`.
- Produces: `$report->is_ready_for_escort: bool`.
- Produces: JSON fields `ready_escort_at: string|null` dan `is_ready_for_escort: bool`.

- [ ] **Step 1: Tulis test readiness dan payload yang gagal**

Tambahkan test model/payload dengan waktu beku:

```php
$this->travelTo(Carbon::parse('2026-09-21 15:00:00'));
$report = $this->report($expedition, ['arrived_at' => Carbon::parse('2026-09-21 10:00:00')]);

$this->assertSame('2026-09-21 17:00:00', $report->ready_escort_at->format('Y-m-d H:i:s'));
$this->assertFalse($report->is_ready_for_escort);
```

Tambahkan assertion Inertia pada `/driver/escorts` dan nested reports di `/coordinator/arrival-monitoring` serta `/purchasing/arrival-monitoring`. Buat pula report internal dan assert `ready_escort_at === null` serta `is_ready_for_escort === true`.

- [ ] **Step 2: Jalankan test dan pastikan gagal karena accessor belum ada**

Run: `php artisan test tests/Feature/DriverEscortWorkflowTest.php tests/Feature/ArrivalPlanningTest.php`

Expected: FAIL pada field readiness, bukan setup atau authorization.

- [ ] **Step 3: Implementasikan accessor model minimal**

Tambahkan `$appends` dan accessor `Attribute`:

```php
protected $appends = ['ready_escort_at', 'is_ready_for_escort'];

protected function readyEscortAt(): Attribute
{
    return Attribute::get(fn () => $this->internal_ijin_lintas_id || ! $this->arrived_at
        ? null
        : $this->arrived_at->copy()->addHours(7));
}

protected function isReadyForEscort(): Attribute
{
    return Attribute::get(fn () => $this->internal_ijin_lintas_id
        || ($this->ready_escort_at && now()->greaterThanOrEqualTo($this->ready_escort_at)));
}
```

Di `ExpeditionController::monitoring()`, sertakan `internal_ijin_lintas_id` pada select report terbatas dan append kedua atribut sebelum serialization.

- [ ] **Step 4: Jalankan kedua file test hingga lulus**

Run: `php artisan test tests/Feature/DriverEscortWorkflowTest.php tests/Feature/ArrivalPlanningTest.php`

Expected: PASS.

- [ ] **Step 5: Commit domain dan payload**

```powershell
git add app/Models/ExpeditionReport.php app/Http/Controllers/ExpeditionController.php tests/Feature/DriverEscortWorkflowTest.php tests/Feature/ArrivalPlanningTest.php
git commit -m "feat: expose escort readiness after arrival"
```

---

### Task 2: Penegakan masa tunggu pada single dan batch claim

**Files:**
- Modify: `app/Http/Controllers/DriverEscortController.php`
- Test: `tests/Feature/DriverEscortWorkflowTest.php`

**Interfaces:**
- Consumes: `is_ready_for_escort` dan `ready_escort_at` dari Task 1.
- Produces: `ensureExpeditionIsReady(ExpeditionReport $report): void`.
- Produces: validation error key `ready_escort_at`.

- [ ] **Step 1: Tulis test single dan batch yang gagal**

Bekukan waktu pada satu detik sebelum boundary dan assert claim ditolak:

```php
$this->travelTo(Carbon::parse('2026-09-21 16:59:59'));
$report = $this->report($expedition, ['arrived_at' => Carbon::parse('2026-09-21 10:00:00')]);
$this->claim($driver, $report)->assertSessionHasErrors('ready_escort_at');
$this->assertSame('WAITING', $report->fresh()->escort_status);
```

Tambahkan test tepat pukul `17:00:00` diterima. Tambahkan batch berisi satu report ready dan satu belum ready; assert seluruh status tetap `WAITING`, batch id tetap null, dan file P5M tidak tersimpan. Pertahankan test internal sebagai bukti exemption.

- [ ] **Step 2: Jalankan subset dan saksikan kegagalan yang benar**

Run: `php artisan test tests/Feature/DriverEscortWorkflowTest.php --filter="seven_hours|batch_claim_is_atomic_when_one_selected_unit_is_not_ready"`

Expected: FAIL karena claim sebelum tujuh jam masih diterima.

- [ ] **Step 3: Implementasikan validasi di dalam transaksi terkunci**

```php
private function ensureExpeditionIsReady(ExpeditionReport $report): void
{
    if ($report->internal_ijin_lintas_id || $report->is_ready_for_escort) {
        return;
    }

    throw ValidationException::withMessages([
        'ready_escort_at' => 'Unit belum ready escort. Dapat mulai escort pada '.$report->ready_escort_at->format('d/m/Y H:i').' WITA.',
    ]);
}
```

Panggil setelah `lockForUpdate()` dan pemeriksaan site pada `claim()`. Pada `claimBatch()`, panggil untuk setiap item sebelum penyimpanan foto/perubahan status. Validasi tanggal internal tetap dijalankan.

- [ ] **Step 4: Jalankan seluruh workflow test**

Run: `php artisan test tests/Feature/DriverEscortWorkflowTest.php`

Expected: PASS untuk single, boundary, batch atomic, dan internal.

- [ ] **Step 5: Commit enforcement**

```powershell
git add app/Http/Controllers/DriverEscortController.php tests/Feature/DriverEscortWorkflowTest.php
git commit -m "feat: enforce seven hour escort wait"
```

---

### Task 3: Helper dan komponen countdown bersama

**Files:**
- Create: `resources/js/lib/escortReadiness.js`
- Create: `resources/js/Components/EscortReadinessBadge.vue`
- Create: `tests/js/escortReadiness.test.mjs`

**Interfaces:**
- Produces: `remainingEscortSeconds(readyAt, nowMs): number|null`.
- Produces: `formatEscortCountdown(seconds): string`.
- Produces: `isEscortReady(item, nowMs): boolean`.
- Produces: `<EscortReadinessBadge :item="report" />`.

- [ ] **Step 1: Tulis Node test yang gagal**

```js
import test from "node:test";
import assert from "node:assert/strict";
import { formatEscortCountdown, isEscortReady, remainingEscortSeconds } from "../../resources/js/lib/escortReadiness.js";

test("counts down and floors at zero", () => {
    const readyAt = "2026-09-21T17:00:00.000Z";
    assert.equal(remainingEscortSeconds(readyAt, Date.parse("2026-09-21T10:00:01.000Z")), 25199);
    assert.equal(remainingEscortSeconds(readyAt, Date.parse("2026-09-21T17:00:01.000Z")), 0);
});

test("formats a stable countdown", () => {
    assert.equal(formatEscortCountdown(25199), "06:59:59");
    assert.equal(formatEscortCountdown(0), "00:00:00");
});

test("becomes ready at the boundary", () => {
    const item = { ready_escort_at: "2026-09-21T17:00:00.000Z", is_ready_for_escort: false };
    assert.equal(isEscortReady(item, Date.parse("2026-09-21T17:00:00.000Z")), true);
});
```

- [ ] **Step 2: Jalankan Node test dan pastikan module belum ditemukan**

Run: `node --test tests/js/escortReadiness.test.mjs`

Expected: FAIL `ERR_MODULE_NOT_FOUND`.

- [ ] **Step 3: Implementasikan helper minimal**

Gunakan selisih `new Date(readyAt).getTime() - nowMs`, `Math.ceil`, floor nol, dan formatter jam/menit/detik dengan `padStart(2, "0")`. `isEscortReady` menerima internal exemption, boolean server, atau countdown nol.

- [ ] **Step 4: Jalankan Node test hingga lulus**

Run: `node --test tests/js/escortReadiness.test.mjs`

Expected: 3 tests PASS.

- [ ] **Step 5: Implementasikan badge Vue**

Komponen memakai `ref(Date.now())`, interval satu detik di `onMounted`, dan `clearInterval` di `onUnmounted`. Jangan render untuk internal atau status selain `WAITING`. Sebelum ready tampil panel amber “Ready Escort dalam”, angka monospace `HH:MM:SS`, dan waktu ready WITA. Setelah nol tampil badge emerald “Ready Escort”.

- [ ] **Step 6: Commit helper dan komponen**

```powershell
git add resources/js/lib/escortReadiness.js resources/js/Components/EscortReadinessBadge.vue tests/js/escortReadiness.test.mjs
git commit -m "feat: add shared escort countdown"
```

---

### Task 4: Integrasi UI Driver dan monitoring lintas-role

**Files:**
- Modify: `resources/js/Pages/Driver/Escorts.vue`
- Modify: `resources/js/Pages/Coordinator/Index.vue`
- Modify: `resources/js/Pages/Expedition/ArrivalMonitoring.vue`

**Interfaces:**
- Consumes: helper dan badge dari Task 3.
- Preserves: route, payload form, P5M, batch selection, close escort, filter, dan tabel/kartu responsif saat ini.

- [ ] **Step 1: Integrasikan Driver**

Ubah `canStart(item)` agar unit ekspedisi harus ready, sementara internal tetap memeriksa `escort_date === siteToday`. Tampilkan badge di kartu `WAITING`, disable tombol pemilihan dengan label “Menunggu 7 Jam”, cegah id belum ready masuk modal/submit, dan tampilkan `claimForm.errors.ready_escort_at`.

- [ ] **Step 2: Integrasikan Koordinator dan Developer**

Pada kolom “Informasi Escort” di `Coordinator/Index.vue`, render badge di bawah status untuk report `WAITING`. Tambahkan juga pada representasi mobile/detail yang menampilkan status escort. Developer otomatis menerima tampilan yang sama karena memakai route dan komponen koordinator.

- [ ] **Step 3: Integrasikan Admin Purchasing dan monitoring Koordinator**

Pada setiap nested report/unit selesai di `Expedition/ArrivalMonitoring.vue`, render badge readiness. Route `/purchasing/arrival-monitoring` dan `/coordinator/arrival-monitoring` memakai komponen yang sama, sehingga tidak membuat halaman duplikat.

- [ ] **Step 4: Verifikasi helper dan build Vue**

Run: `node --test tests/js/escortReadiness.test.mjs` lalu `npm run build`.

Expected: test PASS dan Vite build selesai tanpa error.

- [ ] **Step 5: Commit integrasi UI**

```powershell
git add resources/js/Pages/Driver/Escorts.vue resources/js/Pages/Coordinator/Index.vue resources/js/Pages/Expedition/ArrivalMonitoring.vue
git commit -m "feat: show escort readiness across roles"
```

---

### Task 5: Regression verification dan handoff

**Files:**
- Modify only if verification exposes a scoped defect.

**Interfaces:**
- Consumes: all outputs from Tasks 1–4.
- Produces: verified feature and clean diff.

- [ ] **Step 1: Format PHP**

Run: `vendor/bin/pint app/Models/ExpeditionReport.php app/Http/Controllers/DriverEscortController.php app/Http/Controllers/ExpeditionController.php tests/Feature/DriverEscortWorkflowTest.php tests/Feature/ArrivalPlanningTest.php`

Expected: exit 0.

- [ ] **Step 2: Jalankan targeted verification**

Run: `php artisan test tests/Feature/DriverEscortWorkflowTest.php tests/Feature/ArrivalPlanningTest.php`, `node --test tests/js/escortReadiness.test.mjs`, dan `npm run build`.

Expected: seluruh test PASS dan build sukses.

- [ ] **Step 3: Jalankan seluruh suite**

Run: `php artisan test`

Expected: seluruh suite PASS tanpa failure/error.

- [ ] **Step 4: Periksa hasil akhir**

Run: `git diff --check`, `git status --short`, dan `git log -5 --oneline`.

Expected: tidak ada whitespace error dan hanya perubahan yang disengaja.

- [ ] **Step 5: Commit formatter bila perlu**

Jika Pint mengubah file, commit dengan `git commit -m "style: format escort readiness implementation"`. Jika tidak ada perubahan, lewati langkah ini.

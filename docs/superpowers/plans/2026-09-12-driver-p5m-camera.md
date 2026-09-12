# Driver P5M Camera Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Require a fresh in-app camera photo before a driver can claim an escort, then let authorized coordinators preview and download that P5M photo with the starting driver's identity.

**Architecture:** Keep the existing private `p5m_photo_path` storage and `escort_driver_id` relationship. Strengthen the claim endpoint with required server validation, extend the authorized photo response with explicit inline/download modes, isolate browser camera lifecycle and frame capture in a focused Vue component, and compose it into the existing driver and coordinator modals.

**Tech Stack:** Laravel 12/PHPUnit, Inertia.js, Vue 3 `<script setup>`, MediaDevices/getUserMedia, Canvas/Blob, Tailwind CSS, Vite.

**Spec:** `docs/superpowers/specs/2026-09-12-driver-p5m-camera-design.md`

## Global Constraints

- New escort claims require `p5m_photo`; legacy `PROCESS`/`CLOSED` rows without a photo remain readable.
- Accepted images are JPEG, PNG, or WebP and no larger than 8 MB.
- The driver UI must expose only live camera capture, never a gallery/file picker.
- Prefer the rear camera with `facingMode: environment` and stop every media track whenever capture is no longer active.
- P5M files remain on the private local disk and are served only through an authorized controller action.
- Preview uses `inline`; download uses `attachment` and `P5M-<report>-<driver>.<extension>`.
- Photo ownership is the driver who starts the escort (`escort_driver_id`), not the driver who closes it.
- Camera access on network devices requires HTTPS; localhost remains valid for development.
- Preserve the existing close-escort, GPS, broadcast, site, and row-locking behavior.

## File Structure

- Create `resources/js/Components/P5mCameraCapture.vue`: own camera permission, stream lifecycle, canvas frame capture, preview, retake, and emitted `File`.
- Modify `resources/js/Pages/Driver/Escorts.vue`: open the camera modal for a selected report, submit the emitted photo, surface validation/conflict errors, and close/reset safely.
- Modify `app/Http/Controllers/DriverEscortController.php`: require the image, retain transactional cleanup, and serve inline/download responses with a safe filename.
- Modify `resources/js/Pages/Coordinator/Index.vue`: show driver attribution, an in-page image preview modal, download action, and legacy no-photo state.
- Modify `tests/Feature/DriverEscortWorkflowTest.php`: protect required upload, private access, response disposition/name, site isolation, and legacy behavior.

---

### Task 1: Enforce Required P5M and Authorized Preview/Download

**Files:**
- Modify: `app/Http/Controllers/DriverEscortController.php:31-50,89-98`
- Test: `tests/Feature/DriverEscortWorkflowTest.php`

**Interfaces:**
- Consumes: multipart claim field `p5m_photo`; optional photo query `download=1`.
- Produces: required claim validation; `p5mPhoto(Request, ExpeditionReport)` returning authorized inline or attachment responses.

- [ ] **Step 1: Replace the optional-upload test and add failing request/response tests**

Add tests with literal expectations. Keep `Storage::fake('local')` inside each storage test.

```php
public function test_driver_must_attach_p5m_when_starting_escort(): void
{
    $driver = User::factory()->create(['role' => UserRole::DRIVER, 'permissions' => ['driver.escort']]);
    $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));

    $this->actingAs($driver)
        ->patch('/driver/escorts/'.$report->id.'/claim')
        ->assertSessionHasErrors('p5m_photo');

    $this->assertDatabaseHas('expedition_reports', [
        'id' => $report->id,
        'escort_status' => 'WAITING',
        'escort_driver_id' => null,
        'p5m_photo_path' => null,
    ]);
}

public function test_driver_can_start_escort_with_a_valid_p5m_photo(): void
{
    Storage::fake('local');
    $driver = User::factory()->create(['name' => 'Driver Kamera', 'role' => UserRole::DRIVER, 'permissions' => ['driver.escort']]);
    $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));

    $this->actingAs($driver)->post('/driver/escorts/'.$report->id.'/claim', [
        '_method' => 'PATCH',
        'p5m_photo' => UploadedFile::fake()->image('camera-p5m.jpg'),
    ])->assertSessionHasNoErrors();

    $report->refresh();
    $this->assertSame($driver->id, $report->escort_driver_id);
    $this->assertSame('PROCESS', $report->escort_status);
    Storage::disk('local')->assertExists($report->p5m_photo_path);
}

public function test_coordinator_can_preview_and_download_p5m_with_safe_driver_filename(): void
{
    Storage::fake('local');
    $driver = User::factory()->create(['name' => 'Driver / 8001', 'role' => UserRole::DRIVER]);
    $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'BIB']);
    $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));
    $path = 'private/expedition-reports/EXP-ESCORT/p5m.jpg';
    Storage::disk('local')->put($path, 'image-bytes');
    $report->update(['escort_driver_id' => $driver->id, 'p5m_photo_path' => $path]);

    $this->actingAs($coordinator)
        ->get('/driver/escorts/'.$report->id.'/p5m-photo')
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=p5m.jpg');

    $this->get('/driver/escorts/'.$report->id.'/p5m-photo?download=1')
        ->assertOk()
        ->assertDownload('P5M-EXP-ESCORT-Driver-8001.jpg');
}

public function test_unrelated_driver_and_coordinator_from_another_site_cannot_access_p5m(): void
{
    Storage::fake('local');
    $owner = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB']);
    $other = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
    $otherSite = User::factory()->create(['role' => UserRole::COORDINATOR, 'site' => 'KIM']);
    $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));
    $path = 'private/expedition-reports/EXP-ESCORT/p5m.jpg';
    Storage::disk('local')->put($path, 'image-bytes');
    $report->update(['escort_driver_id' => $owner->id, 'p5m_photo_path' => $path]);

    $this->actingAs($other)->get('/driver/escorts/'.$report->id.'/p5m-photo')->assertNotFound();
    $this->actingAs($otherSite)->get('/driver/escorts/'.$report->id.'/p5m-photo')->assertNotFound();
}

public function test_legacy_escort_without_p5m_has_no_photo_response(): void
{
    $driver = User::factory()->create(['role' => UserRole::DRIVER, 'site' => 'BIB', 'permissions' => ['driver.escort']]);
    $report = $this->report(User::factory()->create(['role' => UserRole::EXPEDITION]));
    $report->update(['escort_status' => 'PROCESS', 'escort_driver_id' => $driver->id, 'p5m_photo_path' => null]);

    $this->actingAs($driver)->get('/driver/escorts/'.$report->id.'/p5m-photo')->assertNotFound();
    $this->get('/driver/escorts')->assertOk();
}
```

Update existing tests that successfully claim an escort so each sends `UploadedFile::fake()->image('p5m.jpg')`. This is required setup, not a weakening of the new validation contract.

- [ ] **Step 2: Run the focused test and confirm RED**

Run:

```powershell
php artisan test tests/Feature/DriverEscortWorkflowTest.php
```

Expected: the missing-photo test fails because the request currently succeeds, and the attachment assertion fails because the current endpoint always responds inline.

- [ ] **Step 3: Implement required validation and safe disposition**

Change claim validation to:

```php
$request->validate([
    'p5m_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
], [
    'p5m_photo.required' => 'Foto P5M wajib diambil sebelum memulai escort.',
]);
```

After the access/file guard in `p5mPhoto`, derive a safe filename and select disposition:

```php
$extension = pathinfo($report->p5m_photo_path, PATHINFO_EXTENSION) ?: 'jpg';
$reportName = str($report->report_number)->ascii()->slug('-')->value();
$driverName = str($report->escortDriver?->name ?? 'Driver')->ascii()->slug('-')->value();
$downloadName = "P5M-{$reportName}-{$driverName}.{$extension}";

if ($request->boolean('download')) {
    return Storage::disk('local')->download($report->p5m_photo_path, $downloadName);
}

return Storage::disk('local')->response(
    $report->p5m_photo_path,
    basename($report->p5m_photo_path),
    ['Content-Disposition' => 'inline; filename='.basename($report->p5m_photo_path)],
);
```

Do not change the transaction, lock, site guard, cleanup, or broadcast behavior.

- [ ] **Step 4: Run the focused test and confirm GREEN**

Run:

```powershell
php artisan test tests/Feature/DriverEscortWorkflowTest.php
```

Expected: all driver escort workflow tests pass.

- [ ] **Step 5: Commit the backend contract**

```powershell
git add app/Http/Controllers/DriverEscortController.php tests/Feature/DriverEscortWorkflowTest.php
git commit -m "feat: require P5M photo for driver escorts"
```

---

### Task 2: Build the Live Camera Capture Component

**Files:**
- Create: `resources/js/Components/P5mCameraCapture.vue`

**Interfaces:**
- Consumes props: `active: Boolean`, `error: String`.
- Produces event: `captured(file: File|null)` where a JPEG `File` is emitted after capture and `null` after retake/reset.
- Internal functions: `startCamera(): Promise<void>`, `stopCamera(): void`, `capturePhoto(): void`, `retake(): Promise<void>`.

- [ ] **Step 1: Create the component shell and explicit camera states**

Use `ref` state for `video`, `canvas`, `stream`, `previewUrl`, `cameraError`, and `starting`. Watch `active`; start the camera when true and stop/reset when false. Register `onBeforeUnmount(stopCamera)`.

```vue
<script setup>
import { onBeforeUnmount, ref, watch } from "vue";

const props = defineProps({ active: Boolean, error: { type: String, default: "" } });
const emit = defineEmits(["captured"]);
const video = ref(null);
const canvas = ref(null);
const stream = ref(null);
const previewUrl = ref("");
const cameraError = ref("");
const starting = ref(false);

const stopCamera = () => {
    stream.value?.getTracks().forEach((track) => track.stop());
    stream.value = null;
    if (video.value) video.value.srcObject = null;
};

const resetCapture = () => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = "";
    emit("captured", null);
};
</script>
```

- [ ] **Step 2: Implement rear-camera startup with actionable errors**

```js
const startCamera = async () => {
    stopCamera();
    resetCapture();
    cameraError.value = "";
    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value = "Kamera tidak tersedia. Gunakan perangkat berkamera melalui HTTPS atau localhost.";
        return;
    }
    starting.value = true;
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: { ideal: "environment" } },
        });
        video.value.srcObject = stream.value;
        await video.value.play();
    } catch (error) {
        cameraError.value = error?.name === "NotAllowedError"
            ? "Izin kamera ditolak. Aktifkan izin kamera browser lalu coba lagi."
            : "Kamera gagal dibuka atau sedang digunakan aplikasi lain.";
    } finally {
        starting.value = false;
    }
};
```

- [ ] **Step 3: Implement frame capture, preview, and retake**

Use the video's intrinsic dimensions, JPEG quality `0.9`, and a deterministic upload name.

```js
const capturePhoto = () => {
    const width = video.value?.videoWidth;
    const height = video.value?.videoHeight;
    if (!width || !height) {
        cameraError.value = "Kamera belum siap. Tunggu sebentar lalu coba kembali.";
        return;
    }
    canvas.value.width = width;
    canvas.value.height = height;
    canvas.value.getContext("2d").drawImage(video.value, 0, 0, width, height);
    canvas.value.toBlob((blob) => {
        if (!blob) {
            cameraError.value = "Foto gagal diproses. Silakan ambil foto ulang.";
            return;
        }
        stopCamera();
        previewUrl.value = URL.createObjectURL(blob);
        emit("captured", new File([blob], `p5m-${Date.now()}.jpg`, { type: "image/jpeg" }));
    }, "image/jpeg", 0.9);
};

const retake = async () => {
    resetCapture();
    await startCamera();
};
```

- [ ] **Step 4: Add the accessible responsive template**

Render exactly one primary state at a time: starting indicator, error panel with **Coba Aktifkan Kamera Lagi**, live `<video autoplay playsinline muted>`, or `<img>` preview with **Foto Ulang**. Keep `<canvas class="hidden">`. The live capture button text is **Ambil Foto P5M**. Do not add `<input type="file">`.

- [ ] **Step 5: Verify compilation and camera-safety invariants**

Run:

```powershell
npm.cmd run build
Select-String -Path resources/js/Components/P5mCameraCapture.vue -Pattern 'type="file"'
```

Expected: Vite build succeeds; `Select-String` returns no match.

- [ ] **Step 6: Commit the camera component**

```powershell
git add resources/js/Components/P5mCameraCapture.vue
git commit -m "feat: add live P5M camera capture"
```

---

### Task 3: Integrate Mandatory Camera Capture into Driver Claim

**Files:**
- Modify: `resources/js/Pages/Driver/Escorts.vue:1-55, waiting-card claim button, claim modal`
- Use: `resources/js/Components/P5mCameraCapture.vue`

**Interfaces:**
- Consumes: `P5mCameraCapture` props/events from Task 2 and backend `p5m_photo` validation from Task 1.
- Produces: a claim modal that cannot submit until `claimForm.p5m_photo` is a captured `File`.

- [ ] **Step 1: Replace file-input imports and add modal lifecycle helpers**

Remove `FileDropInput`, import `P5mCameraCapture`, and add:

```js
const openClaim = (report) => {
    claimForm.reset();
    claimForm.clearErrors();
    selectedClaim.value = report;
};
const closeClaim = () => {
    selectedClaim.value = null;
    claimForm.reset();
    claimForm.clearErrors();
};
const setP5mPhoto = (file) => {
    claimForm.p5m_photo = file;
    if (file) claimForm.clearErrors("p5m_photo");
};
```

Change successful submission to call `closeClaim()`. Add `onError` that leaves the modal and captured preview open so validation is visible; if the response represents a taken-unit conflict, reload the escort list after dismissing the message using the project's existing feedback mechanism.

- [ ] **Step 2: Wire the waiting-card action to the camera modal**

Replace the inline reset assignment with:

```vue
<button class="btn mt-5 w-full" @click="openClaim(x)">
    Pilih & Mulai Escort
</button>
```

- [ ] **Step 3: Replace the optional FileDropInput modal**

```vue
<Modal :show="Boolean(selectedClaim)" max-width="lg" @close="closeClaim">
    <form class="p-6" @submit.prevent="claim">
        <p class="text-[10px] font-bold uppercase tracking-[.18em] text-red-600">Foto P5M Wajib</p>
        <h2 class="mt-1 text-xl font-bold">Mulai Escort {{ selectedClaim?.vehicle_plate }}</h2>
        <p class="mt-2 text-sm text-slate-500">Ambil foto P5M terbaru menggunakan kamera perangkat.</p>
        <P5mCameraCapture
            class="mt-4"
            :active="Boolean(selectedClaim)"
            :error="claimForm.errors.p5m_photo"
            @captured="setP5mPhoto"
        />
        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" class="btn-ghost" @click="closeClaim">Batal</button>
            <button class="btn" :disabled="claimForm.processing || !claimForm.p5m_photo">
                {{ claimForm.processing ? "Menyimpan..." : "Gunakan Foto & Mulai Escort" }}
            </button>
        </div>
    </form>
</Modal>
```

- [ ] **Step 4: Build and manually verify driver states**

Run:

```powershell
npm.cmd run build
```

Then test at mobile width using localhost or HTTPS:

1. click a waiting vehicle and confirm the camera prompt opens;
2. deny permission and confirm retry guidance plus blocked submit;
3. grant permission and confirm rear camera preference;
4. capture, preview, retake, and capture again;
5. submit and confirm escort becomes `PROCESS`;
6. cancel before/after capture and confirm the device camera indicator turns off;
7. confirm no gallery/file-picker action exists.

- [ ] **Step 5: Commit driver integration**

```powershell
git add resources/js/Pages/Driver/Escorts.vue
git commit -m "feat: require live P5M capture before escort"
```

---

### Task 4: Add Coordinator P5M Attribution, Preview Modal, and Download

**Files:**
- Modify: `resources/js/Pages/Coordinator/Index.vue:1-130,905-end`

**Interfaces:**
- Consumes: report fields `id`, `report_number`, `vehicle_plate`, `p5m_photo_path`, `escort_driver.name`, `escort_driver.hull_number`; photo query `download=1` from Task 1.
- Produces: `selectedP5m` preview state and preview/download controls in the escort detail modal.

- [ ] **Step 1: Add independent preview state and URLs**

```js
const selectedP5m = ref(null);
const p5mUrl = (report, download = false) =>
    `/driver/escorts/${report.id}/p5m-photo${download ? "?download=1" : ""}`;
const closeEscortDetail = () => {
    selectedP5m.value = null;
    selectedEscort.value = null;
};
```

Use `closeEscortDetail` for the detail modal close event/button so nested preview state cannot survive after its parent closes.

- [ ] **Step 2: Replace the simple P5M link with attributed actions and legacy state**

Inside the detail modal, add a full-width P5M section before close-photo/maps actions:

```vue
<section class="mt-4 rounded-2xl border border-slate-200 p-4">
    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Foto P5M</p>
    <template v-if="selectedEscort.p5m_photo_path">
        <p class="mt-2 text-sm font-bold text-slate-900">
            Diambil oleh {{ selectedEscort.escort_driver?.name || "Driver" }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
            Lambung {{ selectedEscort.escort_driver?.hull_number || "-" }}
        </p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <button type="button" class="btn-ghost" @click="selectedP5m = selectedEscort">Preview P5M</button>
            <a class="btn" :href="p5mUrl(selectedEscort, true)">Download P5M</a>
        </div>
    </template>
    <p v-else class="mt-2 text-sm text-slate-500">Foto P5M tidak tersedia.</p>
</section>
```

- [ ] **Step 3: Add the responsive preview modal**

Place it after the escort detail modal:

```vue
<Modal :show="Boolean(selectedP5m)" max-width="2xl" @close="selectedP5m = null">
    <div v-if="selectedP5m" class="p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[.18em] text-red-600">Preview Foto P5M</p>
                <h3 class="mt-1 text-xl font-bold">{{ selectedP5m.vehicle_plate }}</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ selectedP5m.report_number }} · {{ selectedP5m.escort_driver?.name || "Driver" }}
                </p>
            </div>
            <button type="button" class="btn-ghost" @click="selectedP5m = null">Tutup</button>
        </div>
        <div class="mt-4 overflow-hidden rounded-2xl bg-slate-100">
            <img :src="p5mUrl(selectedP5m)" :alt="`Foto P5M ${selectedP5m.vehicle_plate}`" class="max-h-[70vh] w-full object-contain" />
        </div>
        <a class="btn mt-4 inline-flex" :href="p5mUrl(selectedP5m, true)">Download P5M</a>
    </div>
</Modal>
```

Ensure the detail modal remains usable at narrow widths and actions have at least the existing 44 px target height.

- [ ] **Step 4: Build and manually verify coordinator behavior**

Run:

```powershell
npm.cmd run build
```

Verify one new escort and one legacy escort:

1. open the new escort detail and confirm the starting driver's name/hull appears;
2. open preview and confirm the image fits desktop and mobile viewports;
3. download and confirm the filename follows `P5M-<report>-<driver>.jpg`;
4. close preview and detail independently;
5. open the legacy escort and confirm **Foto P5M tidak tersedia** without broken image/link.

- [ ] **Step 5: Commit coordinator UI**

```powershell
git add resources/js/Pages/Coordinator/Index.vue
git commit -m "feat: preview and download escort P5M photos"
```

---

### Task 5: Full Regression and Security Verification

**Files:**
- Verify: all files changed in Tasks 1-4
- Verify: `resources/js/Components/AppShell.vue` remains an unrelated pre-existing working-tree change unless intentionally committed separately.

**Interfaces:**
- Consumes: completed backend and frontend behavior.
- Produces: verified release candidate with no regressions or accidental file inclusion.

- [ ] **Step 1: Run formatting/diff checks**

```powershell
git diff --check
git status --short
```

Expected: no whitespace errors; only intended files are modified. Do not absorb the existing AppShell menu edit into a feature commit unless explicitly desired.

- [ ] **Step 2: Run the full backend suite**

```powershell
php artisan test
```

Expected: all tests pass with zero failures.

- [ ] **Step 3: Run the production frontend build**

```powershell
npm.cmd run build
```

Expected: Vite exits with code 0 and no compilation errors.

- [ ] **Step 4: Perform final authorization checks with real routes**

Using test users at the same and different sites, verify:

- coordinator/developer at the report site can preview/download;
- starting or closing driver at the report site can view;
- unrelated driver, vendor, expedition, purchasing admin, unauthenticated user, and users at another site receive 404/redirect as appropriate;
- direct storage paths are not publicly reachable.

- [ ] **Step 5: Review the final diff against every acceptance criterion**

Confirm all six spec criteria: mandatory live capture, no gallery, private driver association, coordinator attribution/preview/download, unauthorized denial, and legacy compatibility. Confirm camera tracks stop on submit, cancel, retake transition, modal close, navigation, and component unmount.

- [ ] **Step 6: Commit any verification-only corrections**

If verification required code corrections, repeat the relevant focused RED/GREEN test and commit only those files:

```powershell
git add app/Http/Controllers/DriverEscortController.php tests/Feature/DriverEscortWorkflowTest.php resources/js/Components/P5mCameraCapture.vue resources/js/Pages/Driver/Escorts.vue resources/js/Pages/Coordinator/Index.vue
git commit -m "fix: harden P5M camera workflow"
```

Omit unchanged paths from `git add`; if no correction was needed, do not create an empty commit.

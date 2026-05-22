<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/logistics_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$tab = logistics_valid_tab((string)($_GET['tab'] ?? 'drivers'));
$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $entity = (string)($_POST['entity'] ?? '');
    $postAction = (string)($_POST['post_action'] ?? 'save');
    $postTab = logistics_valid_tab((string)($_POST['tab'] ?? $tab));
    $id = (int)($_POST['id'] ?? 0) ?: null;
    try {
        if ($postAction === 'disable' && $id) {
            logistics_disable($pdo, $entity, $id);
            set_flash('success', 'Record disabled.');
        } elseif ($entity === 'driver') {
            logistics_save_driver($pdo, $_POST, $id);
            set_flash('success', $id ? 'Driver updated.' : 'Driver saved.');
        } elseif ($entity === 'vehicle') {
            logistics_save_vehicle($pdo, $_POST, $id);
            set_flash('success', $id ? 'Vehicle updated.' : 'Vehicle saved.');
        } elseif ($entity === 'transport') {
            logistics_save_transport($pdo, $_POST, $id);
            set_flash('success', $id ? 'Transport company updated.' : 'Transport company saved.');
        } else {
            throw new InvalidArgumentException('Invalid form.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    $loc = route_url('dispatch/logistics') . '&tab=' . rawurlencode($postTab);
    if ($search !== '') {
        $loc .= '&q=' . rawurlencode($search);
    }
    if ($statusFilter !== '') {
        $loc .= '&status=' . rawurlencode($statusFilter);
    }
    header('Location: ' . $loc);
    exit;
}

$transportsActive = logistics_list_transport($pdo);
$driversActive = logistics_list_drivers($pdo);
$vehiclesActive = logistics_list_vehicles($pdo);

$editDriver = ($tab === 'drivers' && $editId) ? logistics_get_driver($pdo, $editId) : null;
$editVehicle = ($tab === 'vehicles' && $editId) ? logistics_get_vehicle($pdo, $editId) : null;
$editTransport = ($tab === 'transport' && $editId) ? logistics_get_transport($pdo, $editId) : null;

$drivers = logistics_all_drivers($pdo, $tab === 'drivers' ? $search : '', $tab === 'drivers' ? $statusFilter : '');
$vehicles = logistics_all_vehicles($pdo, $tab === 'vehicles' ? $search : '', $tab === 'vehicles' ? $statusFilter : '');
$transports = logistics_all_transport($pdo, $tab === 'transport' ? $search : '', $tab === 'transport' ? $statusFilter : '');

$baseUrl = 'index.php?page=dispatch/logistics';
?>

<div class="dsp-page">
    <header class="dsp-page__head">
        <div>
            <h1 class="dsp-page__title">Logistics</h1>
            <p class="dsp-page__sub">Drivers, vehicles, and transport companies — one place for all dispatch logistics.</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/new')) ?>">New dispatch</a>
            <a href="<?= e(route_url('dispatch/history')) ?>">History</a>
        </nav>
    </header>

    <ul class="dsp-tabs nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'drivers' ? 'active' : '' ?>" href="<?= e($baseUrl) ?>&amp;tab=drivers">Drivers</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'vehicles' ? 'active' : '' ?>" href="<?= e($baseUrl) ?>&amp;tab=vehicles">Vehicles</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'transport' ? 'active' : '' ?>" href="<?= e($baseUrl) ?>&amp;tab=transport">Transport companies</a>
        </li>
    </ul>

    <form method="get" class="row g-2 my-3 align-items-end dsp-logistics-filter">
        <input type="hidden" name="page" value="dispatch/logistics">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="col-md-4">
            <label class="form-label small">Search</label>
            <input class="form-control form-control-sm" name="q" value="<?= e($search) ?>" placeholder="Search this tab…">
        </div>
        <div class="col-auto">
            <label class="form-label small">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All</option>
                <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e($baseUrl) ?>&amp;tab=<?= e($tab) ?>">Reset</a>
        </div>
    </form>

    <?php if ($tab === 'drivers'): ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <section class="dsp-card">
                <div class="dsp-card__head"><h2 class="dsp-card__title"><?= $editDriver ? 'Edit driver' : 'Add driver' ?></h2></div>
                <div class="dsp-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <input type="hidden" name="entity" value="driver">
                        <input type="hidden" name="tab" value="drivers">
                        <input type="hidden" name="post_action" value="save">
                        <?php if ($editDriver): ?><input type="hidden" name="id" value="<?= (int)$editDriver['id'] ?>"><?php endif; ?>
                        <div><label class="form-label">Driver name</label>
                            <input class="form-control form-control-sm" name="driver_name" required maxlength="150"
                                   value="<?= e((string)($editDriver['driver_name'] ?? '')) ?>"></div>
                        <div><label class="form-label">Phone</label>
                            <input class="form-control form-control-sm" name="phone" maxlength="30"
                                   value="<?= e((string)($editDriver['phone'] ?? '')) ?>"></div>
                        <div><label class="form-label">License number</label>
                            <input class="form-control form-control-sm" name="license_number" maxlength="60"
                                   value="<?= e((string)($editDriver['license_number'] ?? '')) ?>"></div>
                        <div><label class="form-label">Assigned vehicle</label>
                            <select class="form-select form-select-sm" name="vehicle_id" id="log-driver-vehicle">
                                <option value="">— Select vehicle —</option>
                                <?php foreach ($vehiclesActive as $v): ?>
                                    <option value="<?= (int)$v['id'] ?>"
                                        data-transport-id="<?= (int)($v['transport_company_id'] ?? 0) ?>"
                                        <?= (int)($editDriver['vehicle_id'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>>
                                        <?= e((string)$v['vehicle_number']) ?> · <?= e((string)($v['transport_company_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></div>
                        <div><label class="form-label">Transport company</label>
                            <select class="form-select form-select-sm" name="transport_company_id" id="log-driver-transport" required>
                                <option value="">Select transport</option>
                                <?php foreach ($transportsActive as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"
                                        <?= (int)($editDriver['transport_company_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                                        <?= e((string)$t['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="dsp-field-hint">Auto-filled when vehicle is selected.</div></div>
                        <div><label class="form-label">Address</label>
                            <input class="form-control form-control-sm" name="address" maxlength="255"
                                   value="<?= e((string)($editDriver['address'] ?? '')) ?>"></div>
                        <?php if ($editDriver): ?>
                        <div><label class="form-label">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="Active" <?= ($editDriver['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= ($editDriver['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select></div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm"><?= $editDriver ? 'Update driver' : 'Save driver' ?></button>
                        <?php if ($editDriver): ?>
                            <a href="<?= e($baseUrl) ?>&amp;tab=drivers" class="btn btn-outline-secondary btn-sm">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <div class="dsp-table-wrap">
                <table class="dsp-table">
                    <thead>
                        <tr><th>Driver</th><th>Vehicle</th><th>Phone</th><th>License</th><th>Transport</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($drivers as $d): ?>
                        <tr>
                            <td><?= e((string)$d['driver_name']) ?></td>
                            <td><?= e((string)($d['assigned_vehicle'] ?? $d['vehicle_no'] ?? '—')) ?></td>
                            <td><?= e((string)($d['phone'] ?? '—')) ?></td>
                            <td><?= e((string)($d['license_number'] ?? '—')) ?></td>
                            <td><?= e((string)($d['transport_company_name'] ?? $d['transport_company'] ?? '—')) ?></td>
                            <td><?= e((string)$d['status']) ?></td>
                            <td class="text-nowrap">
                                <a href="<?= e($baseUrl) ?>&amp;tab=drivers&amp;edit=<?= (int)$d['id'] ?>">Edit</a>
                                <?php if (($d['status'] ?? '') === 'Active'): ?>
                                    <form method="post" class="d-inline ms-1" onsubmit="return confirm('Disable driver?');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="entity" value="driver">
                                        <input type="hidden" name="tab" value="drivers">
                                        <input type="hidden" name="post_action" value="disable">
                                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger">Disable</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($drivers === []): ?>
                        <tr><td colspan="7" class="dsp-empty">No drivers. Add a driver to enable dispatch.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'vehicles'): ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <section class="dsp-card">
                <div class="dsp-card__head"><h2 class="dsp-card__title"><?= $editVehicle ? 'Edit vehicle' : 'Add vehicle' ?></h2></div>
                <div class="dsp-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <input type="hidden" name="entity" value="vehicle">
                        <input type="hidden" name="tab" value="vehicles">
                        <input type="hidden" name="post_action" value="save">
                        <?php if ($editVehicle): ?><input type="hidden" name="id" value="<?= (int)$editVehicle['id'] ?>"><?php endif; ?>
                        <div><label class="form-label">Vehicle number</label>
                            <input class="form-control form-control-sm" name="vehicle_number" required maxlength="40"
                                   value="<?= e((string)($editVehicle['vehicle_number'] ?? '')) ?>" placeholder="PB10AX1234"></div>
                        <div><label class="form-label">Vehicle type</label>
                            <input class="form-control form-control-sm" name="vehicle_type" maxlength="60"
                                   value="<?= e((string)($editVehicle['vehicle_type'] ?? '')) ?>" placeholder="Truck / Trailer"></div>
                        <div><label class="form-label">Capacity</label>
                            <input class="form-control form-control-sm" name="capacity" maxlength="40"
                                   value="<?= e((string)($editVehicle['capacity'] ?? '')) ?>" placeholder="40 tyres"></div>
                        <div><label class="form-label">Assigned driver</label>
                            <select class="form-select form-select-sm" name="driver_id">
                                <option value="">— Optional —</option>
                                <?php foreach ($driversActive as $dr): ?>
                                    <option value="<?= (int)$dr['id'] ?>"
                                        <?= (int)($editVehicle['driver_id'] ?? 0) === (int)$dr['id'] ? 'selected' : '' ?>>
                                        <?= e((string)$dr['driver_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></div>
                        <div><label class="form-label">Transport company</label>
                            <select class="form-select form-select-sm" name="transport_company_id" required>
                                <option value="">Select transport</option>
                                <?php foreach ($transportsActive as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"
                                        <?= (int)($editVehicle['transport_company_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                                        <?= e((string)$t['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></div>
                        <?php if ($editVehicle): ?>
                        <div><label class="form-label">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="Active" <?= ($editVehicle['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= ($editVehicle['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select></div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm"><?= $editVehicle ? 'Update vehicle' : 'Save vehicle' ?></button>
                        <?php if ($editVehicle): ?>
                            <a href="<?= e($baseUrl) ?>&amp;tab=vehicles" class="btn btn-outline-secondary btn-sm">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <div class="dsp-table-wrap">
                <table class="dsp-table">
                    <thead>
                        <tr><th>Vehicle</th><th>Driver</th><th>Capacity</th><th>Company</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vehicles as $v): ?>
                        <tr>
                            <td><?= e((string)$v['vehicle_number']) ?><?= ($v['vehicle_type'] ?? '') !== '' ? ' <span class="text-muted small">(' . e((string)$v['vehicle_type']) . ')</span>' : '' ?></td>
                            <td><?= e((string)($v['driver_name'] ?? '—')) ?></td>
                            <td><?= e((string)($v['capacity'] ?? '—')) ?></td>
                            <td><?= e((string)($v['transport_company_name'] ?? '—')) ?></td>
                            <td><?= e((string)$v['status']) ?></td>
                            <td class="text-nowrap">
                                <a href="<?= e($baseUrl) ?>&amp;tab=vehicles&amp;edit=<?= (int)$v['id'] ?>">Edit</a>
                                <?php if (($v['status'] ?? '') === 'Active'): ?>
                                    <form method="post" class="d-inline ms-1" onsubmit="return confirm('Disable vehicle?');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="entity" value="vehicle">
                                        <input type="hidden" name="tab" value="vehicles">
                                        <input type="hidden" name="post_action" value="disable">
                                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger">Disable</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($vehicles === []): ?>
                        <tr><td colspan="6" class="dsp-empty">No vehicles registered.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <section class="dsp-card">
                <div class="dsp-card__head"><h2 class="dsp-card__title"><?= $editTransport ? 'Edit company' : 'Add transport company' ?></h2></div>
                <div class="dsp-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <input type="hidden" name="entity" value="transport">
                        <input type="hidden" name="tab" value="transport">
                        <input type="hidden" name="post_action" value="save">
                        <?php if ($editTransport): ?><input type="hidden" name="id" value="<?= (int)$editTransport['id'] ?>"><?php endif; ?>
                        <div><label class="form-label">Company name</label>
                            <input class="form-control form-control-sm" name="company_name" required maxlength="150"
                                   value="<?= e((string)($editTransport['company_name'] ?? '')) ?>"></div>
                        <div><label class="form-label">Contact person</label>
                            <input class="form-control form-control-sm" name="contact_person" maxlength="120"
                                   value="<?= e((string)($editTransport['contact_person'] ?? '')) ?>"></div>
                        <div><label class="form-label">Phone</label>
                            <input class="form-control form-control-sm" name="phone" maxlength="30"
                                   value="<?= e((string)($editTransport['phone'] ?? '')) ?>"></div>
                        <div><label class="form-label">GST number</label>
                            <input class="form-control form-control-sm" name="gst_number" maxlength="40"
                                   value="<?= e((string)($editTransport['gst_number'] ?? '')) ?>"></div>
                        <div><label class="form-label">Address</label>
                            <input class="form-control form-control-sm" name="address" maxlength="255"
                                   value="<?= e((string)($editTransport['address'] ?? '')) ?>"></div>
                        <?php if ($editTransport): ?>
                        <div><label class="form-label">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="Active" <?= ($editTransport['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= ($editTransport['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select></div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm"><?= $editTransport ? 'Update' : 'Save company' ?></button>
                        <?php if ($editTransport): ?>
                            <a href="<?= e($baseUrl) ?>&amp;tab=transport" class="btn btn-outline-secondary btn-sm">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <div class="dsp-table-wrap">
                <table class="dsp-table">
                    <thead>
                        <tr><th>Company</th><th>Contact</th><th>Phone</th><th>GST</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transports as $c): ?>
                        <tr>
                            <td><?= e((string)$c['company_name']) ?></td>
                            <td><?= e((string)($c['contact_person'] ?? '—')) ?></td>
                            <td><?= e((string)($c['phone'] ?? '—')) ?></td>
                            <td><?= e((string)($c['gst_number'] ?? '—')) ?></td>
                            <td><?= e((string)$c['status']) ?></td>
                            <td class="text-nowrap">
                                <a href="<?= e($baseUrl) ?>&amp;tab=transport&amp;edit=<?= (int)$c['id'] ?>">Edit</a>
                                <?php if (($c['status'] ?? '') === 'Active'): ?>
                                    <form method="post" class="d-inline ms-1" onsubmit="return confirm('Disable company?');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="entity" value="transport">
                                        <input type="hidden" name="tab" value="transport">
                                        <input type="hidden" name="post_action" value="disable">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger">Disable</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($transports === []): ?>
                        <tr><td colspan="6" class="dsp-empty">No transport companies.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
(function () {
    var vSel = document.getElementById('log-driver-vehicle');
    var tSel = document.getElementById('log-driver-transport');
    if (vSel && tSel) {
        vSel.addEventListener('change', function () {
            var opt = vSel.options[vSel.selectedIndex];
            var tid = opt ? opt.getAttribute('data-transport-id') : '';
            if (tid) {
                tSel.value = tid;
            }
        });
    }
})();
</script>

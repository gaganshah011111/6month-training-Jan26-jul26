<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0) ?: null;
    try {
        dispatch_save_customer($pdo, $_POST, $id);
        set_flash('success', $id ? 'Customer updated.' : 'Customer added.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('dispatch/customers');
}

$customers = dispatch_all_customers($pdo);
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    foreach ($customers as $c) {
        if ((int)$c['id'] === $editId) {
            $edit = $c;
            break;
        }
    }
}
?>

<div class="dsp-page">
    <header class="dsp-page__head">
        <div>
            <h1 class="dsp-page__title">Dispatch Customers</h1>
            <p class="dsp-page__sub">Customer master used in dispatch dropdowns.</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/new')) ?>">New dispatch</a>
            <a href="<?= e(route_url('dispatch/logistics')) ?>">Logistics</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="dsp-card">
                <div class="dsp-card__head">
                    <h2 class="dsp-card__title"><?= $edit ? 'Edit customer' : 'Add customer' ?></h2>
                </div>
                <div class="dsp-card__body">
                    <form method="post" class="dsp-form vstack gap-2">
                        <?= csrf_input() ?>
                        <?php if ($edit): ?>
                            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
                        <?php endif; ?>
                        <div><label class="form-label">Customer name</label>
                            <input class="form-control form-control-sm" name="customer_name" required maxlength="150"
                                   value="<?= e((string)($edit['customer_name'] ?? '')) ?>"></div>
                        <div><label class="form-label">Company</label>
                            <input class="form-control form-control-sm" name="company" maxlength="150"
                                   value="<?= e((string)($edit['company'] ?? '')) ?>"></div>
                        <div><label class="form-label">Phone</label>
                            <input class="form-control form-control-sm" name="phone" maxlength="30"
                                   value="<?= e((string)($edit['phone'] ?? '')) ?>"></div>
                        <div><label class="form-label">GST number</label>
                            <input class="form-control form-control-sm" name="gst_number" maxlength="40"
                                   value="<?= e((string)($edit['gst_number'] ?? '')) ?>"></div>
                        <div><label class="form-label">Address</label>
                            <input class="form-control form-control-sm" name="address" maxlength="255"
                                   value="<?= e((string)($edit['address'] ?? '')) ?>"></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">City</label>
                                <input class="form-control form-control-sm" name="city" maxlength="80"
                                       value="<?= e((string)($edit['city'] ?? '')) ?>"></div>
                            <div class="col-6"><label class="form-label">State</label>
                                <input class="form-control form-control-sm" name="state" maxlength="80"
                                       value="<?= e((string)($edit['state'] ?? '')) ?>"></div>
                        </div>
                        <?php if ($edit): ?>
                            <div><label class="form-label">Status</label>
                                <select class="form-select form-select-sm" name="status">
                                    <option value="Active" <?= ($edit['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Inactive" <?= ($edit['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select></div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm"><?= $edit ? 'Update' : 'Save customer' ?></button>
                        <?php if ($edit): ?>
                            <a href="<?= e(route_url('dispatch/customers')) ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            <div class="dsp-table-wrap">
                <table class="dsp-table">
                    <thead>
                        <tr>
                            <th>Name</th><th>Company</th><th>Phone</th><th>City</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><?= e((string)$c['customer_name']) ?></td>
                            <td><?= e((string)($c['company'] ?? '—')) ?></td>
                            <td><?= e((string)($c['phone'] ?? '—')) ?></td>
                            <td><?= e((string)($c['city'] ?? '—')) ?></td>
                            <td><?= e((string)$c['status']) ?></td>
                            <td><a href="<?= e(route_url('dispatch/customers')) ?>&amp;edit=<?= (int)$c['id'] ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($customers === []): ?>
                        <tr><td colspan="6" class="dsp-empty">No customers yet. Add your first customer.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

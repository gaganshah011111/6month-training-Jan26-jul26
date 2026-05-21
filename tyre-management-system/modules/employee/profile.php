<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attendance_workflow.php';

$pdo = Database::connection();
$error = '';
$success = '';
$passwordError = '';
$passwordSuccess = '';

try {
    $employee = require_employee_record($pdo);
    ensure_employee_columns($pdo);
    $employeeId = (int)$employee['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formType = (string)($_POST['form_type'] ?? 'profile');

        if ($formType === 'password') {
            verify_csrf();
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');
            $userId = employee_session_user_id();
            if ($newPassword === '' || $confirmPassword === '') {
                $passwordError = 'New password and confirmation are required.';
            } elseif (strlen($newPassword) < 8) {
                $passwordError = 'New password must be at least 8 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $passwordError = 'Passwords do not match.';
            } else {
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $userId]);
                $dbHash = (string)$stmt->fetchColumn();
                if (!password_verify($currentPassword, $dbHash) && !hash_equals($dbHash, $currentPassword)) {
                    $passwordError = 'Current password is incorrect.';
                } else {
                    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id');
                    $updateStmt->execute(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]);
                    $passwordSuccess = 'Password updated successfully.';
                }
            }
        } else {
            verify_csrf();
            $contactNo = trim((string)($_POST['contact_no'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $dob = trim((string)($_POST['dob'] ?? ''));
            $emergency = trim((string)($_POST['emergency_contact'] ?? ''));
            $profileImage = (string)($employee['profile_image'] ?? '');

            if ($contactNo !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $contactNo)) {
                $error = 'Please enter a valid phone number.';
            } elseif (strlen($address) > 255) {
                $error = 'Address is too long.';
            } elseif ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                $error = 'Invalid date of birth.';
            } elseif (strlen($emergency) > 120) {
                $error = 'Emergency contact is too long.';
            } else {
                if (isset($_FILES['profile_image']) && (int)($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if ((int)$_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                        $tmpPath = (string)$_FILES['profile_image']['tmp_name'];
                        $fileName = (string)$_FILES['profile_image']['name'];
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                        if (!in_array($ext, $allowed, true)) {
                            $error = 'Only jpg, jpeg, png, webp images are allowed.';
                        } else {
                            $uploadDir = __DIR__ . '/../../assets/uploads/profiles';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0775, true);
                            }
                            $newName = 'emp_' . $employeeId . '_' . time() . '.' . $ext;
                            if (!move_uploaded_file($tmpPath, $uploadDir . '/' . $newName)) {
                                $error = 'Failed to upload image.';
                            } else {
                                $profileImage = 'assets/uploads/profiles/' . $newName;
                            }
                        }
                    } else {
                        $error = 'Profile image upload failed.';
                    }
                }

                if ($error === '') {
                    $stmt = $pdo->prepare('UPDATE employees SET contact_no = :contact_no, address = :address, dob = :dob, emergency_contact = :emergency_contact, profile_image = :profile_image WHERE id = :id AND user_id = :user_id');
                    $stmt->execute([
                        'contact_no' => $contactNo !== '' ? $contactNo : null,
                        'address' => $address !== '' ? $address : null,
                        'dob' => $dob !== '' ? $dob : null,
                        'emergency_contact' => $emergency !== '' ? $emergency : null,
                        'profile_image' => $profileImage !== '' ? $profileImage : null,
                        'id' => $employeeId,
                        'user_id' => employee_session_user_id(),
                    ]);
                    $success = 'Profile updated successfully.';
                }
            }
        }

        $employee = get_employee_record($pdo, employee_session_user_id()) ?? $employee;
    }
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
}

$profileImg = !empty($employee['profile_image']) ? $employee['profile_image'] : '';
$joinDate = !empty($employee['joining_date']) ? date('d M Y', strtotime((string)$employee['joining_date'])) : '—';
$shiftStart = '09:00:00';
$shiftEnd = '18:00:00';
if (!empty($employee)) {
    [$shiftStart, $shiftEnd] = employee_shift_clock_bounds($employee);
}
?>

<div class="emp-portal emp-shell">
<header class="erp-page__top mb-3">
    <div>
        <h1 class="erp-page__title">My Profile<span>Contact details &amp; account settings</span></h1>
    </div>
    <div class="erp-page__top-actions">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('employee/dashboard')) ?>">Dashboard</a>
    </div>
</header>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<?php if (!empty($employee)): ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="emp-profile-card">
            <div class="emp-profile-card__banner"></div>
            <div class="emp-profile-card__body">
                <img src="<?= e($profileImg !== '' ? $profileImg : 'https://ui-avatars.com/api/?name=' . rawurlencode((string)$employee['full_name']) . '&size=192&background=1a2744&color=fff') ?>" alt="" class="emp-profile-card__avatar">
                <h2 class="emp-profile-card__name"><?= e($employee['full_name']) ?></h2>
                <p class="text-muted small mb-0"><?= e((string)($employee['designation'] ?? $employee['role'] ?? 'Employee')) ?></p>
                <ul class="emp-profile-meta">
                    <li><span>Employee ID</span><strong><?= e($employee['employee_code']) ?></strong></li>
                    <li><span>Department</span><strong><?= e($employee['department']) ?></strong></li>
                    <li><span>Joining date</span><strong><?= e($joinDate) ?></strong></li>
                    <li><span>Shift</span><strong><?= e(substr($shiftStart, 0, 5) . ' – ' . substr($shiftEnd, 0, 5)) ?></strong></li>
                    <li><span>Type</span><strong><?= e((string)($employee['employee_type'] ?? 'Staff')) ?></strong></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card emp-form-card mb-4">
            <div class="card-header">Update contact details</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_input() ?>
                    <input type="hidden" name="form_type" value="profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input class="form-control" value="<?= e((string)($employee['department'] ?? '')) ?>" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Designation</label>
                            <input class="form-control" value="<?= e((string)($employee['designation'] ?? $employee['role'] ?? '')) ?>" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input class="form-control" name="contact_no" maxlength="20" value="<?= e((string)($employee['contact_no'] ?? '')) ?>" placeholder="Mobile number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of birth</label>
                            <input class="form-control" type="date" name="dob" value="<?= e((string)($employee['dob'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency contact</label>
                            <input class="form-control" name="emergency_contact" maxlength="120" value="<?= e((string)($employee['emergency_contact'] ?? '')) ?>" placeholder="Name &amp; phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profile photo</label>
                            <div class="emp-upload-zone">
                                <input class="form-control form-control-sm" name="profile_image" type="file" accept=".jpg,.jpeg,.png,.webp">
                                <small class="text-muted">JPG, PNG or WebP · max 2MB recommended</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2" maxlength="255" placeholder="Residential address"><?= e((string)($employee['address'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Save profile</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card emp-form-card">
            <div class="card-header">Change password</div>
            <div class="card-body">
                <?php if ($passwordError): ?><div class="alert alert-danger py-2"><?= e($passwordError) ?></div><?php endif; ?>
                <?php if ($passwordSuccess): ?><div class="alert alert-success py-2"><?= e($passwordSuccess) ?></div><?php endif; ?>
                <form method="post" class="row g-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="form_type" value="password">
                    <div class="col-md-4">
                        <label class="form-label">Current password</label>
                        <input class="form-control" type="password" name="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">New password</label>
                        <input class="form-control" type="password" name="new_password" minlength="8" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Confirm password</label>
                        <input class="form-control" type="password" name="confirm_password" minlength="8" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-danger" type="submit">Update password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
</div>

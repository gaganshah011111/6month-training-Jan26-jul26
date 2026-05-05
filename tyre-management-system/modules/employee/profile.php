<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = Database::connection();
$error = '';
$success = '';

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contactNo = trim((string)($_POST['contact_no'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $profileImage = (string)($employee['profile_image'] ?? '');

        if ($contactNo !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $contactNo)) {
            $error = 'Please enter a valid phone number.';
        } elseif (strlen($address) > 255) {
            $error = 'Address is too long.';
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
                        $targetPath = $uploadDir . '/' . $newName;
                        if (!move_uploaded_file($tmpPath, $targetPath)) {
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
                $stmt = $pdo->prepare('UPDATE employees SET contact_no = :contact_no, address = :address, profile_image = :profile_image WHERE id = :id AND user_id = :user_id');
                $stmt->execute([
                    'contact_no' => $contactNo,
                    'address' => $address !== '' ? $address : null,
                    'profile_image' => $profileImage !== '' ? $profileImage : null,
                    'id' => $employeeId,
                    'user_id' => employee_session_user_id(),
                ]);
                $success = 'Profile updated successfully.';
            }
        }

        $employee = get_employee_record($pdo, employee_session_user_id()) ?? $employee;
    }
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
}
?>

<h4 class="mb-3">My Profile</h4>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<?php if (!empty($employee)): ?>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <img
                        src="<?= e(!empty($employee['profile_image']) ? $employee['profile_image'] : 'https://via.placeholder.com/140x140?text=Profile') ?>"
                        alt="Profile"
                        class="rounded-circle mb-3"
                        style="width: 140px; height: 140px; object-fit: cover;"
                    >
                    <h5 class="mb-1"><?= e($employee['full_name']) ?></h5>
                    <p class="text-muted mb-0"><?= e($employee['department']) ?></p>
                    <small class="text-muted">Code: <?= e($employee['employee_code']) ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Update Contact Details</strong></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input class="form-control" value="<?= e($employee['full_name']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input class="form-control" value="<?= e($employee['department']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input class="form-control" name="contact_no" maxlength="20" value="<?= e((string)($employee['contact_no'] ?? '')) ?>" placeholder="Enter phone number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profile Image</label>
                            <input class="form-control" name="profile_image" type="file" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3" maxlength="255" placeholder="Enter address"><?= e((string)($employee['address'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

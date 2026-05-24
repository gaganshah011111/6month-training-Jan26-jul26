<?php
declare(strict_types=1);

require_once __DIR__ . '/dispatch_service.php';
require_once __DIR__ . '/department_hierarchy.php';

const LOGISTICS_TABS = ['drivers', 'vehicles', 'transport'];

function logistics_valid_tab(string $tab): string
{
    return in_array($tab, LOGISTICS_TABS, true) ? $tab : 'drivers';
}

/** @return array{id: int, name: string} */
function logistics_resolve_transport(PDO $pdo, int $transportId): array
{
    if ($transportId < 1) {
        throw new InvalidArgumentException('Transport company is required.');
    }
    $st = $pdo->prepare(
        "SELECT * FROM dispatch_transport_companies WHERE id = :id AND status = 'Active' LIMIT 1"
    );
    $st->execute(['id' => $transportId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Selected transport company is not active.');
    }

    return ['id' => (int)$row['id'], 'name' => (string)$row['company_name']];
}

function logistics_sync_driver_vehicle(PDO $pdo, int $driverId, ?int $vehicleId): void
{
    $pdo->prepare('UPDATE dispatch_drivers SET vehicle_id = :vid WHERE id = :id')->execute([
        'vid' => $vehicleId,
        'id' => $driverId,
    ]);
    if ($vehicleId) {
        $v = logistics_get_vehicle($pdo, $vehicleId);
        if ($v) {
            $pdo->prepare(
                'UPDATE dispatch_drivers SET vehicle_no = :vn, transport_company_id = :tid, transport_company = :tn WHERE id = :id'
            )->execute([
                'vn' => $v['vehicle_number'],
                'tid' => $v['transport_company_id'],
                'tn' => $v['transport_company_name'] ?? null,
                'id' => $driverId,
            ]);
            $pdo->prepare('UPDATE dispatch_vehicles SET driver_id = :did WHERE id = :id')->execute([
                'did' => $driverId,
                'id' => $vehicleId,
            ]);
        }
    }
}

function logistics_sync_vehicle_driver(PDO $pdo, int $vehicleId, ?int $driverId): void
{
    $pdo->prepare('UPDATE dispatch_vehicles SET driver_id = :did WHERE id = :id')->execute([
        'did' => $driverId,
        'id' => $vehicleId,
    ]);
    if ($driverId) {
        $pdo->prepare('UPDATE dispatch_drivers SET vehicle_id = :vid WHERE id = :id')->execute([
            'vid' => $vehicleId,
            'id' => $driverId,
        ]);
        $v = logistics_get_vehicle($pdo, $vehicleId);
        if ($v) {
            $pdo->prepare(
                'UPDATE dispatch_drivers SET vehicle_no = :vn, transport_company_id = :tid WHERE id = :id'
            )->execute([
                'vn' => $v['vehicle_number'],
                'tid' => $v['transport_company_id'],
                'id' => $driverId,
            ]);
        }
    }
}

function logistics_save_transport(PDO $pdo, array $data, ?int $id = null): int
{
    return dispatch_save_transport($pdo, $data, $id);
}

function logistics_ensure_vehicle_columns(PDO $pdo): void
{
    if (!dh_table_exists($pdo, 'dispatch_vehicles')) {
        return;
    }
    if (!dh_column_exists($pdo, 'dispatch_vehicles', 'insurance_expiry')) {
        try {
            $pdo->exec('ALTER TABLE dispatch_vehicles ADD COLUMN insurance_expiry DATE NULL AFTER capacity');
        } catch (Throwable) {
        }
    }
    if (!dh_column_exists($pdo, 'dispatch_vehicles', 'rc_number')) {
        try {
            $pdo->exec('ALTER TABLE dispatch_vehicles ADD COLUMN rc_number VARCHAR(60) NULL AFTER insurance_expiry');
        } catch (Throwable) {
        }
    }
}

function logistics_parse_insurance_expiry(array $data): ?string
{
    $raw = trim((string)($data['insurance_expiry'] ?? ''));
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        throw new InvalidArgumentException('Insurance expiry must be a valid date (YYYY-MM-DD).');
    }

    return $raw;
}

function logistics_save_vehicle(PDO $pdo, array $data, ?int $id = null): int
{
    logistics_ensure_vehicle_columns($pdo);
    $number = strtoupper(trim((string)($data['vehicle_number'] ?? '')));
    if ($number === '') {
        throw new InvalidArgumentException('Vehicle number is required.');
    }
    $transportId = (int)($data['transport_company_id'] ?? 0);
    $transport = logistics_resolve_transport($pdo, $transportId);
    $driverId = (int)($data['driver_id'] ?? 0) ?: null;
    $params = [
        'n' => $number,
        'vt' => trim((string)($data['vehicle_type'] ?? '')) ?: null,
        'cap' => trim((string)($data['capacity'] ?? '')) ?: null,
        'ins' => logistics_parse_insurance_expiry($data),
        'rc' => trim((string)($data['rc_number'] ?? '')) ?: null,
        'did' => $driverId,
        'tid' => $transport['id'],
        's' => in_array(($data['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
    ];

    if ($id) {
        $pdo->prepare(
            'UPDATE dispatch_vehicles SET vehicle_number=:n, vehicle_type=:vt, capacity=:cap,
             insurance_expiry=:ins, rc_number=:rc, driver_id=:did, transport_company_id=:tid, status=:s WHERE id=:id'
        )->execute($params + ['id' => $id]);
        logistics_sync_vehicle_driver($pdo, $id, $driverId);

        return $id;
    }

    $pdo->prepare(
        'INSERT INTO dispatch_vehicles (vehicle_number, vehicle_type, capacity, insurance_expiry, rc_number, driver_id, transport_company_id, status)
         VALUES (:n,:vt,:cap,:ins,:rc,:did,:tid,:s)'
    )->execute($params);
    $newId = (int)$pdo->lastInsertId();
    logistics_sync_vehicle_driver($pdo, $newId, $driverId);

    return $newId;
}

function logistics_save_driver(PDO $pdo, array $data, ?int $id = null): int
{
    $name = trim((string)($data['driver_name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Driver name is required.');
    }
    $vehicleId = (int)($data['vehicle_id'] ?? 0) ?: null;
    $transportId = (int)($data['transport_company_id'] ?? 0);

    if ($vehicleId) {
        $v = logistics_get_vehicle($pdo, $vehicleId);
        if (!$v) {
            throw new InvalidArgumentException('Selected vehicle not found.');
        }
        if (($v['status'] ?? '') !== 'Active' && ($data['status'] ?? 'Active') === 'Active') {
            throw new InvalidArgumentException('Cannot assign inactive vehicle to active driver.');
        }
        $transportId = (int)($v['transport_company_id'] ?? 0);
    }
    if ($transportId < 1) {
        throw new InvalidArgumentException('Assign a vehicle or select transport company.');
    }
    $transport = logistics_resolve_transport($pdo, $transportId);

    $vehicleNo = null;
    if ($vehicleId) {
        $vehicleNo = (string)(logistics_get_vehicle($pdo, $vehicleId)['vehicle_number'] ?? '');
    }

    $params = [
        'n' => $name,
        'p' => trim((string)($data['phone'] ?? '')) ?: null,
        'l' => trim((string)($data['license_number'] ?? '')) ?: null,
        'vid' => $vehicleId,
        'v' => $vehicleNo,
        'tid' => $transport['id'],
        't' => $transport['name'],
        'a' => trim((string)($data['address'] ?? '')) ?: null,
        's' => in_array(($data['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $data['status'] : 'Active',
    ];

    if ($id) {
        $pdo->prepare(
            'UPDATE dispatch_drivers SET driver_name=:n, phone=:p, license_number=:l, vehicle_id=:vid, vehicle_no=:v,
             transport_company_id=:tid, transport_company=:t, address=:a, status=:s WHERE id=:id'
        )->execute($params + ['id' => $id]);
        logistics_sync_driver_vehicle($pdo, $id, $vehicleId);

        return $id;
    }

    $pdo->prepare(
        'INSERT INTO dispatch_drivers (driver_name, phone, license_number, vehicle_id, vehicle_no, transport_company_id, transport_company, address, status)
         VALUES (:n,:p,:l,:vid,:v,:tid,:t,:a,:s)'
    )->execute($params);
    $newId = (int)$pdo->lastInsertId();
    logistics_sync_driver_vehicle($pdo, $newId, $vehicleId);

    return $newId;
}

function logistics_list_transport(PDO $pdo): array
{
    return dispatch_list_transport($pdo);
}

function logistics_all_transport(PDO $pdo, string $search = '', string $statusFilter = ''): array
{
    return dispatch_all_transport($pdo, $search, $statusFilter);
}

function logistics_get_transport(PDO $pdo, int $id): ?array
{
    return dispatch_get_transport($pdo, $id);
}

function logistics_all_vehicles(PDO $pdo, string $search = '', string $statusFilter = ''): array
{
    $sql = 'SELECT v.*, d.driver_name, t.company_name AS transport_company_name
            FROM dispatch_vehicles v
            LEFT JOIN dispatch_drivers d ON d.id = v.driver_id
            LEFT JOIN dispatch_transport_companies t ON t.id = v.transport_company_id
            WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (v.vehicle_number LIKE :q OR v.vehicle_type LIKE :q OR d.driver_name LIKE :q OR t.company_name LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }
    if ($statusFilter === 'Active' || $statusFilter === 'Inactive') {
        $sql .= ' AND v.status = :st';
        $params['st'] = $statusFilter;
    }
    $sql .= ' ORDER BY v.vehicle_number ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function logistics_list_vehicles(PDO $pdo): array
{
    return $pdo->query(
        "SELECT v.id, v.vehicle_number, v.vehicle_type, v.capacity, v.driver_id, v.transport_company_id, v.status,
                d.driver_name, t.company_name AS transport_company_name
         FROM dispatch_vehicles v
         LEFT JOIN dispatch_drivers d ON d.id = v.driver_id
         LEFT JOIN dispatch_transport_companies t ON t.id = v.transport_company_id
         WHERE v.status = 'Active' ORDER BY v.vehicle_number ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function logistics_get_vehicle(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT v.*, d.driver_name, t.company_name AS transport_company_name
         FROM dispatch_vehicles v
         LEFT JOIN dispatch_drivers d ON d.id = v.driver_id
         LEFT JOIN dispatch_transport_companies t ON t.id = v.transport_company_id
         WHERE v.id = :id LIMIT 1'
    );
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function logistics_all_drivers(PDO $pdo, string $search = '', string $statusFilter = ''): array
{
    $sql = 'SELECT d.*, v.vehicle_number AS assigned_vehicle, v.id AS assigned_vehicle_id,
                   t.company_name AS transport_company_name
            FROM dispatch_drivers d
            LEFT JOIN dispatch_vehicles v ON v.id = d.vehicle_id
            LEFT JOIN dispatch_transport_companies t ON t.id = COALESCE(v.transport_company_id, d.transport_company_id)
            WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (d.driver_name LIKE :q OR d.phone LIKE :q OR d.license_number LIKE :q
            OR v.vehicle_number LIKE :q OR t.company_name LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }
    if ($statusFilter === 'Active' || $statusFilter === 'Inactive') {
        $sql .= ' AND d.status = :st';
        $params['st'] = $statusFilter;
    }
    $sql .= ' ORDER BY d.driver_name ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function logistics_list_drivers(PDO $pdo): array
{
    return $pdo->query(
        "SELECT d.id, d.driver_name, d.phone, d.license_number, d.vehicle_id, d.vehicle_no,
                d.transport_company_id, COALESCE(t.company_name, d.transport_company) AS transport_company,
                v.vehicle_number AS vehicle_number_linked, v.vehicle_type, v.capacity
         FROM dispatch_drivers d
         LEFT JOIN dispatch_vehicles v ON v.id = d.vehicle_id
         LEFT JOIN dispatch_transport_companies t ON t.id = COALESCE(v.transport_company_id, d.transport_company_id)
         WHERE d.status = 'Active' ORDER BY d.driver_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function logistics_get_driver(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM dispatch_drivers WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function logistics_vehicles_for_driver(PDO $pdo, int $driverId): array
{
    if ($driverId < 1) {
        return [];
    }
    $rows = [];
    $seen = [];
    $st = $pdo->prepare(
        "SELECT v.id, v.vehicle_number, v.vehicle_type, v.capacity, v.driver_id, v.transport_company_id,
                v.status, d.driver_name, t.company_name AS transport_company_name
         FROM dispatch_vehicles v
         LEFT JOIN dispatch_drivers d ON d.id = v.driver_id
         LEFT JOIN dispatch_transport_companies t ON t.id = v.transport_company_id
         WHERE v.status = 'Active' AND (v.driver_id = :did OR v.id = (SELECT vehicle_id FROM dispatch_drivers WHERE id = :did2 LIMIT 1))
         ORDER BY v.vehicle_number ASC"
    );
    $st->execute(['did' => $driverId, 'did2' => $driverId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $v) {
        $id = (int)$v['id'];
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $rows[] = $v;
    }

    return $rows;
}

/** Active logistics records for dispatch form (JSON-friendly). */
function logistics_dispatch_bundle(PDO $pdo): array
{
    $allVehicles = logistics_list_vehicles($pdo);
    $vehiclesByDriver = [];
    foreach ($allVehicles as $v) {
        $did = (int)($v['driver_id'] ?? 0);
        if ($did > 0) {
            $vehiclesByDriver[$did][] = (int)$v['id'];
        }
    }

    $drivers = [];
    foreach (logistics_list_drivers($pdo) as $d) {
        $driverId = (int)$d['id'];
        $vid = (int)($d['vehicle_id'] ?? 0);
        $vehicleIds = $vehiclesByDriver[$driverId] ?? [];
        if ($vid > 0 && !in_array($vid, $vehicleIds, true)) {
            $vehicleIds[] = $vid;
        }
        sort($vehicleIds);
        $drivers[] = [
            'id' => $driverId,
            'name' => (string)$d['driver_name'],
            'vehicle_id' => $vid,
            'vehicle_ids' => $vehicleIds,
            'vehicle_no' => (string)($d['vehicle_number_linked'] ?? $d['vehicle_no'] ?? ''),
            'transport_id' => (int)($d['transport_company_id'] ?? 0),
            'transport' => (string)($d['transport_company'] ?? ''),
        ];
    }
    $vehicles = [];
    foreach ($allVehicles as $v) {
        $vehicles[] = [
            'id' => (int)$v['id'],
            'number' => (string)$v['vehicle_number'],
            'type' => (string)($v['vehicle_type'] ?? ''),
            'capacity' => (string)($v['capacity'] ?? ''),
            'driver_id' => (int)($v['driver_id'] ?? 0),
            'driver_name' => (string)($v['driver_name'] ?? ''),
            'transport_id' => (int)($v['transport_company_id'] ?? 0),
            'transport' => (string)($v['transport_company_name'] ?? ''),
        ];
    }

    return ['drivers' => $drivers, 'vehicles' => $vehicles];
}

/**
 * Resolve driver + vehicle + transport for dispatch save.
 * @return array{driver: array, transport: array, vehicle_no: string, vehicle_id: ?int}
 */
function logistics_resolve_dispatch(PDO $pdo, array $data): array
{
    $driverId = (int)($data['driver_id'] ?? 0);
    $vehicleId = (int)($data['vehicle_id'] ?? 0);

    if ($vehicleId > 0 && $driverId < 1) {
        $v = logistics_get_vehicle($pdo, $vehicleId);
        if (!$v || ($v['status'] ?? '') !== 'Active') {
            throw new InvalidArgumentException('Selected vehicle is not active.');
        }
        $driverId = (int)($v['driver_id'] ?? 0);
        if ($driverId < 1) {
            throw new InvalidArgumentException('Selected vehicle has no assigned driver.');
        }
    }

    $driver = dispatch_resolve_driver($pdo, ['driver_id' => $driverId]);
    $resolvedDriverId = (int)($driver['id'] ?? 0);

    if ($vehicleId < 1 && $resolvedDriverId > 0) {
        $dRow = logistics_get_driver($pdo, $resolvedDriverId);
        $vehicleId = (int)($dRow['vehicle_id'] ?? 0);
    }
    if ($vehicleId > 0 && $resolvedDriverId > 0) {
        $allowedIds = array_map(static fn(array $row): int => (int)$row['id'], logistics_vehicles_for_driver($pdo, $resolvedDriverId));
        if ($allowedIds !== [] && !in_array($vehicleId, $allowedIds, true)) {
            throw new InvalidArgumentException('Selected vehicle is not assigned to this driver.');
        }
    }
    if ($vehicleId > 0) {
        $v = logistics_get_vehicle($pdo, $vehicleId);
        if ($v) {
            $driver['vehicle_no'] = (string)$v['vehicle_number'];
            $driver['transport_company_id'] = (int)($v['transport_company_id'] ?? $driver['transport_company_id']);
            $driver['transport_company'] = (string)($v['transport_company_name'] ?? $driver['transport_company']);
        }
    }

    $transport = dispatch_resolve_transport($pdo, [
        'transport_company_id' => (int)($data['transport_company_id'] ?? $driver['transport_company_id'] ?? 0),
    ], $driver);

    return [
        'driver' => $driver,
        'transport' => $transport,
        'vehicle_no' => (string)($driver['vehicle_no'] ?? ''),
        'vehicle_id' => $vehicleId > 0 ? $vehicleId : null,
    ];
}

function logistics_disable(PDO $pdo, string $entity, int $id): void
{
    $table = match ($entity) {
        'driver' => 'dispatch_drivers',
        'vehicle' => 'dispatch_vehicles',
        'transport' => 'dispatch_transport_companies',
        default => throw new InvalidArgumentException('Invalid entity.'),
    };
    $pdo->prepare("UPDATE {$table} SET status = 'Inactive' WHERE id = :id")->execute(['id' => $id]);
}

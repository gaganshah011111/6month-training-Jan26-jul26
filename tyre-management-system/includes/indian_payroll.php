<?php
declare(strict_types=1);

/**
 * Indian industrial payroll: configurable defaults + split gross into statutory-style components.
 */

function payroll_settings_defaults(): array
{
    return [
        'id' => 1,
        'basic_pct_of_gross' => 50.0,
        'da_pct_of_basic' => 0.0,
        'da_enabled' => 0,
        'hra_pct_non_metro' => 40.0,
        'hra_pct_metro' => 50.0,
        'medical_enabled' => 1,
        'medical_mode' => 'fixed',
        'medical_fixed' => 0.0,
        'medical_pct_of_basic' => 0.0,
        'travel_enabled' => 0,
        'travel_mode' => 'fixed',
        'travel_fixed' => 0.0,
        'travel_pct_of_basic' => 0.0,
        'gratuity_pct_of_basic' => 4.81,
        'pf_employee_pct' => 12.0,
        'pf_employer_pct' => 12.0,
        'esi_employee_pct' => 0.75,
        'esi_employer_pct' => 3.25,
        'esi_gross_limit' => 21000.0,
        'working_days_default' => 26.0,
        'shift_hours_default' => 8.0,
        'ot_multiplier' => 1.0,
        'late_deduction_pct_of_daily' => 10.0,
    ];
}

function employee_payroll_auto_indian(array $employee): bool
{
    if (!array_key_exists('payroll_auto_indian', $employee)) {
        return true;
    }

    return (int)$employee['payroll_auto_indian'] === 1;
}

function payroll_settings_fetch(PDO $pdo): array
{
    $defaults = payroll_settings_defaults();
    try {
        $st = $pdo->query('SELECT * FROM payroll_settings WHERE id = 1 LIMIT 1');
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
    } catch (Throwable) {
        $row = false;
    }
    if (!$row) {
        return $defaults;
    }

    foreach ($defaults as $k => $v) {
        if (!array_key_exists($k, $row)) {
            $row[$k] = $v;
        }
    }

    return $row;
}

/**
 * Medical / travel allowance: percent of basic when % > 0, otherwise fixed ₹ amount.
 * Handles settings saved as "percent" with 0% but a positive fixed amount.
 */
function payroll_allowance_amount(bool $enabled, string $mode, float $basic, float $fixed, float $pctOfBasic): float
{
    if (!$enabled) {
        return 0.0;
    }
    if ($mode === 'percent' && $pctOfBasic > 0) {
        return round($basic * $pctOfBasic / 100, 2);
    }

    return round(max(0.0, $fixed), 2);
}

/** Normalized payroll settings for JSON / client-side split (matches indian_split_monthly_gross). */
function payroll_settings_for_client(array $ps): array
{
    return [
        'basic_pct_of_gross' => (float)($ps['basic_pct_of_gross'] ?? 50),
        'da_pct_of_basic' => (float)($ps['da_pct_of_basic'] ?? 0),
        'da_enabled' => !empty($ps['da_enabled']) ? 1 : 0,
        'hra_pct_non_metro' => (float)($ps['hra_pct_non_metro'] ?? 40),
        'hra_pct_metro' => (float)($ps['hra_pct_metro'] ?? 50),
        'medical_enabled' => !empty($ps['medical_enabled']) ? 1 : 0,
        'medical_mode' => (($ps['medical_mode'] ?? 'fixed') === 'percent') ? 'percent' : 'fixed',
        'medical_fixed' => (float)($ps['medical_fixed'] ?? 0),
        'medical_pct_of_basic' => (float)($ps['medical_pct_of_basic'] ?? 0),
        'travel_enabled' => !empty($ps['travel_enabled']) ? 1 : 0,
        'travel_mode' => (($ps['travel_mode'] ?? 'fixed') === 'percent') ? 'percent' : 'fixed',
        'travel_fixed' => (float)($ps['travel_fixed'] ?? 0),
        'travel_pct_of_basic' => (float)($ps['travel_pct_of_basic'] ?? 0),
        'gratuity_pct_of_basic' => (float)($ps['gratuity_pct_of_basic'] ?? 4.81),
        'pf_employee_pct' => (float)($ps['pf_employee_pct'] ?? 12),
        'esi_employee_pct' => (float)($ps['esi_employee_pct'] ?? 0.75),
        'esi_gross_limit' => (float)($ps['esi_gross_limit'] ?? 21000),
        'working_days_default' => max(1.0, (float)($ps['working_days_default'] ?? 26)),
        'shift_hours_default' => max(0.5, (float)($ps['shift_hours_default'] ?? 8)),
        'ot_multiplier' => max(0.5, (float)($ps['ot_multiplier'] ?? 1)),
    ];
}

/** @param array<string, scalar|null> $input */
function payroll_settings_save(PDO $pdo, array $input): void
{
    $d = payroll_settings_defaults();
    $row = [
        'basic_pct_of_gross' => (float)($input['basic_pct_of_gross'] ?? $d['basic_pct_of_gross']),
        'da_pct_of_basic' => (float)($input['da_pct_of_basic'] ?? $d['da_pct_of_basic']),
        'da_enabled' => !empty($input['da_enabled']) ? 1 : 0,
        'hra_pct_non_metro' => (float)($input['hra_pct_non_metro'] ?? $d['hra_pct_non_metro']),
        'hra_pct_metro' => (float)($input['hra_pct_metro'] ?? $d['hra_pct_metro']),
        'medical_enabled' => !empty($input['medical_enabled']) ? 1 : 0,
        'medical_mode' => ($input['medical_mode'] ?? $d['medical_mode']) === 'percent' ? 'percent' : 'fixed',
        'medical_fixed' => (float)($input['medical_fixed'] ?? $d['medical_fixed']),
        'medical_pct_of_basic' => (float)($input['medical_pct_of_basic'] ?? $d['medical_pct_of_basic']),
        'travel_enabled' => !empty($input['travel_enabled']) ? 1 : 0,
        'travel_mode' => ($input['travel_mode'] ?? $d['travel_mode']) === 'percent' ? 'percent' : 'fixed',
        'travel_fixed' => (float)($input['travel_fixed'] ?? $d['travel_fixed']),
        'travel_pct_of_basic' => (float)($input['travel_pct_of_basic'] ?? $d['travel_pct_of_basic']),
        'gratuity_pct_of_basic' => (float)($input['gratuity_pct_of_basic'] ?? $d['gratuity_pct_of_basic']),
        'pf_employee_pct' => (float)($input['pf_employee_pct'] ?? $d['pf_employee_pct']),
        'pf_employer_pct' => (float)($input['pf_employer_pct'] ?? $d['pf_employer_pct']),
        'esi_employee_pct' => (float)($input['esi_employee_pct'] ?? $d['esi_employee_pct']),
        'esi_employer_pct' => (float)($input['esi_employer_pct'] ?? $d['esi_employer_pct']),
        'esi_gross_limit' => (float)($input['esi_gross_limit'] ?? $d['esi_gross_limit']),
        'working_days_default' => max(1.0, (float)($input['working_days_default'] ?? $d['working_days_default'])),
        'shift_hours_default' => max(0.5, (float)($input['shift_hours_default'] ?? $d['shift_hours_default'])),
        'ot_multiplier' => max(0.5, (float)($input['ot_multiplier'] ?? $d['ot_multiplier'])),
        'late_deduction_pct_of_daily' => max(0.0, (float)($input['late_deduction_pct_of_daily'] ?? $d['late_deduction_pct_of_daily'])),
    ];

    $sql = 'INSERT INTO payroll_settings (id, basic_pct_of_gross, da_pct_of_basic, da_enabled, hra_pct_non_metro, hra_pct_metro, medical_enabled, medical_mode, medical_fixed, medical_pct_of_basic, travel_enabled, travel_mode, travel_fixed, travel_pct_of_basic, gratuity_pct_of_basic, pf_employee_pct, pf_employer_pct, esi_employee_pct, esi_employer_pct, esi_gross_limit, working_days_default, shift_hours_default, ot_multiplier, late_deduction_pct_of_daily)
        VALUES (1, :bpg, :dapb, :dae, :hranm, :hram, :me, :mm, :mf, :mpb, :te, :tm, :tf, :tpb, :gpb, :pfee, :pfer, :esiee, :esier, :esilim, :wd, :sh, :otm, :late)
        ON DUPLICATE KEY UPDATE basic_pct_of_gross=VALUES(basic_pct_of_gross), da_pct_of_basic=VALUES(da_pct_of_basic), da_enabled=VALUES(da_enabled), hra_pct_non_metro=VALUES(hra_pct_non_metro), hra_pct_metro=VALUES(hra_pct_metro), medical_enabled=VALUES(medical_enabled), medical_mode=VALUES(medical_mode), medical_fixed=VALUES(medical_fixed), medical_pct_of_basic=VALUES(medical_pct_of_basic), travel_enabled=VALUES(travel_enabled), travel_mode=VALUES(travel_mode), travel_fixed=VALUES(travel_fixed), travel_pct_of_basic=VALUES(travel_pct_of_basic), gratuity_pct_of_basic=VALUES(gratuity_pct_of_basic), pf_employee_pct=VALUES(pf_employee_pct), pf_employer_pct=VALUES(pf_employer_pct), esi_employee_pct=VALUES(esi_employee_pct), esi_employer_pct=VALUES(esi_employer_pct), esi_gross_limit=VALUES(esi_gross_limit), working_days_default=VALUES(working_days_default), shift_hours_default=VALUES(shift_hours_default), ot_multiplier=VALUES(ot_multiplier), late_deduction_pct_of_daily=VALUES(late_deduction_pct_of_daily)';
    $st = $pdo->prepare($sql);
    $st->execute([
        'bpg' => $row['basic_pct_of_gross'],
        'dapb' => $row['da_pct_of_basic'],
        'dae' => $row['da_enabled'],
        'hranm' => $row['hra_pct_non_metro'],
        'hram' => $row['hra_pct_metro'],
        'me' => $row['medical_enabled'],
        'mm' => $row['medical_mode'],
        'mf' => $row['medical_fixed'],
        'mpb' => $row['medical_pct_of_basic'],
        'te' => $row['travel_enabled'],
        'tm' => $row['travel_mode'],
        'tf' => $row['travel_fixed'],
        'tpb' => $row['travel_pct_of_basic'],
        'gpb' => $row['gratuity_pct_of_basic'],
        'pfee' => $row['pf_employee_pct'],
        'pfer' => $row['pf_employer_pct'],
        'esiee' => $row['esi_employee_pct'],
        'esier' => $row['esi_employer_pct'],
        'esilim' => $row['esi_gross_limit'],
        'wd' => $row['working_days_default'],
        'sh' => $row['shift_hours_default'],
        'otm' => $row['ot_multiplier'],
        'late' => $row['late_deduction_pct_of_daily'],
    ]);

    payroll_settings_normalize_modes($pdo);
}

/** Align allowance mode with stored values (percent 0 + fixed ₹ → use fixed). */
function payroll_settings_normalize_modes(PDO $pdo): void
{
    try {
        $st = $pdo->query('SELECT medical_mode, medical_pct_of_basic, medical_fixed, travel_mode, travel_pct_of_basic, travel_fixed FROM payroll_settings WHERE id = 1 LIMIT 1');
        $ps = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
    } catch (Throwable) {
        return;
    }
    if (!$ps) {
        return;
    }
    $medMode = (string)($ps['medical_mode'] ?? 'fixed');
    $trMode = (string)($ps['travel_mode'] ?? 'fixed');
    $medPct = (float)($ps['medical_pct_of_basic'] ?? 0);
    $trPct = (float)($ps['travel_pct_of_basic'] ?? 0);
    $medFixed = (float)($ps['medical_fixed'] ?? 0);
    $trFixed = (float)($ps['travel_fixed'] ?? 0);
    $newMed = $medMode;
    $newTr = $trMode;
    if ($medMode === 'percent' && $medPct <= 0 && $medFixed > 0) {
        $newMed = 'fixed';
    }
    if ($trMode === 'percent' && $trPct <= 0 && $trFixed > 0) {
        $newTr = 'fixed';
    }
    if ($newMed === $medMode && $newTr === $trMode) {
        return;
    }
    $st = $pdo->prepare('UPDATE payroll_settings SET medical_mode = :mm, travel_mode = :tm WHERE id = 1');
    $st->execute(['mm' => $newMed, 'tm' => $newTr]);
}

/**
 * Split fixed monthly gross G (take-home package excl. employer-only gratuity) into components.
 * Gratuity is computed on Basic but not included in G.
 *
 * @return array{
 *   basic:float,da:float,hra:float,hra_pct:float,medical:float,travel:float,special:float,
 *   gratuity_monthly:float,daily_wage:float,hourly_rate:float,ot_rate:float
 * }
 */
function indian_split_monthly_gross(float $G, bool $metro, bool $isWorker, array $s): array
{
    if ($G <= 0) {
        return [
            'basic' => 0.0,
            'da' => 0.0,
            'hra' => 0.0,
            'hra_pct' => 0.0,
            'medical' => 0.0,
            'travel' => 0.0,
            'special' => 0.0,
            'gratuity_monthly' => 0.0,
            'daily_wage' => 0.0,
            'hourly_rate' => 0.0,
            'ot_rate' => 0.0,
        ];
    }

    $basicPct = (float)($s['basic_pct_of_gross'] ?? 50);
    $basic = round($G * $basicPct / 100, 2);

    $daEnabled = !empty($s['da_enabled']);
    $daPct = (float)($s['da_pct_of_basic'] ?? 0);
    $da = ($daEnabled && $daPct > 0) ? round($basic * $daPct / 100, 2) : 0.0;

    $hraPct = 0.0;
    if (!$isWorker) {
        $hraPct = $metro
            ? (float)($s['hra_pct_metro'] ?? 50)
            : (float)($s['hra_pct_non_metro'] ?? 40);
    }
    $hra = $isWorker ? 0.0 : round($basic * $hraPct / 100, 2);

    $medical = payroll_allowance_amount(
        !empty($s['medical_enabled']),
        (string)($s['medical_mode'] ?? 'fixed'),
        $basic,
        (float)($s['medical_fixed'] ?? 0),
        (float)($s['medical_pct_of_basic'] ?? 0)
    );

    $travel = payroll_allowance_amount(
        !empty($s['travel_enabled']),
        (string)($s['travel_mode'] ?? 'fixed'),
        $basic,
        (float)($s['travel_fixed'] ?? 0),
        (float)($s['travel_pct_of_basic'] ?? 0)
    );

    $fixedSum = $basic + $da + $hra + $medical + $travel;
    $special = round(max(0, $G - $fixedSum), 2);

    $gPct = (float)($s['gratuity_pct_of_basic'] ?? 4.81);
    $gratuityMonthly = round($basic * $gPct / 100, 2);

    $wd = max(1.0, (float)($s['working_days_default'] ?? 26));
    $sh = max(0.5, (float)($s['shift_hours_default'] ?? 8));
    $dailyWage = round($G / $wd, 2);
    $hourlyRate = round($dailyWage / $sh, 2);
    $otMult = max(0.5, (float)($s['ot_multiplier'] ?? 1));
    $otRate = round($hourlyRate * $otMult, 2);

    return [
        'basic' => $basic,
        'da' => $da,
        'hra' => $hra,
        'hra_pct' => $hraPct,
        'medical' => $medical,
        'travel' => $travel,
        'special' => $special,
        'gratuity_monthly' => $gratuityMonthly,
        'daily_wage' => $dailyWage,
        'hourly_rate' => $hourlyRate,
        'ot_rate' => $otRate,
    ];
}

function indian_apply_components_to_employee(PDO $pdo, int $employeeId): void
{
    $st = $pdo->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
    $st->execute(['id' => $employeeId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    if (!employee_payroll_auto_indian($row)) {
        return;
    }

    $G = (float)($row['gross_salary'] ?? 0);
    if ($G <= 0) {
        return;
    }

    $settings = payroll_settings_fetch($pdo);
    $metro = normalize_bool($row['metro'] ?? 0);
    $isWorker = ((string)($row['employee_type'] ?? 'Staff')) === 'Worker';

    $split = indian_split_monthly_gross($G, $metro, $isWorker, $settings);

    $esiLimit = (float)($settings['esi_gross_limit'] ?? 21000);
    $esiApplicable = normalize_bool($row['esi_applicable'] ?? 1) && $G <= $esiLimit;

    $pfPct = (float)($settings['pf_employee_pct'] ?? 12);
    $esiPct = (float)($settings['esi_employee_pct'] ?? 0.75);

    $up = $pdo->prepare('UPDATE employees SET
        basic_salary = :b,
        dearness_allowance = :da,
        hra_percentage = :hrp,
        hra_amount = :hra,
        medical_allowance = :ma,
        travel_allowance = :ta,
        special_allowance = :sp,
        other_allowances = 0,
        gratuity_monthly = :gr,
        daily_wage = :dw,
        hourly_rate = :hr,
        overtime_rate = :otr,
        pf_percentage = :pfp,
        esi_percentage = :esip,
        esi_salary_limit = :esilim,
        esi_applicable = :esia
        WHERE id = :id');

    $up->execute([
        'b' => $split['basic'],
        'da' => $split['da'],
        'hrp' => $split['hra_pct'],
        'hra' => $split['hra'],
        'ma' => $split['medical'],
        'ta' => $split['travel'],
        'sp' => $split['special'],
        'gr' => $split['gratuity_monthly'],
        'dw' => $split['daily_wage'],
        'hr' => $split['hourly_rate'],
        'otr' => $split['ot_rate'],
        'pfp' => $pfPct,
        'esip' => $esiPct,
        'esilim' => $esiLimit,
        'esia' => $esiApplicable ? 1 : 0,
        'id' => $employeeId,
    ]);
}

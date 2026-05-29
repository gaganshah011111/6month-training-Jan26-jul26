<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_transactions.php';

$pdo = Database::connection();
acc_tx_history_handle_export($pdo);

$bundle = acc_tx_history_bundle($pdo, $_GET);
$filters = $bundle['filters'];
$rows = $bundle['rows'];
$kpis = $bundle['kpis'];
$analytics = $bundle['analytics'];

$filterQs = array_filter([
    'page' => 'accounts/transactions-history',
    'from' => $filters['from'],
    'to' => $filters['to'],
    'tx_type' => $filters['tx_type'] ?: null,
    'status' => $filters['status'] ?: null,
    'party' => $filters['party'] ?: null,
    'payment_mode' => $filters['payment_mode'] ?: null,
    'amount_min' => $filters['amount_min'] !== null ? (string)$filters['amount_min'] : null,
    'amount_max' => $filters['amount_max'] !== null ? (string)$filters['amount_max'] : null,
    'search' => $filters['search'] ?: null,
]);
$exportBase = 'index.php?' . http_build_query($filterQs);
$resetUrl = route_url('accounts/transactions-history');

$chartPayload = [
    'by_type' => $analytics['by_type'],
    'inflow' => round((float)$analytics['inflow'], 2),
    'outflow' => round((float)$analytics['outflow'], 2),
    'monthly' => $analytics['monthly'],
];
?>

<div class="accounts-page acc-tx-page module-shell" id="accTxHistoryRoot">
    <header class="acc-tx__head">
        <div>
            <h1 class="acc-tx__title">Transactions History</h1>
            <p class="acc-tx__sub">Master financial audit log · <?= e($filters['from']) ?> to <?= e($filters['to']) ?></p>
        </div>
    </header>

    <section class="acc-tx__kpis" aria-label="Transaction summary">
        <div class="acc-tx-kpi"><span>Total Transactions</span><strong><?= (int)$kpis['total'] ?></strong></div>
        <div class="acc-tx-kpi acc-tx-kpi--in"><span>Total Inflow</span><strong><?= e(sales_format_money((float)$kpis['inflow'])) ?></strong></div>
        <div class="acc-tx-kpi acc-tx-kpi--out"><span>Total Outflow</span><strong><?= e(sales_format_money((float)$kpis['outflow'])) ?></strong></div>
        <div class="acc-tx-kpi"><span>Largest Transaction</span><strong><?= e(sales_format_money((float)$kpis['largest'])) ?></strong></div>
        <div class="acc-tx-kpi"><span>Today's Transactions</span><strong><?= (int)$kpis['today'] ?></strong></div>
        <div class="acc-tx-kpi acc-tx-kpi--pending"><span>Pending Transactions</span><strong><?= (int)$kpis['pending'] ?></strong></div>
    </section>

    <form method="get" class="acc-tx__filters" id="accTxFilterForm">
        <input type="hidden" name="page" value="accounts/transactions-history">
        <div class="acc-tx__field">
            <label for="acc_tx_from">From</label>
            <input type="date" name="from" id="acc_tx_from" class="form-control form-control-sm" value="<?= e($filters['from']) ?>" required>
        </div>
        <div class="acc-tx__field">
            <label for="acc_tx_to">To</label>
            <input type="date" name="to" id="acc_tx_to" class="form-control form-control-sm" value="<?= e($filters['to']) ?>" required>
        </div>
        <div class="acc-tx__field">
            <label for="acc_tx_type">Transaction Type</label>
            <select name="tx_type" id="acc_tx_type" class="form-select form-select-sm">
                <?php foreach (ACC_TX_TYPES as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $filters['tx_type'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="acc-tx__field">
            <label for="acc_tx_status">Status</label>
            <select name="status" id="acc_tx_status" class="form-select form-select-sm">
                <?php foreach (ACC_TX_STATUSES as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="acc-tx__field">
            <label for="acc_tx_party">Party</label>
            <input type="text" name="party" id="acc_tx_party" class="form-control form-control-sm" value="<?= e($filters['party']) ?>" placeholder="Customer, supplier…">
        </div>
        <div class="acc-tx__field">
            <label for="acc_tx_mode">Payment Mode</label>
            <select name="payment_mode" id="acc_tx_mode" class="form-select form-select-sm">
                <?php foreach (ACC_TX_PAYMENT_MODES as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $filters['payment_mode'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="acc-tx__field acc-tx__field--range">
            <label>Amount Range</label>
            <div class="acc-tx__range">
                <input type="number" name="amount_min" class="form-control form-control-sm" step="0.01" min="0" placeholder="Min" value="<?= $filters['amount_min'] !== null ? e((string)$filters['amount_min']) : '' ?>">
                <span>—</span>
                <input type="number" name="amount_max" class="form-control form-control-sm" step="0.01" min="0" placeholder="Max" value="<?= $filters['amount_max'] !== null ? e((string)$filters['amount_max']) : '' ?>">
            </div>
        </div>
        <div class="acc-tx__field acc-tx__field--search">
            <label for="acc_tx_search">Search</label>
            <input type="search" name="search" id="acc_tx_search" class="form-control form-control-sm" value="<?= e($filters['search']) ?>" placeholder="ID, reference, party…">
        </div>
        <div class="acc-tx__actions">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Apply</button>
            <a href="<?= e($resetUrl) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="accTxPrint"><i class="bi bi-printer"></i> Print</button>
            <a href="<?= e($exportBase . '&export=pdf') ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
            <a href="<?= e($exportBase . '&export=excel') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
        </div>
    </form>

    <section class="acc-tx__table-wrap">
        <div class="acc-tx__table-scroll">
            <table class="table table-sm acc-tx-table mb-0" id="acc-transactions-table">
                <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Date &amp; Time</th>
                    <th>Type</th>
                    <th>Direction</th>
                    <th>Party</th>
                    <th>Reference</th>
                    <th class="text-end">Amount</th>
                    <th>Mode</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">No transactions match the selected filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $dir = $r['direction_meta'];
                        $st = $r['status_meta'];
                        $src = $r['source_link'];
                        $txPrintQs = $filterQs + ['export' => 'print', 'tx' => $r['tx_code']];
                        $txPdfQs = $filterQs + ['export' => 'pdf', 'tx' => $r['tx_code']];
                        ?>
                        <tr class="acc-tx-row <?= e((string)$r['row_accent']) ?>"
                            data-tx="<?= e(json_encode($r, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS)) ?>">
                            <td class="acc-tx-code"><?= e((string)$r['tx_code']) ?></td>
                            <td><?= e((string)$r['tx_datetime']) ?></td>
                            <td><span class="acc-tx-type"><?= e((string)$r['tx_type']) ?></span></td>
                            <td>
                                <span class="acc-tx-dir <?= e((string)$dir['cls']) ?>">
                                    <i class="bi <?= e((string)$dir['icon']) ?>"></i>
                                    <?= e((string)$dir['label']) ?>
                                </span>
                            </td>
                            <td><?= e((string)$r['party']) ?></td>
                            <td class="acc-tx-ref"><?= e((string)$r['reference_no']) ?></td>
                            <td class="text-end acc-tx-amt"><?= e((string)$r['amount_fmt']) ?></td>
                            <td><?= e((string)$r['payment_mode']) ?></td>
                            <td><?= e((string)$r['created_by']) ?></td>
                            <td><span class="acc-tx-status <?= e((string)$st['cls']) ?>"><?= e((string)$st['label']) ?></span></td>
                            <td class="text-end">
                                <div class="acc-tx-actions">
                                    <button type="button" class="btn btn-link btn-sm p-0 acc-tx-view" title="View"><i class="bi bi-eye"></i></button>
                                    <a href="<?= e('index.php?' . http_build_query($txPrintQs)) ?>" class="btn btn-link btn-sm p-0" target="_blank" title="Print"><i class="bi bi-printer"></i></a>
                                    <a href="<?= e('index.php?' . http_build_query($txPdfQs)) ?>" class="btn btn-link btn-sm p-0" target="_blank" title="PDF"><i class="bi bi-file-pdf"></i></a>
                                    <?php if ($src): ?>
                                        <a href="<?= e($src['url']) ?>" class="btn btn-link btn-sm p-0" title="<?= e($src['label']) ?>"><i class="bi bi-box-arrow-up-right"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="acc-tx__analytics" aria-label="Transaction analytics">
        <div class="acc-tx-chart-card">
            <h3 class="acc-tx-chart-card__title">Transaction Distribution</h3>
            <div class="acc-tx-chart-card__body"><canvas id="accTxChartDist" height="200"></canvas></div>
        </div>
        <div class="acc-tx-chart-card">
            <h3 class="acc-tx-chart-card__title">Inflow vs Outflow</h3>
            <div class="acc-tx-chart-card__body"><canvas id="accTxChartFlow" height="200"></canvas></div>
        </div>
        <div class="acc-tx-chart-card">
            <h3 class="acc-tx-chart-card__title">Monthly Transaction Trend</h3>
            <div class="acc-tx-chart-card__body"><canvas id="accTxChartTrend" height="200"></canvas></div>
        </div>
        <div class="acc-tx-chart-card">
            <h3 class="acc-tx-chart-card__title">Top Transaction Categories</h3>
            <div class="acc-tx-top-cats">
                <?php if ($analytics['by_type'] === []): ?>
                    <p class="text-muted small mb-0 px-2">No data for selected period.</p>
                <?php else: ?>
                    <?php foreach (array_slice($analytics['by_type'], 0, 6) as $cat): ?>
                        <div class="acc-tx-top-cats__row">
                            <span><?= e((string)$cat['type']) ?></span>
                            <strong><?= e(sales_format_money((float)$cat['amount'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<div class="offcanvas offcanvas-end acc-tx-drawer" tabindex="-1" id="accTxDrawer" aria-labelledby="accTxDrawerLabel">
    <div class="offcanvas-header">
        <div>
            <h5 class="offcanvas-title" id="accTxDrawerLabel">Transaction Details</h5>
            <p class="acc-tx-drawer__sub mb-0" id="accTxDrawerSub"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="accTxDrawerBody"></div>
</div>

<script type="application/json" id="accTxChartData"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

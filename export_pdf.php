<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ── Collect & sanitize inputs ────────────────────────────────────────────────
$barangays  = isset($_POST['barangays']) && is_array($_POST['barangays']) ? $_POST['barangays'] : [];
$sort       = isset($_POST['sort'])    ? $_POST['sort']    : 'alphabetical';
$sex        = isset($_POST['sex'])     ? trim($_POST['sex'])     : '';
$status     = isset($_POST['status'])  ? trim($_POST['status'])  : '';
$columns    = isset($_POST['columns']) ? trim($_POST['columns']) : 'standard';

if (empty($barangays)) {
    die('No barangay selected. <a href="export.php">Go back</a>');
}

// Sanitize barangay values
$barangays = array_map('trim', $barangays);
$barangays = array_filter($barangays, fn($b) => $b !== '');

// ── Build query ──────────────────────────────────────────────────────────────
$placeholders = implode(',', array_fill(0, count($barangays), '?'));
$types  = str_repeat('s', count($barangays));
$params = $barangays;

$where = "WHERE barangay IN ($placeholders)";

if ($sex !== '') {
    $where  .= " AND sex = ?";
    $types  .= 's';
    $params[] = $sex;
}

if ($status === 'active') {
    $where .= " AND (deceased = 0 OR deceased IS NULL)";
} elseif ($status === 'deceased') {
    $where .= " AND deceased = 1";
}

$order = $sort === 'birthdate'
    ? "ORDER BY birthdate ASC, name ASC"
    : "ORDER BY name ASC";

$sql = "SELECT *, TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) as age
        FROM persons
        $where
        $order";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$persons = [];
while ($row = $result->fetch_assoc()) {
    if (isset($row['age']) && $row['age'] < 0) $row['age'] = 0;
    $persons[] = $row;
}

// ── Labels ───────────────────────────────────────────────────────────────────
$barangay_label = count($barangays) === 26 || count($barangays) >= count($barangays)
    ? implode(', ', $barangays)
    : implode(', ', $barangays);

// Shorten label for header
$brgy_display = count($barangays) > 3
    ? count($barangays) . ' Barangays'
    : implode(', ', $barangays);

$sort_label   = $sort === 'birthdate' ? 'Sorted by Birthdate' : 'Sorted Alphabetically';
$sex_label    = $sex  !== '' ? " | Sex: $sex" : '';
$status_label = $status !== '' ? ' | Status: ' . ucfirst($status) : '';
$generated    = date('F d, Y h:i A');
$show_osca    = $columns === 'full';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Senior Citizens Export – <?php echo htmlspecialchars($brgy_display); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            background: #fff;
        }

        /* ── Print controls (hidden when printing) ── */
        .print-controls {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 999;
            display: flex;
            gap: 8px;
        }
        .print-controls button {
            padding: 8px 18px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-print  { background: #ef4444; color: #fff; }
        .btn-close2 { background: #64748b; color: #fff; }

        @media print {
            .print-controls { display: none; }
            body { font-size: 10px; }
        }

        /* ── Page layout ── */
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 14mm 12mm 14mm 12mm;
        }

        /* ── Header ── */
        .report-header {
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 3px solid #1a1a2e;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .report-header img {
            height: 64px;
            width: 64px;
            object-fit: contain;
        }
        .header-text { flex: 1; }
        .header-text .agency {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
        }
        .header-text .title {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1.2;
        }
        .header-text .subtitle {
            font-size: 10px;
            color: #475569;
            margin-top: 2px;
        }
        .header-meta {
            text-align: right;
            font-size: 9px;
            color: #64748b;
            line-height: 1.6;
        }

        /* ── Filter summary ── */
        .filter-summary {
            background: #f1f5f9;
            border-left: 4px solid #667eea;
            padding: 6px 10px;
            margin-bottom: 10px;
            border-radius: 0 6px 6px 0;
            font-size: 10px;
            color: #374151;
        }
        .filter-summary strong { color: #1a1a2e; }

        /* ── Stats row ── */
        .stats-row {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }
        .stat-box {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 10px;
            text-align: center;
        }
        .stat-box .stat-num {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1;
        }
        .stat-box .stat-lbl {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        thead tr {
            background: #1a1a2e;
            color: #fff;
        }
        thead th {
            padding: 6px 7px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            white-space: nowrap;
        }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr.deceased-row    { background: #fef2f2; }
        tbody td {
            padding: 5px 7px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 10px;
        }
        .badge-active   { color: #16a34a; font-weight: 700; }
        .badge-deceased { color: #dc2626; font-weight: 700; }
        .no-data { text-align: center; padding: 30px; color: #94a3b8; font-style: italic; }

        /* ── Footer ── */
        .report-footer {
            margin-top: 16px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #94a3b8;
        }

        /* ── Signature block ── */
        .signature-block {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
        }
        .signature-inner {
            text-align: center;
            min-width: 200px;
        }
        .signature-line {
            border-top: 1px solid #1a1a2e;
            margin-top: 40px;
            padding-top: 4px;
            font-size: 10px;
            font-weight: 700;
        }
        .signature-role {
            font-size: 9px;
            color: #64748b;
        }
    </style>
</head>
<body>

<!-- Print controls -->
<div class="print-controls">
    <button class="btn-print" onclick="window.print()">
        🖨️ Print / Save as PDF
    </button>
    <button class="btn-close2" onclick="window.close()">✕ Close</button>
</div>

<div class="page">

    <!-- Header -->
    <div class="report-header">
        <img src="city_logo.png" alt="City Logo" onerror="this.style.display='none'">
        <div class="header-text">
            <div class="agency">City Social Welfare and Development Office – Koronadal City</div>
            <div class="title">Senior Citizens Registry</div>
            <div class="subtitle">
                <?php echo htmlspecialchars($brgy_display); ?>
                &nbsp;|&nbsp;<?php echo $sort_label; ?><?php echo $sex_label; ?><?php echo $status_label; ?>
            </div>
        </div>
        <div class="header-meta">
            <div><strong>Date Generated:</strong></div>
            <div><?php echo $generated; ?></div>
            <div style="margin-top:4px;"><strong>Total Records:</strong> <?php echo count($persons); ?></div>
        </div>
    </div>

    <!-- Filter summary -->
    <div class="filter-summary">
        <strong>Barangay/s:</strong>
        <?php
        if (count($barangays) <= 5) {
            echo htmlspecialchars(implode(', ', $barangays));
        } else {
            echo htmlspecialchars(implode(', ', array_slice($barangays, 0, 5))) . ' … and ' . (count($barangays) - 5) . ' more';
        }
        ?>
        &nbsp;&nbsp;
        <strong>Sort:</strong> <?php echo $sort_label; ?>
        <?php if ($sex !== ''): ?>
            &nbsp;&nbsp;<strong>Sex:</strong> <?php echo htmlspecialchars($sex); ?>
        <?php endif; ?>
        <?php if ($status !== ''): ?>
            &nbsp;&nbsp;<strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($status)); ?>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <?php
    $total   = count($persons);
    $males   = count(array_filter($persons, fn($p) => $p['sex'] === 'Male'));
    $females = count(array_filter($persons, fn($p) => $p['sex'] === 'Female'));
    $active  = count(array_filter($persons, fn($p) => !$p['deceased']));
    $deceased_count = count(array_filter($persons, fn($p) => $p['deceased']));
    ?>
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-num"><?php echo $total; ?></div>
            <div class="stat-lbl">Total</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?php echo $males; ?></div>
            <div class="stat-lbl">Male</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?php echo $females; ?></div>
            <div class="stat-lbl">Female</div>
        </div>
        <div class="stat-box">
            <div class="stat-num" style="color:#16a34a;"><?php echo $active; ?></div>
            <div class="stat-lbl">Active</div>
        </div>
        <div class="stat-box">
            <div class="stat-num" style="color:#dc2626;"><?php echo $deceased_count; ?></div>
            <div class="stat-lbl">Deceased</div>
        </div>
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th style="width:28px;">#</th>
                <th>ID Number</th>
                <?php if ($show_osca): ?><th>OSCA ID</th><?php endif; ?>
                <th>Full Name</th>
                <th>Sex</th>
                <th>Birthdate</th>
                <th>Age</th>
                <th>Barangay</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($persons)): ?>
                <tr><td colspan="<?php echo $show_osca ? 9 : 8; ?>" class="no-data">No records found for the selected filters.</td></tr>
            <?php else: ?>
                <?php foreach ($persons as $i => $p): ?>
                <tr class="<?php echo $p['deceased'] ? 'deceased-row' : ''; ?>">
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($p['id_number']); ?></td>
                    <?php if ($show_osca): ?>
                    <td><?php echo htmlspecialchars($p['osca_id'] ?? '—'); ?></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo htmlspecialchars($p['sex']); ?></td>
                    <td>
                        <?php echo $p['birthdate'] ? date('M d, Y', strtotime($p['birthdate'])) : '—'; ?>
                    </td>
                    <td>
                        <?php
                        if (!$p['birthdate']) {
                            echo '—';
                        } elseif ($p['deceased'] && $p['deceased_date']) {
                            $age_at_death = (int)date('Y', strtotime($p['deceased_date'])) - (int)date('Y', strtotime($p['birthdate']));
                            if (date('md', strtotime($p['deceased_date'])) < date('md', strtotime($p['birthdate']))) $age_at_death--;
                            echo $age_at_death;
                        } else {
                            echo max(0, (int)$p['age']);
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($p['barangay']); ?></td>
                    <td>
                        <?php if ($p['deceased']): ?>
                            <span class="badge-deceased">Deceased</span>
                            <?php if ($p['deceased_date']): ?>
                                <br><span style="font-size:8px;color:#94a3b8;"><?php echo date('M d, Y', strtotime($p['deceased_date'])); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge-active">Active</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Signature block -->
    <div class="signature-block">
        <div class="signature-inner">
            <img src="official_signature.png" alt="" style="max-height:50px;" onerror="this.style.display='none'">
            <div class="signature-line">CSWDO Officer</div>
            <div class="signature-role">City Social Welfare and Development Officer</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="report-footer">
        <span>Senior Citizen Management System – Koronadal City CSWDO</span>
        <span>Generated: <?php echo $generated; ?></span>
    </div>

</div>

<script>
    // Auto-trigger print dialog after a short delay so the page renders first
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 600);
    });
</script>
</body>
</html>

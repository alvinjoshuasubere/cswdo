<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Predefined barangays (same as index.php)
$predefined_barangays = [
    'BRGY. AVANCEÑA',
    'BRGY. CACUB',
    'BRGY. CALOOCAN',
    'BRGY. CARPENTER HILL',
    'BRGY. CONCEPCION',
    'BRGY. ESPERANZA',
    'BRGY. GENERAL PAULINO SANTOS',
    'BRGY. MABINI',
    'BRGY. MAGSAYSAY',
    'BRGY. MAMBUCAL',
    'BRGY. MORALES',
    'BRGY. NAMNAMA',
    'BRGY. NEW PANGASINAN',
    'BRGY. PARAISO',
    'BRGY. ROTONDA',
    'BRGY. SAN ISIDRO',
    'BRGY. SAN ROQUE',
    'BRGY. SAN JOSE',
    'BRGY. STA. CRUZ',
    'BRGY. STO. NIÑO',
    'BRGY. SARAVIA',
    'BRGY. TOPLAND',
    'BRGY. ZONE 1',
    'BRGY. ZONE 2',
    'BRGY. ZONE 3',
    'BRGY. ZONE 4'
];

// Get barangays that actually have records
$barangays_result = $conn->query("SELECT DISTINCT barangay FROM persons WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay");
$db_barangays = [];
if ($barangays_result) {
    while ($row = $barangays_result->fetch_assoc()) {
        $db_barangays[] = $row['barangay'];
    }
}

// Merge predefined + DB barangays (unique)
$all_barangays = array_unique(array_merge($predefined_barangays, $db_barangays));
sort($all_barangays);

$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Records – Senior Citizen System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .export-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(102,126,234,0.10);
            background: #fff;
        }
        .export-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-radius: 16px 16px 0 0;
            padding: 1.25rem 1.5rem;
        }
        .export-card .card-header h5 {
            margin: 0;
            font-weight: 600;
        }
        .barangay-list {
            max-height: 320px;
            overflow-y: auto;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.5rem;
            background: #f8fafc;
        }
        .barangay-list .form-check {
            padding: 0.35rem 0.5rem;
            border-radius: 6px;
            transition: background 0.15s;
        }
        .barangay-list .form-check:hover {
            background: #ede9fe;
        }
        .barangay-list .form-check-label {
            font-size: 0.9rem;
            cursor: pointer;
        }
        .sort-option {
            cursor: pointer;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .sort-option:hover,
        .sort-option.selected {
            border-color: #667eea;
            background: #ede9fe;
        }
        .sort-option.selected .sort-icon {
            color: #667eea;
        }
        .sort-icon {
            font-size: 1.5rem;
            color: #94a3b8;
        }
        .btn-export-pdf {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 0.65rem 1.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(239,68,68,0.25);
        }
        .btn-export-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239,68,68,0.35);
            color: #fff;
        }
        .btn-export-excel {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 0.65rem 1.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(22,163,74,0.25);
        }
        .btn-export-excel:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(22,163,74,0.35);
            color: #fff;
        }
        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            margin-right: 0.5rem;
            flex-shrink: 0;
        }
        .step-title {
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        #preview-count {
            font-size: 0.9rem;
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php require_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-download me-2"></i>Export Records</h1>
                <a href="index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="export-card card mb-4">
                        <div class="card-header">
                            <h5><i class="bi bi-funnel me-2"></i>Export Filters</h5>
                            <small class="opacity-75">Configure your export options below, then choose PDF or Excel.</small>
                        </div>
                        <div class="card-body p-4">

                            <!-- STEP 1: Barangay Selection -->
                            <div class="mb-4">
                                <div class="step-title">
                                    <span class="step-badge">1</span> Select Barangay
                                </div>

                                <div class="mb-2 d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn" onclick="selectAllBarangays()">
                                        <i class="bi bi-check2-all"></i> Select All
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllBarangays()">
                                        <i class="bi bi-x-circle"></i> Clear All
                                    </button>
                                    <span id="preview-count" class="ms-auto align-self-center"></span>
                                </div>

                                <div class="barangay-list" id="barangayList">
                                    <?php foreach ($all_barangays as $brgy): ?>
                                    <div class="form-check">
                                        <input class="form-check-input brgy-checkbox" type="checkbox"
                                               value="<?php echo htmlspecialchars($brgy); ?>"
                                               id="brgy_<?php echo md5($brgy); ?>"
                                               onchange="updatePreviewCount()">
                                        <label class="form-check-label" for="brgy_<?php echo md5($brgy); ?>">
                                            <?php echo htmlspecialchars($brgy); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text mt-1">
                                    <i class="bi bi-info-circle"></i>
                                    Select one or more barangays. Use "Select All" to include all barangays.
                                </div>
                            </div>

                            <hr>

                            <!-- STEP 2: Sort Order -->
                            <div class="mb-4">
                                <div class="step-title">
                                    <span class="step-badge">2</span> Sort Order
                                </div>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <div class="sort-option selected" id="sort_alpha" onclick="selectSort('alphabetical')">
                                            <div class="d-flex align-items-center gap-3">
                                                <i class="bi bi-sort-alpha-down sort-icon"></i>
                                                <div>
                                                    <div class="fw-600" style="font-weight:600;">Alphabetical</div>
                                                    <small class="text-muted">Sort by last name A → Z</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="sort-option" id="sort_birth" onclick="selectSort('birthdate')">
                                            <div class="d-flex align-items-center gap-3">
                                                <i class="bi bi-calendar-date sort-icon"></i>
                                                <div>
                                                    <div class="fw-600" style="font-weight:600;">By Birthdate</div>
                                                    <small class="text-muted">Oldest first (earliest birthdate)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="sortOrder" value="alphabetical">
                            </div>

                            <hr>

                            <!-- STEP 3: Additional Filters -->
                            <div class="mb-4">
                                <div class="step-title">
                                    <span class="step-badge">3</span> Additional Filters <small class="text-muted fw-normal">(optional)</small>
                                </div>
                                <div class="row g-3">
                                    <div class="col-sm-4">
                                        <label class="form-label">Sex</label>
                                        <select class="form-select" id="filterSex">
                                            <option value="">All</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" id="filterStatus">
                                            <option value="">All</option>
                                            <option value="active">Active Only</option>
                                            <option value="deceased">Deceased Only</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">Include Columns</label>
                                        <select class="form-select" id="filterColumns">
                                            <option value="standard">Standard</option>
                                            <option value="full">Full (with OSCA ID)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- STEP 4: Export -->
                            <div>
                                <div class="step-title">
                                    <span class="step-badge">4</span> Export
                                </div>
                                <div class="d-flex gap-3 flex-wrap">
                                    <button type="button" class="btn-export-pdf btn" onclick="doExport('pdf')">
                                        <i class="bi bi-file-earmark-pdf me-2"></i>Export to PDF
                                    </button>
                                    <button type="button" class="btn-export-excel btn" onclick="doExport('excel')">
                                        <i class="bi bi-file-earmark-excel me-2"></i>Export to Excel
                                    </button>
                                </div>
                                <div class="form-text mt-2">
                                    <i class="bi bi-lightbulb text-warning"></i>
                                    PDF will open in a new tab — use your browser's Print dialog to save as PDF.
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ── Sort selection ──────────────────────────────────────────────
    function selectSort(value) {
        document.getElementById('sortOrder').value = value;
        document.getElementById('sort_alpha').classList.toggle('selected', value === 'alphabetical');
        document.getElementById('sort_birth').classList.toggle('selected', value === 'birthdate');
    }

    // ── Barangay helpers ────────────────────────────────────────────
    function selectAllBarangays() {
        document.querySelectorAll('.brgy-checkbox').forEach(cb => cb.checked = true);
        updatePreviewCount();
    }

    function clearAllBarangays() {
        document.querySelectorAll('.brgy-checkbox').forEach(cb => cb.checked = false);
        updatePreviewCount();
    }

    function getSelectedBarangays() {
        return Array.from(document.querySelectorAll('.brgy-checkbox:checked')).map(cb => cb.value);
    }

    function updatePreviewCount() {
        const selected = getSelectedBarangays();
        const el = document.getElementById('preview-count');
        if (selected.length === 0) {
            el.textContent = 'No barangay selected';
        } else if (selected.length === document.querySelectorAll('.brgy-checkbox').length) {
            el.textContent = 'All barangays selected';
        } else {
            el.textContent = selected.length + ' barangay' + (selected.length > 1 ? 's' : '') + ' selected';
        }
    }

    // ── Export ──────────────────────────────────────────────────────
    function doExport(type) {
        const selected = getSelectedBarangays();
        if (selected.length === 0) {
            alert('Please select at least one barangay before exporting.');
            return;
        }

        const sort    = document.getElementById('sortOrder').value;
        const sex     = document.getElementById('filterSex').value;
        const status  = document.getElementById('filterStatus').value;
        const columns = document.getElementById('filterColumns').value;

        // Build form and POST to the correct handler
        const form = document.createElement('form');
        form.method = 'POST';
        form.target = type === 'pdf' ? '_blank' : '_self';
        form.action = type === 'pdf' ? 'export_pdf.php' : 'export_excel.php';

        const addField = (name, value) => {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = name;
            input.value = value;
            form.appendChild(input);
        };

        // Send each barangay as an array
        selected.forEach(brgy => addField('barangays[]', brgy));
        addField('sort',    sort);
        addField('sex',     sex);
        addField('status',  status);
        addField('columns', columns);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    // Init
    updatePreviewCount();
</script>
</body>
</html>

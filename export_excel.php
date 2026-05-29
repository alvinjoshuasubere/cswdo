<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

// ── Collect & sanitize inputs ────────────────────────────────────────────────
$barangays  = isset($_POST['barangays']) && is_array($_POST['barangays']) ? $_POST['barangays'] : [];
$sort       = isset($_POST['sort'])    ? $_POST['sort']    : 'alphabetical';
$sex        = isset($_POST['sex'])     ? trim($_POST['sex'])     : '';
$status     = isset($_POST['status'])  ? trim($_POST['status'])  : '';
$columns    = isset($_POST['columns']) ? trim($_POST['columns']) : 'standard';

if (empty($barangays)) {
    die('No barangay selected. <a href="export.php">Go back</a>');
}

$barangays = array_map('trim', $barangays);
$barangays = array_filter($barangays, fn($b) => $b !== '');

// ── Build query ──────────────────────────────────────────────────────────────
$placeholders = implode(',', array_fill(0, count($barangays), '?'));
$types  = str_repeat('s', count($barangays));
$params = array_values($barangays);

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

$show_osca = $columns === 'full';

// ── Build spreadsheet ────────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Senior Citizens');

// ── Colour palette ───────────────────────────────────────────────────────────
$NAVY   = '1A1A2E';
$PURPLE = '667EEA';
$LIGHT  = 'F1F5F9';
$WHITE  = 'FFFFFF';
$GREEN  = 'D1FAE5';
$RED    = 'FEE2E2';
$BORDER = 'E2E8F0';

// ── Merge & title row ────────────────────────────────────────────────────────
$lastCol = $show_osca ? 'J' : 'I';
$colCount = $show_osca ? 10 : 9;

// Row 1 – System title
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'CITY SOCIAL WELFARE AND DEVELOPMENT OFFICE – KORONADAL CITY');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF' . $WHITE]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $NAVY]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(22);

// Row 2 – Report subtitle
$brgy_display = count($barangays) > 3
    ? count($barangays) . ' Barangays'
    : implode(', ', $barangays);

$sort_label   = $sort === 'birthdate' ? 'Sorted by Birthdate' : 'Sorted Alphabetically';
$sex_label    = $sex  !== '' ? " | Sex: $sex" : '';
$status_label = $status !== '' ? ' | Status: ' . ucfirst($status) : '';

$sheet->mergeCells("A2:{$lastCol}2");
$sheet->setCellValue('A2', "Senior Citizens Registry – {$brgy_display} | {$sort_label}{$sex_label}{$status_label}");
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['bold' => false, 'size' => 10, 'color' => ['argb' => 'FF' . $WHITE]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $PURPLE]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(2)->setRowHeight(16);

// Row 3 – Meta info
$sheet->mergeCells("A3:{$lastCol}3");
$sheet->setCellValue('A3', 'Date Generated: ' . date('F d, Y h:i A') . '     Total Records: ' . count($persons));
$sheet->getStyle('A3')->applyFromArray([
    'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF475569']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $LIGHT]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->getRowDimension(3)->setRowHeight(14);

// Row 4 – blank spacer
$sheet->getRowDimension(4)->setRowHeight(6);

// ── Column headers (row 5) ───────────────────────────────────────────────────
$headers = ['#', 'ID Number'];
if ($show_osca) $headers[] = 'OSCA ID';
$headers = array_merge($headers, ['Full Name', 'Sex', 'Birthdate', 'Age', 'Barangay', 'City', 'Status']);

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '5', $header);
    $col++;
}

$headerRange = "A5:{$lastCol}5";
$sheet->getStyle($headerRange)->applyFromArray([
    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF' . $WHITE]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $NAVY]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . $BORDER]],
    ],
]);
$sheet->getRowDimension(5)->setRowHeight(18);

// ── Data rows ────────────────────────────────────────────────────────────────
$rowNum = 6;
foreach ($persons as $i => $p) {
    $isDeceased = (bool)$p['deceased'];

    // Calculate age
    if (!$p['birthdate']) {
        $age = '—';
    } elseif ($isDeceased && $p['deceased_date']) {
        $age = (int)date('Y', strtotime($p['deceased_date'])) - (int)date('Y', strtotime($p['birthdate']));
        if (date('md', strtotime($p['deceased_date'])) < date('md', strtotime($p['birthdate']))) $age--;
    } else {
        $age = max(0, (int)$p['age']);
    }

    $rowData = [$i + 1, $p['id_number']];
    if ($show_osca) $rowData[] = $p['osca_id'] ?? '';
    $rowData = array_merge($rowData, [
        $p['name'],
        $p['sex'],
        $p['birthdate'] ? date('M d, Y', strtotime($p['birthdate'])) : '—',
        $age,
        $p['barangay'],
        $p['city'],
        $isDeceased ? 'Deceased' : 'Active',
    ]);

    $col = 'A';
    foreach ($rowData as $val) {
        $sheet->setCellValue($col . $rowNum, $val);
        $col++;
    }

    // Row background
    $bgColor = $isDeceased ? $RED : ($i % 2 === 0 ? $WHITE : $LIGHT);
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $bgColor]],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . $BORDER]],
        ],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // Status cell colour
    $statusCol = $show_osca ? 'J' : 'I';
    if ($isDeceased) {
        $sheet->getStyle("{$statusCol}{$rowNum}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFDC2626']],
        ]);
    } else {
        $sheet->getStyle("{$statusCol}{$rowNum}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF16A34A']],
        ]);
    }

    // Center # and Age columns
    $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ageCol = $show_osca ? 'G' : 'F';
    $sheet->getStyle("{$ageCol}{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->getRowDimension($rowNum)->setRowHeight(15);
    $rowNum++;
}

// ── Summary rows ─────────────────────────────────────────────────────────────
$rowNum++; // blank row
$total    = count($persons);
$males    = count(array_filter($persons, fn($p) => $p['sex'] === 'Male'));
$females  = count(array_filter($persons, fn($p) => $p['sex'] === 'Female'));
$active   = count(array_filter($persons, fn($p) => !$p['deceased']));
$deceased_count = count(array_filter($persons, fn($p) => $p['deceased']));

$summaries = [
    ['Total Records', $total],
    ['Male',          $males],
    ['Female',        $females],
    ['Active',        $active],
    ['Deceased',      $deceased_count],
];

$sheet->mergeCells("A{$rowNum}:C{$rowNum}");
$sheet->setCellValue("A{$rowNum}", 'SUMMARY');
$sheet->getStyle("A{$rowNum}:C{$rowNum}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF' . $WHITE]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $NAVY]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$rowNum++;

foreach ($summaries as [$label, $value]) {
    $sheet->setCellValue("A{$rowNum}", $label);
    $sheet->setCellValue("B{$rowNum}", $value);
    $sheet->getStyle("A{$rowNum}:B{$rowNum}")->applyFromArray([
        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $LIGHT]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . $BORDER]]],
    ]);
    $sheet->getStyle("A{$rowNum}")->getFont()->setBold(true);
    $rowNum++;
}

// ── Column widths ─────────────────────────────────────────────────────────────
$widths = [5, 16]; // # , ID Number
if ($show_osca) $widths[] = 16; // OSCA ID
$widths = array_merge($widths, [36, 8, 14, 6, 22, 18, 12]); // Name, Sex, Birthdate, Age, Barangay, City, Status

$col = 'A';
foreach ($widths as $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
    $col++;
}

// ── Freeze panes ─────────────────────────────────────────────────────────────
$sheet->freezePane('A6');

// ── Auto-filter ──────────────────────────────────────────────────────────────
$sheet->setAutoFilter("A5:{$lastCol}5");

// ── Output ───────────────────────────────────────────────────────────────────
$filename = 'senior_citizens_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();

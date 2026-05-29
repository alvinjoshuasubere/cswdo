<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$person_id = $_GET['id'] ?? 0;
if ($person_id == 0) { die('Invalid person ID'); }

$stmt = $conn->prepare("SELECT *, TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) as age FROM persons WHERE id = ?");
$stmt->bind_param("i", $person_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) { die('Person not found'); }
$person = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Movie Card — <?php echo htmlspecialchars($person['name']); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --pink:      #e86ac7;
      --pink-pale: #fdf0fa;
      --white:     #ffffff;
      --black:     #0a0a0a;
      --border:    #e0e0e0;
      --font-d:    'Bebas Neue', sans-serif;
      --font-b:    'Inter', sans-serif;
      --cw: 85.6mm;
      --ch: 53.98mm;
    }

    body {
      background: #f2f2f2;
      font-family: var(--font-b);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      gap: 26px;
    }

    .page-label {
      font-size: 10px;
      font-weight: 500;
      letter-spacing: .18em;
      text-transform: uppercase;
      color: #aaa;
      text-align: center;
    }

    /* ── Card shell ─────────────────────── */
    .id-card {
      width: var(--cw);
      height: var(--ch);
      background: var(--white);
      border-radius: 3.5mm;
      position: relative;
      overflow: hidden;
      outline: 1px solid var(--border);
      box-shadow: 0 8px 40px rgba(0,0,0,.13), 0 2px 10px rgba(232,106,199,.2);
    }

    /* ── Decorative layer ───────────────── */
    /* Left pink vertical strip */
    .d-strip {
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 3mm;
      background: var(--pink);
      z-index: 0;
    }

    /* Bottom black bar */
    .d-bottom {
      position: absolute;
      left: 0; right: 0; bottom: 0;
      height: 5.6mm;
      background: var(--black);
      z-index: 1;
    }

    /* Faint ring watermark */
    .d-ring {
      position: absolute;
      right: -11mm; top: 50%;
      transform: translateY(-50%);
      width: 38mm; height: 38mm;
      border-radius: 50%;
      border: 4.5mm solid var(--pink);
      opacity: .06;
      z-index: 0;
      pointer-events: none;
    }

    /* Logo watermark */
    .d-wm {
      position: absolute;
      left: 50%; top: 50%;
      transform: translate(-50%, -50%);
      width: 30mm;
      opacity: .09;
      filter: grayscale(1);
      z-index: 0;
      pointer-events: none;
    }

    /* ── HEADER ─────────────────────────── */
    .card-header {
      position: absolute;
      top: 0; left: 3mm; right: 0;
      height: 10.5mm;
      background: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 2.5mm;
      padding: 0 3mm;
      border-bottom: .5mm solid var(--pink);
      z-index: 2;
    }

    .h-seal {
      width: 7mm; height: 7mm;
      object-fit: contain;
      flex-shrink: 0;
    }
    /* invisible mirror keeps seal centered */
    .h-seal-ghost { width: 7mm; height: 7mm; flex-shrink: 0; visibility: hidden; }

    .h-text {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: .25mm;
      text-align: center;
    }
    .h-republic {
      font-size: 3.2pt;
      font-weight: 400;
      color: #888;
      letter-spacing: .05em;
      line-height: 1;
    }
    .h-city {
      font-size: 5pt;
      font-weight: 700;
      color: var(--black);
      letter-spacing: .1em;
      text-transform: uppercase;
      line-height: 1.1;
    }
    .h-office {
      font-size: 3.2pt;
      font-weight: 500;
      color: #999;
      letter-spacing: .04em;
      line-height: 1;
    }

    /* ── MOVIE CARD band ────────────────── */
    .movie-band {
      position: absolute;
      top: 10.5mm; left: 3mm; right: 0;
      height: 7.8mm;
      background: var(--pink);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2;
    }
    .movie-title {
      font-family: var(--font-d);
      font-size: 16pt;
      color: var(--white);
      letter-spacing: .42em;
      line-height: 1;
    }

    /* ── BODY ───────────────────────────── */
    .card-body {
      position: absolute;
      top: 18.3mm; left: 3mm; right: 0;
      bottom: 5.6mm;
      display: flex;
      align-items: stretch;
      gap: 2.4mm;
      padding: 1.8mm 2.5mm 1.5mm;
      z-index: 2;
    }

    /* Photo column */
    .col-photo {
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.2mm;
    }

    .photo-frame {
      width: 20mm;
      height: 20mm;
      border: .5mm solid var(--pink);
      overflow: hidden;
      background: var(--pink-pale);
    }
    .photo-frame img {
      width: 100%; height: 100%;
      object-fit: cover;
      display: block;
    }
    .photo-ph {
      width: 100%; height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .qr-box {
      width: 13mm; height: 13mm;
      border: .4mm solid #e0e0e0;
      background: var(--white);
      padding: .6mm;
    }
    .qr-box img { width: 100%; height: 100%; object-fit: contain; display: block; }

    .qr-lbl {
      font-size: 3pt;
      font-weight: 500;
      color: #ccc;
      letter-spacing: .08em;
      margin-right: 2mm;
      text-transform: uppercase;
      text-align: center;
    }

    .col-qr {
      flex: 0 0 17mm;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      justify-content: center;
      gap: .8mm;
    }
    .col-qr .sig-block {
      align-items: flex-end;
      text-align: right;
      width: 100%;
    }
    .col-qr .sig-name,
    .col-qr .sig-title {
      width: 100%;
    }

    /* Info column */
    .col-info {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .info-top { display: flex; flex-direction: column; gap: .9mm; }

    .name-eyebrow {
      font-size: 3.4pt;
      font-weight: 600;
      color: var(--pink);
      letter-spacing: .14em;
      text-transform: uppercase;
      line-height: 1;
    }
    .member-name {
      font-family: var(--font-d);
      font-size: 12pt;
      color: var(--black);
      letter-spacing: .04em;
      line-height: 1;
    }
    .pink-rule {
      width: 100%;
      height: .35mm;
      background: var(--pink);
      opacity: .35;
      margin: .2mm 0;
    }

    .data-block { display: flex; flex-direction: column; gap: 1mm; }
    .data-row   { display: flex; align-items: baseline; gap: 1.5mm; }
    .dr-l {
      font-size: 3.4pt;
      font-weight: 700;
      color: var(--pink);
      text-transform: uppercase;
      letter-spacing: .08em;
      min-width: 10.5mm;
      flex-shrink: 0;
      line-height: 1;
    }
    .dr-v {
      font-size: 6pt;
      font-weight: 600;
      color: var(--black);
      letter-spacing: .01em;
      line-height: 1;
    }

    /* Signature */
    .sig-block {
      display: flex;
      flex-direction: column;
      align-items: end;
      gap: .4mm;
      margin-top: 5mm;
    }
    .sig-img {
      position: absolute;
      right: 0;
      bottom: 0;
      top: 16mm;
      width: 22mm;
      max-height: 15mm;
      height: auto;
      object-fit: contain;
      pointer-events: none;
    }
    .sig-name  { font-size: 4pt; font-weight: 700; color: var(--black); text-align: center; text-transform: uppercase; letter-spacing: .04em; line-height: 1.1; }
    .sig-title { font-size: 3.2pt; font-weight: 400; color: #999; text-align: center; letter-spacing: .04em; text-transform: uppercase; }

    /* ── Bottom ID bar ──────────────────── */
    .id-bar {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 5.6mm;
      background: var(--black);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 4mm 0 6.5mm;
      z-index: 3;
    }
    .id-bar-label { font-size: 3.6pt; font-weight: 400; color: rgba(255,255,255,.38); letter-spacing: .14em; text-transform: uppercase; }
    .id-bar-num   { font-family: var(--font-d); font-size: 10.5pt; color: var(--white); letter-spacing: .3em; }
    .id-bar-r     { font-size: 3.5pt; font-weight: 400; color: rgba(255,255,255,.38); letter-spacing: .05em; text-align: right; line-height: 1.4; }

    /* ── Actions ────────────────────────── */
    .actions { display: flex; gap: 10px; align-items: center; }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 22px;
      border-radius: 7px;
      border: none;
      font-family: var(--font-b);
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      letter-spacing: .02em;
      transition: opacity .15s, transform .1s;
    }
    .btn:hover  { opacity: .86; transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }
    .btn-black { background: var(--black); color: var(--white); }
    .btn-pink  { background: var(--pink); color: var(--white); box-shadow: 0 4px 18px rgba(232,106,199,.35); }

    /* ── Print ──────────────────────────── */
    @media print {
      body { background:#f2f2f2; padding:8mm; align-items:flex-start; justify-content:flex-start; -webkit-print-color-adjust: exact; color-adjust: exact; }
      .actions, .page-label { display:none; }
      .id-card { box-shadow:none; outline:.3mm solid #ccc; background: var(--white); -webkit-print-color-adjust: exact; color-adjust: exact; }
      .d-strip, .movie-band, .d-bottom, .d-ring { -webkit-print-color-adjust: exact; color-adjust: exact; }
    }
  </style>
</head>
<body>

  <span class="page-label">City of Koronadal &nbsp;·&nbsp; CSWD &nbsp;·&nbsp; Senior Citizen Office</span>

  <div class="id-card">

    <!-- Decorative -->
    <div class="d-strip"></div>
    <div class="d-bottom"></div>
    <div class="d-ring"></div>
    <img src="city_logo.png" class="d-wm" alt="">

    <!-- Header — centered -->
    <div class="card-header">
      <img src="city_logo.png" class="h-seal" alt="City Seal">
      <div class="h-text">
        <span class="h-republic">Republic of the Philippines</span>
        <span class="h-republic">Province of South Cotabato</span>
        <span class="h-office">City of Koronadal</span>
        <span class="h-city">Office of Senior Citizens Affairs</span>
      </div>
      <div class="h-seal-ghost"></div>
    </div>

    <!-- MOVIE CARD band -->
    <div class="movie-band">
      <span class="movie-title">MOVIE CARD</span>
    </div>

    <!-- Body -->
    <div class="card-body">

      <!-- Photo + QR -->
      <div class="col-photo">
        <div class="photo-frame">
          <?php if (!empty($person['picture'])): ?>
            <img src="<?php echo htmlspecialchars($person['picture']); ?>" alt="Member Photo">
          <?php else: ?>
            <div class="photo-ph">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#e86ac7" stroke-width="1.5">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 20c0-4 3.58-7 8-7s8 3 8 7"/>
              </svg>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Info -->
      <div class="col-info">
        <div class="info-top">
          <span class="name-eyebrow">Name of Member</span>
          <div class="member-name"><?php echo htmlspecialchars($person['name']); ?></div>
          <div class="pink-rule"></div>
          <div class="data-block">
            <div class="data-row">
              <span class="dr-l">Birthdate</span>
              <span class="dr-v"><?php echo date('M d, Y', strtotime($person['birthdate'])); ?></span>
            </div>
            <div class="data-row">
              <span class="dr-l">Sex</span>
              <span class="dr-v"><?php echo htmlspecialchars($person['sex']); ?></span>
            </div>
            <div class="data-row">
              <span class="dr-l">Barangay</span>
              <span class="dr-v"><?php echo htmlspecialchars($person['barangay']); ?></span>
            </div>
            <?php if (!empty($person['purok'])): ?>
            <div class="data-row">
              <span class="dr-l">Purok</span>
              <span class="dr-v"><?php echo htmlspecialchars($person['purok']); ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <?php if (!empty($person['qr_code'])): ?>
        <div class="col-qr">
          <div class="qr-box">
            <img src="<?php echo htmlspecialchars($person['qr_code']); ?>" alt="QR Code">
          </div>
          <span class="qr-lbl">Scan to verify</span>
          <div class="sig-block">
            <?php if (file_exists('official_signature.png')): ?>
              <img src="official_signature.png" class="sig-img" alt="Signature">
            <?php endif; ?>
            <div class="sig-rule"></div>
            <span class="sig-name">Hon. Erlinda P. Araquil</span>
            <span class="sig-title">Authorized Signatory</span>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ID number bar -->
    <div class="id-bar">
      <span class="id-bar-label">ID No.</span>
      <span class="id-bar-num"><?php echo htmlspecialchars($person['id_number']); ?></span>
      <span class="id-bar-r">City of Koronadal</span>
    </div>

  </div>

  <div class="actions">
    <a href="index.php" class="btn btn-black">← Return to List</a>
    <button onclick="window.print()" class="btn btn-pink">🖨&nbsp; Print ID Card</button>
  </div>

</body>
</html>
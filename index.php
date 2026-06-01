<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$upload_errors = [];
if (isset($_SESSION['upload_errors'])) {
    $upload_errors = $_SESSION['upload_errors'];
    unset($_SESSION['upload_errors']);
}

// Pagination and search variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all unique barangays for filter dropdown
$barangays_result = $conn->query("SELECT DISTINCT barangay FROM persons WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay");
$barangays = [];
if ($barangays_result) {
    while ($row = $barangays_result->fetch_assoc()) {
        $barangays[] = $row['barangay'];
    }
}

// Define predefined barangay options
$predefined_barangays = [
    'BRGY. ASSUMPTION',
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

// Get filter parameters
$filter_barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
$filter_sex = isset($_GET['sex']) ? $_GET['sex'] : '';

// Get current user role
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'staff'; // Default to staff for safety

// Build search and filter conditions
$search_condition = '';
$search_params = [];

if (!empty($search)) {
    $search_condition .= " WHERE (id_number LIKE ? OR name LIKE ? OR barangay LIKE ? OR city LIKE ? OR province LIKE ?)";
    $search_term = "%$search%";
    $search_params = array_fill(0, 5, $search_term);
}

// Add barangay filter
if (!empty($filter_barangay)) {
    if (empty($search_condition)) {
        $search_condition .= " WHERE barangay = ?";
    } else {
        $search_condition .= " AND barangay = ?";
    }
    $search_params[] = $filter_barangay;
}

// Add sex filter
if (!empty($filter_sex)) {
    if (empty($search_condition)) {
        $search_condition .= " WHERE sex = ?";
    } else {
        $search_condition .= " AND sex = ?";
    }
    $search_params[] = $filter_sex;
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM persons$search_condition";
$count_stmt = $conn->prepare($count_sql);
if (!empty($search_params)) {
    $count_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_records = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get statistics from ALL data (not filtered by search)
$all_stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) as male_count,
    SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) as female_count
    FROM persons";
$all_stats_result = $conn->query($all_stats_sql);
$all_stats = $all_stats_result->fetch_assoc();

// Get filtered statistics based on current filters
$filter_stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) as male_count,
    SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) as female_count
    FROM persons$search_condition";
$filter_stats_stmt = $conn->prepare($filter_stats_sql);
if (!empty($search_params)) {
    $filter_stats_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
}
$filter_stats_stmt->execute();
$filter_stats_result = $filter_stats_stmt->get_result();
$filter_stats = $filter_stats_result->fetch_assoc();

// Use filtered stats if any filter is applied, otherwise use all stats
$display_stats = (!empty($filter_barangay) || !empty($filter_sex) || !empty($search)) ? $filter_stats : $all_stats;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_person'])) {
        $id_number = $_POST['id_number'];
        $name = $_POST['name'];
        $sex = $_POST['sex'];
        $barangay = $_POST['barangay'];
        $city = $_POST['city'];
        $province = $_POST['province'];
        $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : NULL;
        $osca_id = !empty($_POST['osca_id']) ? $_POST['osca_id'] : NULL;
        
        // Check if name already exists
        $check_stmt = $conn->prepare("SELECT id FROM persons WHERE name = ?");
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = "Error: Person with name '$name' already exists!";
            $message_type = "danger";
        } elseif ($birthdate) {
            // Calculate age based on birthdate
            $birthDateObj = new DateTime($birthdate);
            $today = new DateTime();
            $age = $today->diff($birthDateObj)->y;
            
            if ($age < 60) {
                $message = "Error: Person must be 60 years or older to be added. Current age: $age years.";
                $message_type = "danger";
            } else {
                // Handle file upload
            $picture = '';
            if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $picture = $target_dir . time() . '_' . basename($_FILES["picture"]["name"]);
                move_uploaded_file($_FILES["picture"]["tmp_name"], $picture);
            }
            
            // Generate QR code
            $qr_data = "ID: " . $id_number . "\nName: " . $name . ($birthdate ? "\nBirthdate: " . $birthdate : "");
            $qr_code = generateQRCode($qr_data);
            
            $stmt = $conn->prepare("INSERT INTO persons (id_number, name, sex, barangay, city, province, birthdate, osca_id, picture, qr_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $id_number, $name, $sex, $barangay, $city, $province, $birthdate, $osca_id, $picture, $qr_code);
            
            if ($stmt->execute()) {
                    $message = "Person added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding person: " . $conn->error;
                    $message_type = "danger";
                }
            }
        } else {
            // Handle case when birthdate is empty - allow adding without age restriction
            // Handle file upload
            $picture = '';
            if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $picture = $target_dir . time() . '_' . basename($_FILES["picture"]["name"]);
                move_uploaded_file($_FILES["picture"]["tmp_name"], $picture);
            }
            
            // Generate QR code
            $qr_data = "ID: " . $id_number . "\nName: " . $name;
            $qr_code = generateQRCode($qr_data);
            
            $stmt = $conn->prepare("INSERT INTO persons (id_number, name, sex, barangay, city, province, birthdate, osca_id, picture, qr_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $id_number, $name, $sex, $barangay, $city, $province, $birthdate, $osca_id, $picture, $qr_code);
            
            if ($stmt->execute()) {
                $message = "Person added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding person: " . $conn->error;
                $message_type = "danger";
            }
        }
    } elseif (isset($_POST['update_person'])) {
        $id            = (int)$_POST['id'];
        $name          = trim($_POST['name']);
        $sex           = trim($_POST['sex']);
        $barangay      = trim($_POST['barangay']);
        $city          = trim($_POST['city']);
        $province      = trim($_POST['province']);
        $birthdate     = !empty($_POST['birthdate'])     ? $_POST['birthdate']     : NULL;
        $osca_id       = !empty($_POST['osca_id'])       ? trim($_POST['osca_id']) : NULL;
        $deceased      = isset($_POST['deceased'])       ? 1                       : 0;
        $deceased_date = !empty($_POST['deceased_date']) ? $_POST['deceased_date'] : NULL;

        // Handle file upload
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $picture = $target_dir . time() . '_' . basename($_FILES["picture"]["name"]);
            move_uploaded_file($_FILES["picture"]["tmp_name"], $picture);

            $stmt = $conn->prepare("UPDATE persons SET name = ?, sex = ?, barangay = ?, city = ?, province = ?, birthdate = ?, picture = ?, deceased = ?, deceased_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("sssssssisi", $name, $sex, $barangay, $city, $province, $birthdate, $picture, $deceased, $deceased_date, $id);
        } else {
            $stmt = $conn->prepare("UPDATE persons SET name = ?, sex = ?, barangay = ?, city = ?, province = ?, birthdate = ?, deceased = ?, deceased_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("ssssssisi", $name, $sex, $barangay, $city, $province, $birthdate, $deceased, $deceased_date, $id);
        }

        if ($stmt->execute()) {
            $message = "Person updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating person: " . $conn->error;
            $message_type = "danger";
        }
    } elseif (isset($_POST['delete_person'])) {
        // Only admin can delete persons
        if ($user_role !== 'admin') {
            $message = "Error: You don't have permission to delete records.";
            $message_type = "danger";
        } else {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM persons WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Person deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting person: " . $conn->error;
                $message_type = "danger";
            }
        }
    }
}

// Fetch persons with pagination and search
$persons = [];
$sql = "SELECT *, 
    CASE 
        WHEN birthdate IS NULL THEN 0
        ELSE TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) 
    END as age 
    FROM persons$search_condition ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

if (!empty($search_params)) {
    $types = str_repeat('s', count($search_params)) . 'ii';
    $params = array_merge($search_params, [$per_page, $offset]);
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Ensure age is never negative
        if ($row['age'] < 0) {
            $row['age'] = 0;
        }
        $persons[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Data System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Upper right corner alert positioning */
        .alert-positioned {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .alert-positioned.fade-out {
            animation: slideOutRight 0.3s ease-out forwards;
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* Enhanced alert styling */
        .alert-positioned .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-positioned .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .alert-positioned .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        
        .alert-positioned .btn-close {
            filter: brightness(0) invert(1);
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
                    <h1 class="h2">Senior Citizen List</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if ($user_role === 'admin'): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addPersonModal">
                                    <i class="bi bi-plus-circle"></i> Add Person
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                    <i class="bi bi-upload"></i> Upload Excel
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addPersonModal">
                                    <i class="bi bi-plus-circle"></i> Add Person
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show alert-positioned" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($upload_errors)): ?>
                    <div class="alert alert-warning alert-dismissible fade show alert-positioned" role="alert">
                        <h6>Upload Errors:</h6>
                        <ul class="mb-0">
                            <?php foreach ($upload_errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="simple-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Total Persons</h6>
                                    <h3 class="mb-0"><?php echo $display_stats['total']; ?></h3>
                                </div>
                                <div>
                                    <i class="bi bi-people-fill text-primary" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="simple-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Male</h6>
                                    <h3 class="mb-0"><?php echo $display_stats['male_count']; ?></h3>
                                </div>
                                <div>
                                    <i class="bi bi-gender-male text-info" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="simple-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-1">Female</h6>
                                    <h3 class="mb-0"><?php echo $display_stats['female_count']; ?></h3>
                                </div>
                                <div>
                                    <i class="bi bi-gender-female text-danger" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="row">
                    <!-- Persons List (Left Side) -->
                    <div class="col-lg-12">
                        <!-- Persons List -->
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <!-- <div class="col-md-4">
                                        <h5 class="card-title mb-0">Registered Senior Citizens</h5>
                                    </div> -->
                                    <form id="searchForm" class="row g-2 align-items-center w-100">
                                        <div class="col-md-4">
                                            <input type="text" class="form-control form-control-md" name="search" id="searchInput" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select form-select-md" name="barangay" id="barangayFilter" onchange="applyFilters()">
                                                <option value="">All Barangays</option>
                                                <?php foreach ($barangays as $barangay): ?>
                                                    <option value="<?php echo htmlspecialchars($barangay); ?>" <?php echo $filter_barangay === $barangay ? 'selected' : ''; ?>><?php echo htmlspecialchars($barangay); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select form-select-md" name="sex" id="sexFilter" onchange="applyFilters()">
                                                <option value="">All Sex</option>
                                                <option value="Male" <?php echo $filter_sex === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo $filter_sex === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-auto d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="filterTable()">
                                                <i class="bi bi-search"></i> Search
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                                                <i class="bi bi-x-circle"></i> Clear
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="personsTable" class="table">
                                        <thead>
                                            <tr>
                                                <th>OSCA ID Number</th>
                                                <th>Name</th>
                                                <th>Sex</th>
                                                <th>Age</th>
                                                <th>Birthdate</th>
                                                <th>Barangay</th>
                                                <th>City</th>
                                                <th>Status</th>
                                                <th>Picture</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($persons as $person): ?>
                                            <tr class="<?php echo is_null($person['birthdate']) ? 'incomplete-data' : ''; ?> <?php echo $person['deceased'] ? 'table-danger' : ''; ?>">
                                                <td><?php echo htmlspecialchars($person['id_number']); ?></td>
                                                <td><?php echo htmlspecialchars($person['name']); ?></td>
                                                <td><?php echo htmlspecialchars($person['sex']); ?></td>
                                                <td>
                                                    <?php 
                                                    if (is_null($person['birthdate'])) {
                                                        echo 'N/A';
                                                    } elseif ($person['deceased']) {
                                                        // Calculate age at death
                                                        if ($person['deceased_date']) {
                                                            $age_at_death = date('Y') - date('Y', strtotime($person['birthdate']));
                                                            if (date('md', strtotime($person['deceased_date'])) < date('md', strtotime($person['birthdate']))) {
                                                                $age_at_death--;
                                                            }
                                                            echo $age_at_death . ' <small class="text-muted">(at death)</small>';
                                                        } else {
                                                            echo $person['age'] . ' <small class="text-muted">(deceased)</small>';
                                                        }
                                                    } else {
                                                        echo $person['age'];
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($person['birthdate']) {
                                                        echo date('M d, Y', strtotime($person['birthdate']));
                                                    } else {
                                                        echo '<span class="text-muted">N/A</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($person['barangay']); ?></td>
                                                <td><?php echo htmlspecialchars($person['city']); ?></td>
                                                <td>
                                                    <?php if ($person['deceased']): ?>
                                                        <span class="badge bg-danger">Deceased</span>
                                                        <?php if ($person['deceased_date']): ?>
                                                            <br><small><?php echo date('M d, Y', strtotime($person['deceased_date'])); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($person['picture']): ?>
                                                        <img src="<?php echo htmlspecialchars($person['picture']); ?>" class="person-image" alt="Picture">
                                                    <?php else: ?>
                                                        <i class="bi bi-person-circle" style="font-size: 2rem;"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user_role === 'admin'): ?>
                                                        <button class="btn btn-sm btn-primary" onclick="window.open('id_card_view.php?id=<?php echo $person['id']; ?>', '_blank')">
                                                            <i class="bi bi-card-text"></i> ID
                                                        </button>
                                                        <button class="btn btn-sm btn-warning" onclick="editPerson(<?php echo $person['id']; ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="deletePerson(<?php echo $person['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-primary" onclick="window.open('id_card_view.php?id=<?php echo $person['id']; ?>', '_blank')">
                                                            <i class="bi bi-card-text"></i> ID
                                                        </button>
                                                        <button class="btn btn-sm btn-warning" onclick="editPerson(<?php echo $person['id']; ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-end mt-2">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_barangay) ? '&barangay=' . urlencode($filter_barangay) : ''; ?><?php echo !empty($filter_sex) ? '&sex=' . urlencode($filter_sex) : ''; ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_barangay) ? '&barangay=' . urlencode($filter_barangay) : ''; ?><?php echo !empty($filter_sex) ? '&sex=' . urlencode($filter_sex) : ''; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($filter_barangay) ? '&barangay=' . urlencode($filter_barangay) : ''; ?><?php echo !empty($filter_sex) ? '&sex=' . urlencode($filter_sex) : ''; ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                
                                <div class="text-left text-muted mt-2">
                                    Showing <?php echo count($persons); ?> of <?php echo $total_records; ?> records
                                    <?php if (!empty($search)): ?>
                                        (filtered by "<?php echo htmlspecialchars($search); ?>")
                                    <?php endif; ?>
                                    <?php if (!empty($filter_barangay)): ?>
                                        | Barangay: <?php echo htmlspecialchars($filter_barangay); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($filter_sex)): ?>
                                        | Sex: <?php echo htmlspecialchars($filter_sex); ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Person Modal -->
    <div class="modal fade" id="addPersonModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Person</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" onsubmit="return validateAge()">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="id_number" class="form-label">OSCA ID Number *</label>
                                    <input type="text" class="form-control" id="id_number" name="id_number" required>
                                </div>
                            </div>
                            <!-- <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="osca_id" class="form-label">OSCA ID</label>
                                    <input type="text" class="form-control" id="osca_id" name="osca_id">
                                    <div class="form-text">Office of Senior Citizens Affairs ID</div>
                                </div>
                            </div> -->
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="sex" class="form-label">Sex *</label>
                                    <select class="form-select" id="sex" name="sex" required>
                                        <option value="">Select Sex</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="birthdate" class="form-label">Birthdate</label>
                                    <input type="date" class="form-control" id="birthdate" name="birthdate">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="barangay" class="form-label">Barangay *</label>
                                    <select class="form-select" id="barangay" name="barangay" required>
                                        <option value="">Select Barangay</option>
                                        <?php foreach ($predefined_barangays as $barangay_option): ?>
                                            <option value="<?php echo htmlspecialchars($barangay_option); ?>"><?php echo htmlspecialchars($barangay_option); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" value="KORONADAL CITY" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="province" class="form-label">Province *</label>
                                    <input type="text" class="form-control" id="province" name="province" value="SOUTH COTABATO" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="picture" class="form-label">Picture</label>
                            <input type="file" class="form-control" id="picture" name="picture" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_person" class="btn btn-primary">Add Person</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Excel Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Excel File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" action="upload_excel.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">Select Excel File</label>
                            <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls" required>
                            <div class="form-text">
                                File should contain columns: ID Number, Name, Sex, Barangay, City, Province, Birthdate
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Person Modal -->
    <div class="modal fade" id="editPersonModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Person</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_id_number" class="form-label">OSCA ID Number</label>
                                    <input type="text" class="form-control" id="edit_id_number" name="id_number" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="edit_name" name="name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_sex" class="form-label">Sex</label>
                                    <select class="form-select" id="edit_sex" name="sex" required>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_birthdate" class="form-label">Birthdate</label>
                                    <input type="date" class="form-control" id="edit_birthdate" name="birthdate">
                                    <div class="form-text">Leave empty if unknown</div>
                                </div>
                            </div>
                            <!-- <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_osca_id" class="form-label">OSCA ID</label>
                                    <input type="text" class="form-control" id="edit_osca_id" name="osca_id" placeholder="Optional">
                                    <div class="form-text">Office of Senior Citizens Affairs ID (if applicable)</div>
                                </div>
                            </div> -->
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_barangay" class="form-label">Barangay</label>
                                    <select class="form-select" id="edit_barangay" name="barangay" required>
                                        <option value="">Select Barangay</option>
                                        <?php foreach ($predefined_barangays as $barangay_option): ?>
                                            <option value="<?php echo htmlspecialchars($barangay_option); ?>"><?php echo htmlspecialchars($barangay_option); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="edit_city" name="city" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_province" class="form-label">Province</label>
                                    <input type="text" class="form-control" id="edit_province" name="province" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_picture" class="form-label">Update Picture</label>
                                    <input type="file" class="form-control" id="edit_picture" name="picture" accept="image/*">
                                    <div class="form-text">Leave empty to keep current picture</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="edit_deceased" name="deceased">
                                        <label class="form-check-label" for="edit_deceased">
                                            Mark as Deceased
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3" id="edit_deceased_date_group" style="display: none;">
                                    <label for="edit_deceased_date" class="form-label">Date of Death</label>
                                    <input type="date" class="form-control" id="edit_deceased_date" name="deceased_date">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_person" class="btn btn-warning">Update Person</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Validation Result Modal -->
    <div class="modal fade" id="validationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Senior Citizen Validation Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="validationResult">
                    <!-- Validation results will be displayed here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printValidation()">Print</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generateID(personId) {
            window.open('generate_id.php?id=' + personId, '_blank');
        }
        
        function deletePerson(personId) {
            // Check if user is admin
            <?php if ($user_role !== 'admin'): ?>
                alert('You do not have permission to delete records.');
                return;
            <?php endif; ?>
            
            if (confirm('Are you sure you want to delete this person?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="id" value="' + personId + '"><input type="hidden" name="delete_person" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function editPerson(personId) {
            // Load person data into modal
            fetch('get_person.php?id=' + personId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_id_number').value = data.id_number;
                    document.getElementById('edit_name').value = data.name;
                    document.getElementById('edit_sex').value = data.sex;
                    document.getElementById('edit_barangay').value = data.barangay;
                    document.getElementById('edit_city').value = data.city;
                    document.getElementById('edit_province').value = data.province;
                    document.getElementById('edit_birthdate').value = data.birthdate;
                    // document.getElementById('edit_osca_id').value = data.osca_id || '';
                    document.getElementById('edit_deceased').checked = data.deceased == 1;
                    document.getElementById('edit_deceased_date').value = data.deceased_date;
                    
                    // Show/hide deceased date field
                    toggleDeceasedDate();
                    
                    // Show modal
                    new bootstrap.Modal(document.getElementById('editPersonModal')).show();
                })
                .catch(error => console.error('Error:', error));
        }
        
        function toggleDeceasedDate() {
            const deceasedCheckbox = document.getElementById('edit_deceased');
            const deceasedDateField = document.getElementById('edit_deceased_date_group');
            
            if (deceasedCheckbox.checked) {
                deceasedDateField.style.display = 'block';
            } else {
                deceasedDateField.style.display = 'none';
            }
        }
        
        function applyFilters() {
            filterTable();
        }

        function clearFilters() {
            const searchInput = document.getElementById('searchInput');
            const barangayFilter = document.getElementById('barangayFilter');
            const sexFilter = document.getElementById('sexFilter');

            if (searchInput) searchInput.value = '';
            if (barangayFilter) barangayFilter.selectedIndex = 0;
            if (sexFilter) sexFilter.selectedIndex = 0;
            filterTable();
        }

        function filterTable() {
            const searchInput = document.getElementById('searchInput');
            const barangayFilter = document.getElementById('barangayFilter');
            const sexFilter = document.getElementById('sexFilter');
            const table = document.getElementById('personsTable');
            const searchValue = searchInput ? searchInput.value.trim().toLowerCase() : '';
            const barangayValue = barangayFilter ? barangayFilter.value.trim().toLowerCase() : '';
            const sexValue = sexFilter ? sexFilter.value : '';
            let visibleCount = 0;

            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                const rowBarangayCell = row.querySelector('td:nth-child(6)');
                const rowSexCell = row.querySelector('td:nth-child(3)');
                const rowBarangay = rowBarangayCell ? rowBarangayCell.textContent.trim().toLowerCase() : '';
                const rowSex = rowSexCell ? rowSexCell.textContent.trim() : '';
                const matchesSearch = !searchValue || rowText.includes(searchValue);
                const matchesBarangay = !barangayValue || rowBarangay === barangayValue;
                const matchesSex = !sexValue || rowSex === sexValue;
                const show = matchesSearch && matchesBarangay && matchesSex;
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            const infoText = document.getElementById('filterInfoText');
            if (infoText) {
                infoText.textContent = visibleCount + ' of ' + rows.length + ' rows shown';
            }
        }
        
        function validateSenior() {
            const idNumber = document.getElementById('manualIdInput').value.trim();
            
            if (!idNumber) {
                alert('Please enter an ID number');
                return;
            }
            
            fetch('validate_senior.php?id=' + encodeURIComponent(idNumber))
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showValidationError(data.error);
                    } else {
                        showValidationResult(data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showValidationError('An error occurred while validating the senior citizen');
                });
        }
        
        function startQRScan() {
            // Check if camera is available
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
                    .then(function(stream) {
                        showQRScanner(stream);
                    })
                    .catch(function(error) {
                        console.error('Camera access denied:', error);
                        alert('Camera access is required for QR code scanning. Please allow camera access and try again.');
                    });
            } else {
                alert('QR code scanning is not supported on this device');
            }
        }
        
        function showQRScanner(stream) {
            // Create QR scanner modal
            const scannerModal = document.createElement('div');
            scannerModal.className = 'modal fade';
            scannerModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Scan QR Code</h5>
                            <button type="button" class="btn-close" onclick="closeQRScanner()"></button>
                        </div>
                        <div class="modal-body text-center">
                            <video id="qrVideo" width="300" height="300" autoplay></video>
                            <div class="mt-3">
                                <p class="text-muted">Position the QR code within the frame</p>
                                <button type="button" class="btn btn-secondary" onclick="closeQRScanner()">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(scannerModal);
            
            // Show modal
            const modal = new bootstrap.Modal(scannerModal);
            modal.show();
            
            // Start video stream
            const video = document.getElementById('qrVideo');
            video.srcObject = stream;
            
            // Initialize QR code scanner (simplified version)
            // In a real implementation, you would use a library like jsQR
            setTimeout(() => {
                // For demo purposes, simulate QR code detection
                const simulatedId = prompt('QR Code detected! Enter ID number for demo:');
                if (simulatedId) {
                    document.getElementById('manualIdInput').value = simulatedId;
                    closeQRScanner();
                    validateSenior();
                }
            }, 3000);
        }
        
        function closeQRScanner() {
            // Stop video stream
            const video = document.getElementById('qrVideo');
            if (video && video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
            }
            
            // Remove modal
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (modal.querySelector('#qrVideo')) {
                    bootstrap.Modal.getInstance(modal).hide();
                    modal.remove();
                }
            });
        }
        
        function showValidationResult(data) {
            const resultHtml = `
                <div class="row">
                    <div class="col-md-4 text-center">
                        ${data.picture ? `<img src="${data.picture}" class="img-fluid rounded mb-3" style="max-height: 200px;">` : '<i class="bi bi-person-circle" style="font-size: 8rem;"></i>'}
                        ${data.qr_code ? `<img src="${data.qr_code}" class="img-fluid" style="max-height: 100px;">` : ''}
                    </div>
                    <div class="col-md-8">
                        <h4 class="mb-3">Senior Citizen Information</h4>
                        <div class="row">
                            <div class="col-6"><strong>ID Number:</strong></div>
                            <div class="col-6">${data.id_number}</div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Name:</strong></div>
                            <div class="col-6">${data.name}</div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Sex:</strong></div>
                            <div class="col-6">${data.sex}</div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Age:</strong></div>
                            <div class="col-6">${data.age}</div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Birthdate:</strong></div>
                            <div class="col-6">${data.birthdate || 'N/A'}</div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Barangay:</strong></div>
                            <div class="col-6">${data.barangay}</div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>City:</strong></div>
                            <div class="col-6">${data.city}</div>
                        </div>
                        <div class="row">
                            <div class="col-6"><strong>Status:</strong></div>
                            <div class="col-6">
                                ${data.deceased ? '<span class="badge bg-danger">Deceased</span>' : '<span class="badge bg-success">Active</span>'}
                            </div>
                        </div>
                        ${data.deceased_date ? `
                        <div class="row">
                            <div class="col-6"><strong>Date of Death:</strong></div>
                            <div class="col-6">${data.deceased_date}</div>
                        </div>
                        ` : ''}
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <strong>Validation Status: VERIFIED</strong><br>
                                    <small>This senior citizen is registered in the system.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('validationResult').innerHTML = resultHtml;
            new bootstrap.Modal(document.getElementById('validationModal')).show();
        }
        
        function showValidationError(error) {
            const resultHtml = `
                <div class="text-center">
                    <i class="bi bi-x-circle text-danger" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">Validation Failed</h4>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> ${error}
                    </div>
                    <p class="text-muted">Please check the ID number and try again.</p>
                </div>
            `;
            
            document.getElementById('validationResult').innerHTML = resultHtml;
            new bootstrap.Modal(document.getElementById('validationModal')).show();
        }
        
        function clearValidation() {
            document.getElementById('manualIdInput').value = '';
        }
        
        function printValidation() {
            window.print();
        }
        
        function validateAge() {
            const birthdate = document.getElementById('birthdate').value;
            
            if (birthdate) {
                const birthDate = new Date(birthdate);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                const dayDiff = today.getDate() - birthDate.getDate();
                
                // Adjust age if birthday hasn't occurred yet this year
                const actualAge = (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) ? age - 1 : age;
                
                if (actualAge < 60) {
                    alert('Person must be 60 years or older to be added. Current age: ' + actualAge + ' years.');
                    return false;
                }
            }
            
            return true;
        }
        
        // Initialize event listener
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('edit_deceased').addEventListener('change', toggleDeceasedDate);
            
            // Auto-hide positioned alerts after 5 seconds
            const positionedAlerts = document.querySelectorAll('.alert-positioned');
            positionedAlerts.forEach(alert => {
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.classList.add('fade-out');
                        setTimeout(() => {
                            if (alert.parentNode) {
                                alert.remove();
                            }
                        }, 300);
                    }
                }, 5000);
            });
            
            // Handle manual close with animation
            const closeButtons = document.querySelectorAll('.alert-positioned .btn-close');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const alert = this.closest('.alert-positioned');
                    if (alert) {
                        alert.classList.add('fade-out');
                        setTimeout(() => {
                            if (alert.parentNode) {
                                alert.remove();
                            }
                        }, 300);
                    }
                });
            });

            // Auto-filter table as user types (debounced)
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                let searchTimer = null;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => {
                        filterTable();
                    }, 250);
                });
            }
        });
    </script>
</body>
</html>

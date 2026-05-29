<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!-- Sidebar -->
<div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4 logo-header">
            <img src="city_logo.png" alt="City Logo" onerror="this.style.display='none'">
            <h5 class="text-white">Senior Citizen Management System</h5>
            <p class="text-white-50">Welcome, <?php echo $_SESSION['username']; ?> 
                <?php 
                $role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'staff';
                echo '<span class="badge bg-' . ($role === 'admin' ? 'danger' : 'info') . '">' . strtoupper($role) . '</span>';
                ?>
            </p>
        </div>
        
        <ul class="nav flex-column">
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="bi bi-people"></i> User Management
                </a>
            </li>
            <?php endif; ?>
             <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'qr_scanner.php' ? 'active' : ''; ?>" href="qr_scanner.php">
                    <i class="bi bi-qr-code-scan"></i> QR Scanner Validation
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="bi bi-house-door"></i> Senior Citizen List
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'export.php' ? 'active' : ''; ?>" href="export.php">
                    <i class="bi bi-download"></i> Export Records
                </a>
            </li>
            <hr>
            <hr>
            <li class="nav-item">
                <a class="nav-link text-white" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</div>

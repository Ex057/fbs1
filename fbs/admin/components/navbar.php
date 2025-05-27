<?php
// Define page titles for different sections
$pages = [
    "main.php" => "Dashboard",
    "records.php" => "Survey Submissions",
    "submissions.php" => "Submissions",
    "manage_form.php" => "Form Builder",
    "survey.php" => "Survey Manager",
    "settings.php" => "Settings",
     "dashbard.php" => "Survey Dashboard"
];
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pages[$currentPage] ?? "Admin Panel";
?>

<nav class="navbar navbar-main navbar-expand-lg px-3 bg-white navbar-fixed-top shadow-sm" id="navbarBlur">
    <div class="container-fluid position-relative"> <!-- Added position-relative -->
        <!-- Sidebar Toggle Buttons -->
        <button class="btn btn-link px-0 me-2 d-lg-none" id="sidebarToggle">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <button class="btn btn-link px-0 me-3 d-none d-lg-block" id="sidebarToggleDesktop">
            <i class="fas fa-bars fa-lg"></i>
        </button>

        <!-- Page Title -->
        <div class="d-flex align-items-center">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="main">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= $pageTitle ?></li>
                </ol>
                <h5 class="font-weight-bolder mb-0 text-dark"><?= $pageTitle ?></h5>
            </nav>
        </div>

        <!-- Right-aligned navbar items -->
        <div class="ms-auto d-flex align-items-center">
            <!-- Quick Actions Dropdown - Wrapped in position-relative container -->
            <div class="position-relative me-3 d-none d-sm-block">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="quickActions" data-bs-toggle="dropdown" aria-expanded="false" data-bs-display="static">
                    <i class="fas fa-bolt me-1"></i> Quick Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end position-absolute" aria-labelledby="quickActions" style="z-index: 1060;">
                    <li><a class="dropdown-item" href="manage_form"><i class="fas fa-plus-circle text-success me-2"></i> Create New Form</a></li>
                    <li><a class="dropdown-item" href="records"><i class="fas fa-list-alt text-primary me-2"></i> View Submissions</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="preview_form?survey_id=30"><i class="fas fa-cog text-info me-2"></i> Publish Survey</a></li>
                </ul>
            </div>

            <!-- User Dropdown -->
            <div class="dropdown position-relative">
                <a href="#" class="avatar avatar-sm rounded-circle d-flex align-items-center justify-content-center" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-display="static">
                    <img src="argon-dashboard-master/assets/img/admin.png" alt="User" class="avatar-img">
                    <span class="ms-2 d-none d-lg-inline-block text-dark small"><?= $_SESSION['admin_username'] ?? 'Admin' ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end position-absolute" aria-labelledby="userDropdown" style="z-index: 1060;">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user-circle me-2"></i> My Profile</a></li>
                    <li><a class="dropdown-item" href="register.php"><i class="fas fa-cog me-2"></i> Register</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="logout.php">
                            <button class="dropdown-item" name="logout"><i class="fas fa-sign-out-alt me-2"></i> Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<style>
    /* Navbar styling */
    .navbar-main {
        background: linear-gradient(87deg, #f8f9fa 0, #ffffff 100%) !important;
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        padding: 0.5rem 1rem;
        position: relative;
        z-index: 1050; /* Higher than most content */
    }
    
    .dropdown-menu {
        border: none;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    /* Ensure dropdowns appear above all content */
    .dropdown-menu.position-absolute {
        position: absolute !important;
        z-index: 1060 !important; /* Higher than navbar */
    }
    
    /* Make sure main content doesn't interfere */
    .main-content {
        position: relative;
        z-index: 1; /* Lower than navbar */
    }
    
    @media (max-width: 991.98px) {
        .navbar-main {
            padding: 0.5rem;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap dropdowns with static display
        const dropdownElements = document.querySelectorAll('.dropdown-toggle');
        dropdownElements.forEach(el => {
            el.addEventListener('show.bs.dropdown', function() {
                // Force the dropdown to display above content
                const dropdownMenu = this.nextElementSibling;
                dropdownMenu.style.position = 'absolute';
                dropdownMenu.style.zIndex = '1060';
            });
        });
        
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleDesktop = document.getElementById('sidebarToggleDesktop');
        const sidebar = document.getElementById('sidenav-main');
        
        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
        
        sidebarToggle.addEventListener('click', toggleSidebar);
        sidebarToggleDesktop.addEventListener('click', toggleSidebar);
    });
</script>
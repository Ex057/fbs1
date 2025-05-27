<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$menuItems = [
    [
        'title' => 'Dashboard',
        'icon' => 'fa-tachometer-alt',
        'link' => 'main',
        'color' => 'primary',
        'pages' => ['main.php']
    ],
    [
        'title' => 'Manage Form',
        'icon' => 'fa-wpforms',
        'link' => 'manage_form',
        'color' => 'info',
        'pages' => ['manage_form.php']
    ],
    [
        'title' => 'Survey Dashboard',
        'icon' => 'fa-chart-pie',
        'link' => 'dashbard',
        'color' => 'warning',
        'pages' => ['dashbard.php']
    ],
    [
        'title' => 'Submissions',
        'icon' => 'fa-paper-plane',
        'link' => 'records',
        'color' => 'success',
        'pages' => ['records.php']
    ],
    [
        'title' => 'Survey',
        'icon' => 'fa-poll',
        'link' => 'survey',
        'color' => 'danger',
        'pages' => ['survey.php']
    ],
    [
        'title' => 'Settings',
        'icon' => 'fa-cog',
        'link' => 'settings',
        'color' => 'danger',
        'pages' => ['settings.php']
    ]
];
?>

<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-0 bg-white fixed-start" id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" 
           id="iconSidenav" aria-label="Close sidebar"></i>
        
        <a class="navbar-brand m-0 text-center w-100" href="#">
            <img src="argon-dashboard-master/assets/img/dhis.png" 
                 class="navbar-brand-img h-100" 
                 alt="logo"
                 style="max-height: 3.5rem;">
            <span class="ms-1 font-weight-bold fs-5 d-block mt-2">Admin Panel</span>
        </a>
    </div>
    
    <hr class="horizontal dark mt-0 mb-1">
    
    <div class="collapse navbar-collapse w-auto h-100" id="sidenav-collapse-main">
        <div class="nav-scroller">
            <ul class="navbar-nav">
                <?php foreach ($menuItems as $item): ?>
                    <?php $isActive = in_array($currentPage, $item['pages']); ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive ? 'active bg-gradient-'.$item['color'] : '' ?>" href="<?= $item['link'] ?>">
                            <div class="icon icon-shape icon-sm shadow border-radius-md bg-white text-center me-2 d-flex align-items-center justify-content-center">
                                <i class="fas <?= $item['icon'] ?> text-<?= $item['color'] ?> text-sm opacity-10"></i>
                            </div>
                            <span class="nav-link-text ms-1"><?= $item['title'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="mt-auto p-3 user-profile-section">
                <hr class="horizontal dark mb-3">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm me-2">
                        <img src="argon-dashboard-master/assets/img/admin.png" 
                             alt="User" 
                             class="avatar-img rounded-circle">
                    </div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold"><?= $_SESSION['admin_username'] ?? 'Admin' ?></span>
                        <small class="text-muted">Administrator</small>
                    </div>
                </div>
                <div class="mt-2 d-grid">
                    <a href="logout.php" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Backdrop for mobile (only shown when sidebar is open) -->
<div class="sidenav-backdrop"></div>

<style>
    /* Layout structure */
    .sidenav {
        width: 250px;
        min-height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1030;
        transition: transform 0.3s ease, width 0.3s ease;
    }
    
    /* Main content should have left margin */
    .main-content {
        margin-left: 250px;
        transition: margin-left 0.3s ease;
    }
    
    /* Collapsed state */
    .sidenav.collapsed {
        width: 80px;
    }
    
    .sidenav.collapsed ~ .main-content {
        margin-left: 80px;
    }
    
    .sidenav.collapsed .nav-link-text,
    .sidenav.collapsed .navbar-brand span,
    .sidenav.collapsed .user-profile-section {
        display: none !important;
    }
    
    /* Mobile behavior */
    @media (max-width: 1199.98px) {
        .sidenav:not(.collapsed) {
            transform: translateX(0);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .sidenav {
            transform: translateX(-100%);
        }
        
        .sidenav-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1029;
        }
        
        .sidenav:not(.collapsed) + .sidenav-backdrop {
            display: block;
        }
        
        .main-content {
            margin-left: 0 !important;
        }
    }
    
    /* Nav item styling */
    .nav-item {
        margin-bottom: 0.25rem;
    }
    
    .nav-link {
        border-radius: 0.375rem;
        padding: 0.75rem 1rem;
        margin: 0 0.5rem;
    }
    
    .nav-link.active {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .icon-shape {
        width: 32px;
        height: 32px;
    }
    
    /* Scroller for long menus */
    .nav-scroller {
        height: calc(100vh - 120px);
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidenav = document.getElementById('sidenav-main');
        const iconSidenav = document.getElementById('iconSidenav');
        const backdrop = document.querySelector('.sidenav-backdrop');
        
        // Toggle sidebar on mobile
        iconSidenav.addEventListener('click', function() {
            sidenav.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidenav.classList.contains('collapsed'));
        });
        
        // Close sidebar when clicking backdrop
        backdrop.addEventListener('click', function() {
            sidenav.classList.add('collapsed');
            localStorage.setItem('sidebarCollapsed', true);
        });
        
        // Load saved state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidenav.classList.add('collapsed');
        }
        
        // Auto-collapse on mobile by default
        if (window.innerWidth < 1200) {
            sidenav.classList.add('collapsed');
        }
    });
</script>
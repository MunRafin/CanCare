<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../loginPC.html");
    exit();
}

$page = $_GET['page'] ?? 'dashboard';

// Allowed page keys (without 'admin_' prefix)
$allowed_pages = [
    'dashboard',
    'users',
    'doctors',
    'patients',
    'appointments',
    'prescriptions',
    'medicines',
    'fooddata',
    'exercises',
    'reports',
    'settings'
];

// Construct the corresponding file name
$page_file = "admin_" . $page . ".php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CanCare | Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    * {
      margin: 0; 
      padding: 0; 
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      display: flex;
      min-height: 100vh;
      background: #f8fafb;
      color: #1f2937;
    }

    /* Sidebar Styles */
    aside.sidebar {
      width: 280px;
      background: linear-gradient(180deg, #0f172a 0%, #1e293b 50%, #334155 100%);
      display: flex;
      flex-direction: column;
      position: relative;
      box-shadow: 8px 0 30px rgba(0, 0, 0, 0.15);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: hidden;
    }

    .sidebar::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.08) 0%,
        rgba(255, 255, 255, 0.02) 50%,
        rgba(0, 0, 0, 0.05) 100%
      );
      pointer-events: none;
      z-index: 1;
    }

    .sidebar-header {
      padding: 32px 28px;
      display: flex;
      flex-direction: column;
      align-items: center;
      border-bottom: 1px solid rgba(148, 163, 184, 0.15);
      position: relative;
      z-index: 2;
      background: rgba(15, 23, 42, 0.8);
      backdrop-filter: blur(10px);
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 24px;
    }

    .brand-icon {
      width: 46px;
      height: 46px;
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 22px;
      box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
      transition: all 0.3s ease;
    }

    .brand-icon:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 25px rgba(245, 158, 11, 0.4);
    }

    .brand-text {
      color: white;
      font-size: 26px;
      font-weight: 800;
      letter-spacing: -0.5px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .profile-section {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .profile-image {
      width: 85px;
      height: 85px;
      border-radius: 50%;
      border: 3px solid rgba(245, 158, 11, 0.4);
      margin-bottom: 16px;
      object-fit: cover;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .profile-image:hover {
      transform: scale(1.08);
      border-color: rgba(245, 158, 11, 0.8);
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
    }

    .profile-info h3 {
      color: white;
      font-size: 19px;
      font-weight: 700;
      margin-bottom: 6px;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    }

    .profile-info p {
      color: rgba(148, 163, 184, 0.9);
      font-size: 14px;
      font-weight: 500;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    .sidebar-content {
      flex: 1;
      padding: 28px 24px;
      position: relative;
      z-index: 2;
      overflow-y: auto;
    }

    .menu-section {
      margin-bottom: 36px;
    }

    .menu-title {
      color: rgba(148, 163, 184, 0.7);
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      margin-bottom: 18px;
      padding-left: 16px;
    }

    .sidebar-content ul {
      list-style: none;
    }

    .sidebar-content > ul > li {
      margin-bottom: 6px;
    }

    .sidebar-content > ul > li > a {
      display: flex;
      align-items: center;
      gap: 16px;
      text-decoration: none;
      color: rgba(203, 213, 225, 0.9);
      padding: 16px 20px;
      border-radius: 14px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      font-weight: 500;
      font-size: 15px;
      position: relative;
      overflow: hidden;
      backdrop-filter: blur(10px);
    }

    .sidebar-content > ul > li > a::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(245, 158, 11, 0.1), transparent);
      transition: left 0.6s ease;
    }

    .sidebar-content a:hover::before {
      left: 100%;
    }

    .sidebar-content a:hover {
      background: rgba(245, 158, 11, 0.12);
      color: white;
      transform: translateX(8px);
      box-shadow: 0 6px 20px rgba(245, 158, 11, 0.15);
    }

    .sidebar-content a.active {
      background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.15) 100%);
      color: white;
      box-shadow: 0 8px 25px rgba(245, 158, 11, 0.2);
      border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .sidebar-content a.active::after {
      content: '';
      position: absolute;
      right: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 45%;
      background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
      border-radius: 2px 0 0 2px;
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
    }

    .menu-icon {
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      color: inherit;
      transition: all 0.3s ease;
    }

    .sidebar-content a:hover .menu-icon {
      color: #f59e0b;
      transform: scale(1.1);
    }

    .sidebar-content a.active .menu-icon {
      color: #f59e0b;
    }

    /* Main Content Wrapper */
    .main-wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: #f8fafb;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: white;
      padding: 24px 32px;
      border-bottom: 1px solid #e5e7eb;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      backdrop-filter: blur(10px);
      position: relative;
      z-index: 10;
    }

    .topbar::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
    }

    .topbar-left {
      display: flex;
      align-items: center;
      gap: 24px;
    }

    .page-title {
      font-size: 28px;
      font-weight: 700;
      color: #111827;
      background: linear-gradient(135deg, #111827 0%, #374151 100%);
      background-clip: text;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .breadcrumb {
      color: #6b7280;
      font-size: 14px;
      font-weight: 500;
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .topbar-right a {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: #6b7280;
      padding: 12px 18px;
      border-radius: 10px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      font-weight: 500;
      font-size: 14px;
      background: rgba(249, 250, 251, 0.8);
      border: 1px solid rgba(229, 231, 235, 0.8);
      backdrop-filter: blur(10px);
    }

    .topbar-right a:hover {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(245, 158, 11, 0.25);
      border-color: transparent;
    }

    .content {
      padding: 32px;
      flex: 1;
      overflow-y: auto;
      background: #f8fafb;
    }

    /* Mobile Toggle Button */
    .mobile-toggle {
      display: none;
      position: fixed;
      top: 24px;
      left: 24px;
      z-index: 1000;
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: white;
      border: none;
      padding: 14px;
      border-radius: 12px;
      cursor: pointer;
      box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
      transition: all 0.3s ease;
    }

    .mobile-toggle:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(245, 158, 11, 0.4);
    }

    /* Responsive Styles */
    @media (max-width: 1024px) {
      aside.sidebar {
        width: 260px;
      }

      .content {
        padding: 24px;
      }
    }

    @media (max-width: 768px) {
      .mobile-toggle {
        display: block;
      }

      aside.sidebar {
        position: fixed;
        left: -280px;
        top: 0;
        height: 100vh;
        z-index: 999;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }

      aside.sidebar.mobile-open {
        left: 0;
      }

      .main-wrapper {
        width: 100%;
      }

      .topbar {
        padding-left: 80px;
        padding: 20px 24px 20px 80px;
      }

      .content {
        padding: 20px;
      }

      .page-title {
        font-size: 24px;
      }
    }

    @media (max-width: 480px) {
      .topbar {
        flex-direction: column;
        gap: 16px;
        padding: 16px 20px 16px 70px;
        align-items: flex-start;
      }

      .topbar-right {
        gap: 8px;
        width: 100%;
        justify-content: flex-end;
      }

      .topbar-right a {
        padding: 10px 14px;
        font-size: 13px;
      }

      .content {
        padding: 16px;
      }

      .page-title {
        font-size: 22px;
      }
    }

    /* Loading Animation */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(255, 255, 255, 0.95);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      backdrop-filter: blur(8px);
    }

    .loading-spinner {
      width: 50px;
      height: 50px;
      border: 4px solid rgba(245, 158, 11, 0.2);
      border-top: 4px solid #f59e0b;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* Scrollbar Styling */
    .sidebar-content::-webkit-scrollbar {
      width: 6px;
    }

    .sidebar-content::-webkit-scrollbar-track {
      background: rgba(148, 163, 184, 0.1);
      border-radius: 3px;
    }

    .sidebar-content::-webkit-scrollbar-thumb {
      background: rgba(245, 158, 11, 0.4);
      border-radius: 3px;
      transition: background 0.3s ease;
    }

    .sidebar-content::-webkit-scrollbar-thumb:hover {
      background: rgba(245, 158, 11, 0.6);
    }

    /* Enhanced Visual Effects */
    .sidebar-content a {
      position: relative;
    }

    .sidebar-content a:hover {
      backdrop-filter: blur(10px);
    }

    .sidebar-content a.active {
      backdrop-filter: blur(15px);
    }

    /* Mobile Overlay */
    @media (max-width: 768px) {
      .mobile-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 998;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .mobile-overlay.show {
        display: block;
        opacity: 1;
      }
    }
  </style>
</head>
<body>
  <!-- Mobile Toggle Button -->
  <button class="mobile-toggle" onclick="toggleMobileSidebar()">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Mobile Overlay -->
  <div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileSidebar()"></div>

  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
  </div>

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-brand">
        <div class="brand-icon">
          <i class="fas fa-user-shield"></i>
        </div>
        <div class="brand-text">CanCare</div>
      </div>
      
      <div class="profile-section">
        <img src="../zphotos/admin_img.png" alt="Admin Picture" class="profile-image">
        <div class="profile-info">
          <h3><?= htmlspecialchars($_SESSION['name'] ?? 'Administrator') ?></h3>
          <p>Admin Panel</p>
        </div>
      </div>
    </div>

    <div class="sidebar-content">
      <div class="menu-section">
        <div class="menu-title">Main Dashboard</div>
        <ul>
          <li>
            <a href="?page=dashboard" class="<?= $page == 'dashboard' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-chart-line"></i></div>
              Dashboard
            </a>
          </li>
          <li>
            <a href="?page=users" class="<?= $page == 'users' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-users"></i></div>
              User Management
            </a>
          </li>
        </ul>
      </div>

      <div class="menu-section">
        <div class="menu-title">Healthcare Management</div>
        <ul>
          <li>
            <a href="?page=doctors" class="<?= $page == 'doctors' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-user-md"></i></div>
              Doctors
            </a>
          </li>
          <li>
            <a href="?page=patients" class="<?= $page == 'patients' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-hospital-user"></i></div>
              Patients
            </a>
          </li>
          <li>
            <a href="?page=appointments" class="<?= $page == 'appointments' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-calendar-check"></i></div>
              Appointments
            </a>
          </li>
          <li>
            <a href="?page=prescriptions" class="<?= $page == 'prescriptions' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-prescription"></i></div>
              Prescriptions
            </a>
          </li>
        </ul>
      </div>

      <div class="menu-section">
        <div class="menu-title">Data Management</div>
        <ul>
          <li>
            <a href="?page=medicines" class="<?= $page == 'medicines' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-pills"></i></div>
              Medicines
            </a>
          </li>
          <li>
            <a href="?page=fooddata" class="<?= $page == 'fooddata' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-utensils"></i></div>
              Food Database
            </a>
          </li>
          <li>
            <a href="?page=exercises" class="<?= $page == 'exercises' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-dumbbell"></i></div>
              Exercise Library
            </a>
          </li>
        </ul>
      </div>

      <div class="menu-section">
        <div class="menu-title">Analytics & Settings</div>
        <ul>
          <li>
            <a href="?page=reports" class="<?= $page == 'reports' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-chart-bar"></i></div>
              Analytics & Reports
            </a>
          </li>
          <li>
            <a href="?page=settings" class="<?= $page == 'settings' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-cogs"></i></div>
              System Settings
            </a>
          </li>
        </ul>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="main-wrapper">
    <!-- Top Bar -->
    <div class="topbar">
      <div class="topbar-left">
        <div class="page-title">
          <?php
            $page_titles = [
              'dashboard' => 'Dashboard Overview',
              'users' => 'User Management',
              'doctors' => 'Doctor Management',
              'patients' => 'Patient Management',
              'appointments' => 'Appointment Management',
              'prescriptions' => 'Prescription Management',
              'medicines' => 'Medicine Database',
              'fooddata' => 'Food Database',
              'exercises' => 'Exercise Library',
              'reports' => 'Analytics & Reports',
              'settings' => 'System Settings'
            ];
            echo $page_titles[$page] ?? 'Unknown Page';
          ?>
        </div>
        <div class="breadcrumb">
          <i class="fas fa-chevron-right" style="margin: 0 8px; font-size: 12px;"></i>
          Administration Panel
        </div>
      </div>
      
      <div class="topbar-right">
        <a href="?page=settings" onclick="showLoading()">
          <i class="fas fa-cog"></i>
          Settings
        </a>
        <a href="../logout.php" onclick="showLoading()">
          <i class="fas fa-sign-out-alt"></i>
          Logout
        </a>
      </div>
    </div>

    <div class="content">
      <?php
        if (in_array($page, $allowed_pages) && file_exists($page_file)) {
            include $page_file;
        } else {
            echo "<div style='text-align: center; padding: 80px 20px; background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>";
            echo "<div style='background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;'>";
            echo "<i class='fas fa-exclamation-triangle' style='font-size: 3rem; color: #d97706;'></i>";
            echo "</div>";
            echo "<h1 style='font-size: 2.5rem; margin-bottom: 12px; color: #111827; font-weight: 700;'>404 - Page Not Found</h1>";
            echo "<p style='color: #6b7280; font-size: 1.1rem; max-width: 500px; margin: 0 auto;'>The administrative page you requested could not be found. Please check the URL or contact support if this error persists.</p>";
            echo "<div style='margin-top: 32px;'>";
            echo "<a href='?page=dashboard' style='display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 14px 28px; border-radius: 12px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;' onmouseover='this.style.transform=\"translateY(-2px)\"; this.style.boxShadow=\"0 8px 25px rgba(245, 158, 11, 0.3)\"' onmouseout='this.style.transform=\"translateY(0)\"; this.style.boxShadow=\"none\"'>";
            echo "<i class='fas fa-home'></i> Return to Dashboard</a>";
            echo "</div>";
            echo "</div>";
        }
      ?>
    </div>
  </div>

<script>
  function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.toggle('mobile-open');
    
    if (sidebar.classList.contains('mobile-open')) {
      overlay.classList.add('show');
    } else {
      overlay.classList.remove('show');
    }
  }

  function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('show');
  }

  function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'flex';
    
    // Hide loading after 1.5 seconds max
    setTimeout(() => {
      overlay.style.display = 'none';
    }, 1500);
  }

  // Close mobile sidebar when clicking outside
  document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const mobileToggle = document.querySelector('.mobile-toggle');
    const overlay = document.getElementById('mobileOverlay');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(e.target) && 
        !mobileToggle.contains(e.target) && 
        sidebar.classList.contains('mobile-open')) {
      closeMobileSidebar();
    }
  });

  // Handle window resize
  window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (window.innerWidth > 768) {
      sidebar.classList.remove('mobile-open');
      overlay.classList.remove('show');
    }
  });

  // Hide loading on page load
  window.addEventListener('load', function() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'none';
  });

  // Add smooth scroll behavior
  document.documentElement.style.scrollBehavior = 'smooth';

  // Enhanced hover effects for menu items
  document.querySelectorAll('.sidebar-content a').forEach(link => {
    link.addEventListener('mouseenter', function() {
      this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
    });
    
    link.addEventListener('mouseleave', function() {
      this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
    });
  });
</script>

</body>
</html>
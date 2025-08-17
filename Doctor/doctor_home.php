<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../loginPC.html");
    exit();
}

$page = $_GET['page'] ?? 'dashboard';

// Allowed page keys (without 'doctor_' prefix)
$allowed_pages = [
    'dashboard', 
    'appointments', 
    'prescribe', 
    'reports', 
    'patients',
    'schedule',
    'consultations',
    'analytics',
    'profile',
    'notes',
    'calendar',
    'availability'
];

// Construct the corresponding file name
$page_file = "doctor_" . $page . ".php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CanCare | Doctor Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    * {
      margin: 0; 
      padding: 0; 
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      display: flex;
      min-height: 100vh;
      background: #f8fafc;
      color: #1e293b;
    }

    /* Sidebar Styles - Medical Theme */
    aside.sidebar {
      width: 280px;
      background: linear-gradient(180deg, #059669 0%, #047857 100%);
      display: flex;
      flex-direction: column;
      position: relative;
      box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }

    .sidebar::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(180deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
      pointer-events: none;
    }

    .sidebar-header {
      padding: 30px 25px;
      display: flex;
      flex-direction: column;
      align-items: center;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      position: relative;
      z-index: 2;
    }

    .profile-section {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .profile-image {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      border: 4px solid rgba(255,255,255,0.2);
      margin-bottom: 15px;
      object-fit: cover;
      transition: all 0.3s ease;
    }

    .profile-image:hover {
      transform: scale(1.05);
      border-color: rgba(255,255,255,0.4);
    }

    .profile-info h3 {
      color: white;
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 5px;
    }

    .profile-info p {
      color: rgba(255,255,255,0.7);
      font-size: 14px;
      font-weight: 400;
    }

    .doctor-badge {
      background: rgba(255,255,255,0.2);
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      margin-top: 8px;
      display: inline-block;
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
    }

    .brand-icon {
      width: 40px;
      height: 40px;
      background: rgba(255,255,255,0.15);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
    }

    .brand-text {
      color: white;
      font-size: 24px;
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .sidebar-content {
      flex: 1;
      padding: 25px 20px;
      position: relative;
      z-index: 2;
    }

    .menu-section {
      margin-bottom: 30px;
    }

    .menu-title {
      color: rgba(255,255,255,0.6);
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 15px;
      padding-left: 15px;
    }

    .sidebar-content ul {
      list-style: none;
    }

    .sidebar-content > ul > li {
      margin-bottom: 8px;
    }

    .sidebar-content > ul > li > a {
      display: flex;
      align-items: center;
      gap: 15px;
      text-decoration: none;
      color: rgba(255,255,255,0.8);
      padding: 15px 20px;
      border-radius: 12px;
      transition: all 0.3s ease;
      cursor: pointer;
      font-weight: 500;
      position: relative;
      overflow: hidden;
    }

    .sidebar-content > ul > li > a::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
      transition: left 0.5s;
    }

    .sidebar-content a:hover::before {
      left: 100%;
    }

    .sidebar-content a:hover {
      background: rgba(255,255,255,0.15);
      color: white;
      transform: translateX(5px);
    }

    .sidebar-content a.active {
      background: rgba(255,255,255,0.2);
      color: white;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .sidebar-content a.active::after {
      content: '';
      position: absolute;
      right: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 40%;
      background: white;
      border-radius: 2px 0 0 2px;
    }

    .menu-icon {
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
    }

    /* Dropdown Styles */
    .dropdown-parent {
      position: relative;
    }

    .dropdown-toggle {
      position: relative;
    }

    .dropdown-arrow {
      margin-left: auto;
      transition: transform 0.3s ease;
      font-size: 16px;
    }

    .dropdown-parent.open .dropdown-arrow {
      transform: rotate(180deg);
    }

    .sidebar-content ul ul {
      list-style: none;
      padding-left: 0;
      margin-top: 8px;
      margin-left: 35px;
      display: none;
      opacity: 0;
      transform: translateY(-10px);
      transition: all 0.3s ease;
    }

    .sidebar-content li.open > ul {
      display: block;
      opacity: 1;
      transform: translateY(0);
    }

    .sidebar-content ul ul li {
      margin-bottom: 5px;
    }

    .sidebar-content ul ul li a {
      display: flex;
      align-items: center;
      gap: 10px;
      color: rgba(255,255,255,0.7);
      padding: 12px 15px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 14px;
      font-weight: 400;
      transition: all 0.3s ease;
      position: relative;
    }

    .sidebar-content ul ul li a::before {
      content: '';
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: rgba(255,255,255,0.4);
      transition: all 0.3s ease;
    }

    .sidebar-content ul ul li a:hover {
      background: rgba(255,255,255,0.1);
      color: white;
      padding-left: 20px;
    }

    .sidebar-content ul ul li a:hover::before {
      background: white;
      transform: scale(1.2);
    }

    .sidebar-content ul ul li a.active {
      background: rgba(255,255,255,0.15);
      color: white;
      font-weight: 500;
    }

    .sidebar-content ul ul li a.active::before {
      background: white;
    }

    /* Notification Badge */
    .notification-badge {
      background: #ef4444;
      color: white;
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 10px;
      margin-left: auto;
      min-width: 18px;
      text-align: center;
    }

    /* Main Content Wrapper */
    .main-wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: #f8fafc;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: white;
      padding: 20px 30px;
      border-bottom: 1px solid #e2e8f0;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .topbar-left {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .page-title {
      font-size: 24px;
      font-weight: 600;
      color: #1e293b;
    }

    .breadcrumb {
      color: #64748b;
      font-size: 14px;
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .topbar-right a {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      color: #64748b;
      padding: 10px 15px;
      border-radius: 8px;
      transition: all 0.3s ease;
      font-weight: 500;
      position: relative;
    }

    .topbar-right a:hover {
      background: #f1f5f9;
      color: #1e293b;
    }

    .status-indicator {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #10b981;
    }

    .content {
      padding: 30px;
      flex: 1;
      overflow-y: auto;
      background: #f8fafc;
    }

    /* Mobile Toggle Button */
    .mobile-toggle {
      display: none;
      position: fixed;
      top: 20px;
      left: 20px;
      z-index: 1000;
      background: #059669;
      color: white;
      border: none;
      padding: 12px;
      border-radius: 8px;
      cursor: pointer;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    /* Responsive Styles */
    @media (max-width: 1024px) {
      aside.sidebar {
        width: 260px;
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
        transition: left 0.3s ease;
      }

      aside.sidebar.mobile-open {
        left: 0;
      }

      .main-wrapper {
        width: 100%;
      }

      .topbar {
        padding-left: 70px;
      }

      .content {
        padding: 20px;
      }
    }

    @media (max-width: 480px) {
      .topbar {
        flex-direction: column;
        gap: 15px;
        padding: 15px 20px;
        padding-left: 60px;
      }

      .topbar-right {
        gap: 10px;
      }

      .content {
        padding: 15px;
      }
    }

    /* Loading Animation */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(255,255,255,0.9);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }

    .loading-spinner {
      width: 40px;
      height: 40px;
      border: 4px solid #e2e8f0;
      border-top: 4px solid #059669;
      border-radius: 50%;
      animation: spin 1s linear infinite;
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
      background: rgba(255,255,255,0.1);
      border-radius: 3px;
    }

    .sidebar-content::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,0.3);
      border-radius: 3px;
    }

    .sidebar-content::-webkit-scrollbar-thumb:hover {
      background: rgba(255,255,255,0.5);
    }
  </style>
</head>
<body>
  <!-- Mobile Toggle Button -->
  <button class="mobile-toggle" onclick="toggleMobileSidebar()">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
  </div>

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-brand">
        <div class="brand-icon">
          <i class="fas fa-stethoscope"></i>
        </div>
        <div class="brand-text">CanCare</div>
      </div>
      
      <div class="profile-section">
        <img src="../zphotos/profile_img.png" alt="Profile Picture" class="profile-image">
        <div class="profile-info">
          <h3>Dr. <?= htmlspecialchars($_SESSION['name'] ?? 'Doctor') ?></h3>
          <p>Medical Practitioner</p>
          <div class="doctor-badge">
            <i class="fas fa-user-md"></i> Licensed Physician
          </div>
        </div>
      </div>
    </div>

    <div class="sidebar-content">
      <div class="menu-section">
        <div class="menu-title">Main Dashboard</div>
        <ul>
          <li>
            <a href="?page=dashboard" class="<?= $page == 'dashboard' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-chart-pie"></i></div>
              Dashboard
            </a>
          </li>
          <li>
            <a href="?page=appointments" class="<?= $page == 'appointments' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-calendar-alt"></i></div>
              Appointments
              <span class="notification-badge">3</span>
            </a>
          </li>
          <li>
            <a href="?page=prescribe" class="<?= $page == 'prescribe' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-prescribe"></i></div>
              Prescriptions
            </a>
          </li>
        </ul>
      </div>

      <div class="menu-section">
        <div class="menu-title">Patient Management</div>
        <ul>
          <li>
            <a href="?page=patients" class="<?= $page == 'patients' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-users"></i></div>
              My Patients
            </a>
          </li>
          <li>
            <a href="?page=consultations" class="<?= $page == 'consultations' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-user-check"></i></div>
              Consultations
            </a>
          </li>
          
          <!-- Medical Records Dropdown -->
          <li id="recordsMenu" class="dropdown-parent <?= in_array($page, ['prescribe', 'reports', 'notes']) ? 'open' : '' ?>">
            <a onclick="toggleDropdown('recordsDropdown')" class="dropdown-toggle" style="cursor:pointer;">
              <div class="menu-icon"><i class="fas fa-file-medical"></i></div>
              Medical Records
              <i class="fas fa-chevron-down dropdown-arrow"></i>
            </a>
            <ul id="recordsDropdown">
              <li><a href="?page=prescribe" class="<?= $page == 'prescribe' ? 'active' : '' ?>" onclick="showLoading()">Prescriptions</a></li>
              <li><a href="?page=reports" class="<?= $page == 'reports' ? 'active' : '' ?>" onclick="showLoading()">Lab Reports</a></li>
              <li><a href="?page=notes" class="<?= $page == 'notes' ? 'active' : '' ?>" onclick="showLoading()">Medical Notes</a></li>
            </ul>
          </li>
        </ul>
      </div>

      <div class="menu-section">
        <div class="menu-title">Analytics & Tools</div>
        <ul>
          <li>
            <a href="?page=analytics" class="<?= $page == 'analytics' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-chart-line"></i></div>
              Analytics
            </a>
          </li>
          <li>
            <a href="?page=schedule" class="<?= $page == 'schedule' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-clock"></i></div>
              Schedule Manager
            </a>
          </li>
          <li>
            <a href="?page=availability" class="<?= $page == 'availability' ? 'active' : '' ?>" onclick="showLoading()">
              <div class="menu-icon"><i class="fas fa-calendar-check"></i></div>
              Set Availability
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
              'dashboard' => 'Medical Dashboard',
              'appointments' => 'Patient Appointments',
              'prescribe' => 'Prescription Management',
              'reports' => 'Laboratory Reports',
              'patients' => 'Patient Directory',
              'schedule' => 'Schedule Management',
              'consultations' => 'Active Consultations',
              'analytics' => 'Performance Analytics',
              'calendar' => 'Calendar Overview',
              'notes' => 'Medical Notes',
              'availability' => 'Availability Settings',
              'profile' => 'Doctor Profile'
            ];
            echo $page_titles[$page] ?? 'Medical Portal';
          ?>
        </div>
        <div class="breadcrumb">
          <i class="fas fa-chevron-right" style="margin: 0 8px; font-size: 12px;"></i>
          Doctor Portal
        </div>
      </div>
      
      <div class="topbar-right">
        <a href="#" onclick="showLoading()" title="Emergency Contacts">
          <i class="fas fa-phone-alt"></i>
          Emergency
        </a>
        <a href="#" onclick="showLoading()" title="Notifications">
          <i class="fas fa-bell"></i>
          Alerts
          <span class="notification-badge">5</span>
        </a>
        <a href="?page=profile" onclick="showLoading()">
          <i class="fas fa-user-md"></i>
          Profile
          <span class="status-indicator"></span>
        </a>
        <a href="#" onclick="showLoading()">
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
            echo "<div style='text-align: center; padding: 60px 20px;'>";
            echo "<i class='fas fa-stethoscope' style='font-size: 4rem; color: #059669; margin-bottom: 20px;'></i>";
            echo "<h1 style='font-size: 2rem; margin-bottom: 10px; color: #1e293b;'>Welcome to Doctor Portal</h1>";
            echo "<p style='color: #64748b; font-size: 1.1rem;'>Select a section from the sidebar to get started with patient management.</p>";
            echo "</div>";
        }
      ?>
    </div>
  </div>

<script>
  function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    const parentLi = dropdown.parentElement;
    
    if (parentLi.classList.contains('open')) {
      parentLi.classList.remove('open');
    } else {
      // Close other dropdowns
      document.querySelectorAll('.dropdown-parent').forEach(el => {
        if (el !== parentLi) el.classList.remove('open');
      });
      parentLi.classList.add('open');
    }
  }

  function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('mobile-open');
  }

  function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'flex';
    
    // Hide loading after 1 second max
    setTimeout(() => {
      overlay.style.display = 'none';
    }, 1000);
  }

  // Auto-open dropdown when on child pages
  document.addEventListener('DOMContentLoaded', function() {
    const recordsDropdown = document.getElementById('recordsDropdown');
    const parentLi = document.getElementById('recordsMenu');
    
    if (parentLi.classList.contains('open')) {
      // Already handled by PHP class
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!parentLi.contains(e.target)) {
        parentLi.classList.remove('open');
      }
    });

    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function(e) {
      const sidebar = document.getElementById('sidebar');
      const mobileToggle = document.querySelector('.mobile-toggle');
      
      if (window.innerWidth <= 768 && 
          !sidebar.contains(e.target) && 
          !mobileToggle.contains(e.target) && 
          sidebar.classList.contains('mobile-open')) {
        sidebar.classList.remove('mobile-open');
      }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
      const sidebar = document.getElementById('sidebar');
      if (window.innerWidth > 768) {
        sidebar.classList.remove('mobile-open');
      }
    });

    // Update notification badges periodically
    setInterval(updateNotifications, 30000); // Every 30 seconds
  });

  function updateNotifications() {
    // This would typically fetch from your backend
    // For now, it's just a placeholder
    console.log('Checking for new appointments and alerts...');
  }

  // Hide loading on page load
  window.addEventListener('load', function() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'none';
  });
</script>

</body>
</html>
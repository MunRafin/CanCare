<?php
// Start session at the very top
session_start();

// Handle AJAX requests first (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    // Set content type to JSON
    header('Content-Type: application/json');
    
    // This handles the AJAX appointment status updates
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    require_once '../dbPC.php';

    // Create database connection
    try {
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8");
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
        exit();
    }

    if (!isset($_POST['appointment_id']) || !isset($_POST['status'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        $conn->close();
        exit();
    }

    $appointment_id = intval($_POST['appointment_id']);
    $status = $_POST['status'];
    $doctor_user_id = $_SESSION['user_id'];

    // Validate status
    $allowed_statuses = ['accepted', 'rejected'];
    if (!in_array($status, $allowed_statuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        $conn->close();
        exit();
    }

    // Verify the appointment belongs to the current doctor and has 'made' status
    $verify_query = "SELECT id FROM appointments WHERE id = ? AND doctor_id = ? AND appointment_status = 'made'";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("ii", $appointment_id, $doctor_user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Appointment not found, access denied, or already processed']);
        $verify_stmt->close();
        $conn->close();
        exit();
    }
    $verify_stmt->close();

    // Update appointment status
    $update_query = "UPDATE appointments SET appointment_status = ? WHERE id = ? AND doctor_id = ? AND appointment_status = 'made'";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sii", $status, $appointment_id, $doctor_user_id);

    if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Appointment ' . $status . ' successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment or appointment already processed']);
    }

    $update_stmt->close();
    $conn->close();
    exit();
}

// Redirect if not doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../loginPC.html");
    exit();
}

require_once '../dbPC.php';

// Create database connection
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$doctor_id = $_SESSION['user_id'];

// Get doctor information
$doctor_query = "SELECT u.name, d.specialization, d.qualification, d.experience_years 
                FROM users u 
                JOIN doctors d ON u.id = d.user_id 
                WHERE u.id = ?";
$doctor_stmt = $conn->prepare($doctor_query);
$doctor_stmt->bind_param("i", $doctor_id);
$doctor_stmt->execute();
$doctor_info = $doctor_stmt->get_result()->fetch_assoc();

// Get total appointments
$total_appointments_query = "SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ?";
$total_stmt = $conn->prepare($total_appointments_query);
$total_stmt->bind_param("i", $doctor_id);
$total_stmt->execute();
$total_appointments = $total_stmt->get_result()->fetch_assoc()['total'];

// Get accepted appointments
$accepted_appointments_query = "SELECT COUNT(*) as accepted FROM appointments WHERE doctor_id = ? AND appointment_status = 'accepted'";
$accepted_stmt = $conn->prepare($accepted_appointments_query);
$accepted_stmt->bind_param("i", $doctor_id);
$accepted_stmt->execute();
$accepted_appointments = $accepted_stmt->get_result()->fetch_assoc()['accepted'];

// Get completed appointments (patients checked)
$completed_appointments_query = "SELECT COUNT(*) as completed FROM appointments WHERE doctor_id = ? AND appointment_status = 'prescribed'";
$completed_stmt = $conn->prepare($completed_appointments_query);
$completed_stmt->bind_param("i", $doctor_id);
$completed_stmt->execute();
$completed_appointments = $completed_stmt->get_result()->fetch_assoc()['completed'];

// Get pending appointments
$pending_appointments_query = "SELECT COUNT(*) as pending FROM appointments WHERE doctor_id = ? AND appointment_status = 'made'";
$pending_stmt = $conn->prepare($pending_appointments_query);
$pending_stmt->bind_param("i", $doctor_id);
$pending_stmt->execute();
$pending_appointments = $pending_stmt->get_result()->fetch_assoc()['pending'];

// Get patient age groups (for completed appointments)
$age_groups_query = "SELECT 
    CASE 
        WHEN TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) < 18 THEN 'Under 18'
        WHEN TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
        WHEN TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
        WHEN TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) BETWEEN 51 AND 65 THEN '51-65'
        ELSE 'Over 65'
    END as age_group,
    COUNT(*) as count
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_status = 'prescribed'
    GROUP BY age_group";
$age_stmt = $conn->prepare($age_groups_query);
$age_stmt->bind_param("i", $doctor_id);
$age_stmt->execute();
$age_groups = $age_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent appointments - ONLY SHOW 'made' STATUS
$recent_appointments_query = "SELECT a.*, u.name as patient_name, u.phone 
                             FROM appointments a 
                             JOIN users u ON a.patient_id = u.id 
                             WHERE a.doctor_id = ? AND a.appointment_status = 'made'
                             ORDER BY a.created_at DESC 
                             LIMIT 10";
$recent_stmt = $conn->prepare($recent_appointments_query);
$recent_stmt->bind_param("i", $doctor_id);
$recent_stmt->execute();
$recent_appointments = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get monthly appointment trends (last 6 months)
$monthly_trends_query = "SELECT 
    DATE_FORMAT(appointment_date, '%Y-%m') as month,
    COUNT(*) as count
    FROM appointments 
    WHERE doctor_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month";
$trends_stmt = $conn->prepare($monthly_trends_query);
$trends_stmt->bind_param("i", $doctor_id);
$trends_stmt->execute();
$monthly_trends = $trends_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get today's appointments
$today_appointments_query = "SELECT COUNT(*) as today_count 
                            FROM appointments 
                            WHERE doctor_id = ? AND appointment_date = CURDATE()";
$today_stmt = $conn->prepare($today_appointments_query);
$today_stmt->bind_param("i", $doctor_id);
$today_stmt->execute();
$today_appointments = $today_stmt->get_result()->fetch_assoc()['today_count'];

// Get this week's appointments
$week_appointments_query = "SELECT COUNT(*) as week_count 
                           FROM appointments 
                           WHERE doctor_id = ? 
                           AND appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) 
                           AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)";
$week_stmt = $conn->prepare($week_appointments_query);
$week_stmt->bind_param("i", $doctor_id);
$week_stmt->execute();
$week_appointments = $week_stmt->get_result()->fetch_assoc()['week_count'];

// Close all statements
if (isset($doctor_stmt)) $doctor_stmt->close();
if (isset($total_stmt)) $total_stmt->close();
if (isset($accepted_stmt)) $accepted_stmt->close();
if (isset($completed_stmt)) $completed_stmt->close();
if (isset($pending_stmt)) $pending_stmt->close();
if (isset($age_stmt)) $age_stmt->close();
if (isset($recent_stmt)) $recent_stmt->close();
if (isset($trends_stmt)) $trends_stmt->close();
if (isset($today_stmt)) $today_stmt->close();
if (isset($week_stmt)) $week_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --secondary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --purple: #8b5cf6;
            --gray-100: #f8fafc;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #334155;
            min-height: 100vh;
        }
        
        .appointment-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .appointment-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px;
            border-radius: 0 0 20px 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .welcome-text h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .doctor-stats {
            text-align: right;
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .doctor-stats h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .doctor-stats p {
            margin: 5px 0;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-color, var(--primary));
            border-radius: 20px 20px 0 0;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            background: var(--card-color, var(--primary));
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--gray-700);
            font-size: 1rem;
            font-weight: 500;
        }
        
        .stat-trend {
            font-size: 0.9rem;
            color: var(--success);
            margin-top: 10px;
        }
        
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .recent-appointments {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 15px 20px;
            z-index: 10000;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
            min-width: 300px;
        }
        
        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .notification-success {
            border-left-color: var(--success);
        }
        
        .notification-error {
            border-left-color: var(--danger);
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-content i {
            font-size: 1.2rem;
        }
        
        .notification-success .notification-content i {
            color: var(--success);
        }
        
        .notification-error .notification-content i {
            color: var(--danger);
        }
        
        .appointment-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .accept-btn, .reject-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .accept-btn {
            background: var(--success);
            color: white;
        }
        
        .accept-btn:hover {
            background: var(--primary);
            transform: translateY(-2px);
        }
        
        .accept-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .reject-btn {
            background: var(--danger);
            color: white;
        }
        
        .reject-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        
        .reject-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .appointment-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 25px;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .appointment-item:hover {
            background: var(--gray-100);
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .patient-info h4 {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 5px;
        }
        
        .patient-info p {
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        
        .appointment-meta {
            text-align: right;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 5px;
            display: inline-block;
        }
        
        .status-made { background: #fef3c7; color: #92400e; }
        .status-accepted { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fecaca; color: #991b1b; }
        .status-prescribed { background: #e0e7ff; color: #3730a3; }
        
        .appointment-date {
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .action-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            color: white;
        }
        
        .action-btn.secondary {
            background: var(--gray-700);
        }
        
        .action-btn.secondary:hover {
            background: #475569;
        }
        
        @media (max-width: 1024px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-section {
                flex-direction: column;
                text-align: center;
            }
            
            .doctor-stats {
                text-align: center;
                width: 100%;
            }
            
            .welcome-text h1 {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .appointment-header {
                padding: 20px;
            }
            
            .stat-card, .chart-card, .recent-appointments {
                padding: 20px;
            }
            
            .appointment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .appointment-meta {
                text-align: left;
            }
            
            .appointment-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
<div class="appointment-container">
    <div class="appointment-header">
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Dr. <?= htmlspecialchars($doctor_info['name']) ?> - Appointment</h1>
                <p><?= htmlspecialchars($doctor_info['specialization']) ?> | <?= htmlspecialchars($doctor_info['qualification']) ?></p>
            </div>
            <div class="doctor-stats">
                <h3><i class="fas fa-calendar-day"></i> Today's Overview</h3>
                <p><strong><?= $today_appointments ?></strong> appointments today</p>
                <p><strong><?= $week_appointments ?></strong> this week</p>
                <p><strong><?= date('l, F j, Y') ?></strong></p>
            </div>
        </div>
    </div>

    <div class="recent-appointments">
        <h3 class="section-title">
            <i class="fas fa-bell"></i> Pending Appointment Requests
        </h3>
        
        <?php if (!empty($recent_appointments)): ?>
            <?php foreach ($recent_appointments as $appointment): ?>
                <div class="appointment-item" data-appointment-id="<?= $appointment['id'] ?>">
                    <div class="patient-info">
                        <h4><?= htmlspecialchars($appointment['patient_name']) ?></h4>
                        <p><i class="fas fa-phone"></i> <?= htmlspecialchars($appointment['phone']) ?></p>
                        <?php if (!empty($appointment['symptoms'])): ?>
                        <p><i class="fas fa-stethoscope"></i> <?= htmlspecialchars($appointment['symptoms']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="appointment-meta">
                        <span class="status-badge status-made">Pending</span>
                        <div class="appointment-date">
                            <i class="fas fa-calendar"></i> 
                            <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?>
                            <br>
                            <i class="fas fa-clock"></i> 
                            <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                        </div>
                        <div class="appointment-actions">
                            <button class="accept-btn" onclick="acceptAppointment(<?= $appointment['id'] ?>)">
                                <i class="fas fa-check"></i> Accept
                            </button>
                            <button class="reject-btn" onclick="rejectAppointment(<?= $appointment['id'] ?>)">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #64748b;">
                <i class="fas fa-calendar-check" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                <p>No pending appointment requests.</p>
                <p style="font-size: 0.9rem; margin-top: 10px;">New appointment requests will appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="stats-grid">
        <div class="stat-card" style="--card-color: #3b82f6;">
            <div class="stat-header">
                <div class="stat-icon" style="background: #3b82f6;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
            <div class="stat-number"><?= $total_appointments ?></div>
            <div class="stat-label">Total Appointments</div>
            <div class="stat-trend">
                <i class="fas fa-arrow-up"></i> All time record
            </div>
        </div>

        <div class="stat-card" style="--card-color: #10b981;">
            <div class="stat-header">
                <div class="stat-icon" style="background: #10b981;">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-number"><?= $accepted_appointments ?></div>
            <div class="stat-label">Accepted Appointments</div>
            <div class="stat-trend">
                <i class="fas fa-arrow-up"></i> Ready for consultation
            </div>
        </div>

        <div class="stat-card" style="--card-color: #8b5cf6;">
            <div class="stat-header">
                <div class="stat-icon" style="background: #8b5cf6;">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            <div class="stat-number"><?= $completed_appointments ?></div>
            <div class="stat-label">Patients Treated</div>
            <div class="stat-trend">
                <i class="fas fa-arrow-up"></i> Successfully completed
            </div>
        </div>

        <div class="stat-card" style="--card-color: #f59e0b;">
            <div class="stat-header">
                <div class="stat-icon" style="background: #f59e0b;">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-number"><?= $pending_appointments ?></div>
            <div class="stat-label">Pending Requests</div>
            <div class="stat-trend">
                <i class="fas fa-exclamation-circle"></i> Awaiting response
            </div>
        </div>

        <div class="stat-card" style="--card-color: #ef4444;">
            <div class="stat-header">
                <div class="stat-icon" style="background: #ef4444;">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
            <div class="stat-number"><?= $today_appointments ?></div>
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-trend">
                <i class="fas fa-calendar-check"></i> Schedule for today
            </div>
        </div>

        <div class="stat-card" style="--card-color: #06b6d4;">
            <div class="stat-header">
                <div class="stat-icon" style="background: #06b6d4;">
                    <i class="fas fa-calendar-week"></i>
                </div>
            </div>
            <div class="stat-number"><?= $week_appointments ?></div>
            <div class="stat-label">This Week</div>
            <div class="stat-trend">
                <i class="fas fa-chart-bar"></i> Weekly overview
            </div>
        </div>
    </div>

    <div class="charts-section">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="fas fa-users"></i> Patient Age Distribution
                </h3>
            </div>
            <div class="chart-container">
                <canvas id="ageGroupChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="fas fa-chart-line"></i> Appointment Trends
                </h3>
            </div>
            <div class="chart-container">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>
    </div>

    <div class="quick-actions">
        <a href="?page=appointments" class="action-btn">
            <i class="fas fa-calendar-check"></i>
            View All Appointments
        </a>
        <a href="?page=patients" class="action-btn secondary">
            <i class="fas fa-users"></i>
            Manage Patients
        </a>
        <a href="?page=schedule" class="action-btn secondary">
            <i class="fas fa-calendar-alt"></i>
            Update Schedule
        </a>
    </div>
</div>

<script>
// Age Groups Chart
const ageGroupData = <?= json_encode($age_groups) ?>;
const ageLabels = ageGroupData.length > 0 ? ageGroupData.map(item => item.age_group) : ['No Data'];
const ageCounts = ageGroupData.length > 0 ? ageGroupData.map(item => parseInt(item.count)) : [1];

const ageGroupChart = new Chart(document.getElementById('ageGroupChart'), {
    type: 'doughnut',
    data: {
        labels: ageLabels,
        datasets: [{
            data: ageCounts,
            backgroundColor: ageGroupData.length > 0 ? [
                '#3b82f6',
                '#10b981',
                '#f59e0b',
                '#ef4444',
                '#8b5cf6'
            ] : ['#e2e8f0'],
            borderWidth: 3,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    font: {
                        size: 12
                    }
                }
            }
        }
    }
});

// Monthly Trends Chart
const trendsData = <?= json_encode($monthly_trends) ?>;
const monthLabels = trendsData.length > 0 ? trendsData.map(item => {
    const date = new Date(item.month + '-01');
    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
}) : ['No Data'];
const trendsCounts = trendsData.length > 0 ? trendsData.map(item => parseInt(item.count)) : [0];

const trendsChart = new Chart(document.getElementById('trendsChart'), {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Appointments',
            data: trendsCounts,
            borderColor: '#059669',
            backgroundColor: 'rgba(5, 150, 105, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#059669',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.1)'
                },
                ticks: {
                    stepSize: 1
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Accept appointment function - FIXED VERSION
function acceptAppointment(appointmentId) {
    if (confirm('Are you sure you want to accept this appointment?')) {
        const appointmentItem = document.querySelector(`[data-appointment-id="${appointmentId}"]`);
        const acceptBtn = appointmentItem.querySelector('.accept-btn');
        const rejectBtn = appointmentItem.querySelector('.reject-btn');
        
        // Show loading state
        acceptBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Accepting...';
        acceptBtn.disabled = true;
        rejectBtn.disabled = true;
        
        // Create form data for POST request
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('appointment_id', appointmentId);
        formData.append('status', 'accepted');
        
        // Send AJAX request to the same page
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is ok and is JSON
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server did not return JSON response');
            }
            
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Remove the entire appointment item with animation
                appointmentItem.style.transition = 'all 0.5s ease';
                appointmentItem.style.opacity = '0';
                appointmentItem.style.transform = 'translateX(-100%)';
                
                setTimeout(() => {
                    appointmentItem.remove();
                    showNotification('Appointment accepted successfully!', 'success');
                    
                    // Check if no more pending appointments
                    const remainingItems = document.querySelectorAll('.appointment-item').length;
                    if (remainingItems === 0) {
                        // Refresh to show "no pending appointments" message and update stats
                        setTimeout(() => window.location.reload(), 1000);
                    }
                }, 500);
            } else {
                showNotification('Failed to accept appointment: ' + data.message, 'error');
                // Reset buttons
                acceptBtn.innerHTML = '<i class="fas fa-check"></i> Accept';
                acceptBtn.disabled = false;
                rejectBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while accepting the appointment', 'error');
            // Reset buttons
            acceptBtn.innerHTML = '<i class="fas fa-check"></i> Accept';
            acceptBtn.disabled = false;
            rejectBtn.disabled = false;
        });
    }
}

// Reject appointment function - FIXED VERSION
function rejectAppointment(appointmentId) {
    if (confirm('Are you sure you want to reject this appointment? This action cannot be undone.')) {
        const appointmentItem = document.querySelector(`[data-appointment-id="${appointmentId}"]`);
        const acceptBtn = appointmentItem.querySelector('.accept-btn');
        const rejectBtn = appointmentItem.querySelector('.reject-btn');
        
        // Show loading state
        rejectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rejecting...';
        rejectBtn.disabled = true;
        acceptBtn.disabled = true;
        
        // Create form data for POST request
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('appointment_id', appointmentId);
        formData.append('status', 'rejected');
        
        // Send AJAX request to the same page
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the status badge
                const statusBadge = appointmentItem.querySelector('.status-badge');
                statusBadge.className = 'status-badge status-rejected';
                statusBadge.textContent = 'Rejected';
                
                // Remove action buttons
                const actionsDiv = appointmentItem.querySelector('.appointment-actions');
                if (actionsDiv) {
                    actionsDiv.remove();
                }
                
                showNotification('Appointment rejected successfully!', 'success');
                
                // Refresh page after 2 seconds to update statistics
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showNotification('Failed to reject appointment: ' + data.message, 'error');
                // Reset buttons
                rejectBtn.innerHTML = '<i class="fas fa-times"></i> Reject';
                rejectBtn.disabled = false;
                acceptBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while rejecting the appointment', 'error');
            // Reset buttons
            rejectBtn.innerHTML = '<i class="fas fa-times"></i> Reject';
            rejectBtn.disabled = false;
            acceptBtn.disabled = false;
        });
    }
}

// Show notification function
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 3000);
}

// Page initialization
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .chart-card, .recent-appointments');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const timeElement = document.querySelector('.current-time');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }

    updateTime();
    setInterval(updateTime, 60000);

    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
});

// Auto-refresh every 5 minutes
setInterval(() => {
    console.log('Auto-refreshing dashboard...');
    window.location.reload();
}, 300000);

// Handle chart resize on window resize
window.addEventListener('resize', function() {
    if (typeof ageGroupChart !== 'undefined') {
        ageGroupChart.resize();
    }
    if (typeof trendsChart !== 'undefined') {
        trendsChart.resize();
    }
});

// Show loading state while charts are rendering
document.addEventListener('DOMContentLoaded', function() {
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach(container => {
        container.style.position = 'relative';
        
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'chart-loading';
        loadingDiv.innerHTML = `
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #059669; margin-bottom: 10px;"></i>
                <p style="color: #64748b;">Loading chart...</p>
            </div>
        `;
        container.appendChild(loadingDiv);
        
        setTimeout(() => {
            if (loadingDiv.parentNode) {
                loadingDiv.remove();
            }
        }, 2000);
    });
});
</script>

</body>
</html>
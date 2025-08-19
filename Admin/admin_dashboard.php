<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../loginPC.html");
    exit();
}

require_once '../dbPC.php';

// Initialize variables
$total_patients = 0;
$total_doctors = 0;
$total_nutritionists = 0;
$total_trainers = 0;
$appointments_made = 0;
$appointments_accepted = 0;
$appointments_done = 0;
$total_medicines = 0;
$new_medicines_this_week = 0;
$todays_appointments = [];

// 1. Count users by role
try {
    $stmt = $conn->prepare("
        SELECT role, COUNT(*) as count 
        FROM users
        WHERE role IN ('patient', 'doctor', 'nutritionist', 'trainer')
        GROUP BY role
    ");
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        switch ($row['role']) {
            case 'patient':
                $total_patients = $row['count'];
                break;
            case 'doctor':
                $total_doctors = $row['count'];
                break;
            case 'nutritionist':
                $total_nutritionists = $row['count'];
                break;
            case 'trainer':
                $total_trainers = $row['count'];
                break;
        }
    }
} catch (PDOException $e) {
    error_log("User count query error: " . $e->getMessage());
}

// 2. Appointment status counts
try {
    $stmt = $conn->prepare("
        SELECT appointment_status, COUNT(*) as count 
        FROM appointments
        GROUP BY appointment_status
    ");
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        switch ($row['appointment_status']) {
            case 'made':
                $appointments_made = $row['count'];
                break;
            case 'accepted':
                $appointments_accepted = $row['count'];
                break;
            case 'done':
                $appointments_done = $row['count'];
                break;
        }
    }
} catch (PDOException $e) {
    error_log("Appointment status query error: " . $e->getMessage());
}

// 3. Today's appointments
$today = date("Y-m-d");
try {
    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.symptoms, a.appointment_status,
               p.name as patient_name, d.name as doctor_name
        FROM appointments a
        JOIN users p ON a.patient_id = p.id
        JOIN users d ON a.doctor_id = d.id
        WHERE a.appointment_date = ?
        ORDER BY a.appointment_time
    ");
    $stmt->execute([$today]);
    $todays_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Today's appointments query error: " . $e->getMessage());
}

// 4. Medicine counts
try {
    // Total medicines
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM medicines");
    $stmt->execute();
    $total_medicines = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // New medicines this week
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM medicines 
        WHERE DATE(added_on) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_of_week, $today]);
    $new_medicines_this_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    error_log("Medicine count query error: " . $e->getMessage());
}

// 5. Chart data - Monthly appointments
$appointments_by_day = [];
$patient_signups_by_day = [];
$medicine_added_by_day = [];

// Initialize arrays for last 30 days
$last_30_days = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last_30_days[] = $date;
    $appointments_by_day[$date] = 0;
    $patient_signups_by_day[$date] = 0;
    $medicine_added_by_day[$date] = 0;
}

// Appointments per day
try {
    $stmt = $conn->prepare("
        SELECT DATE(appointment_date) as date, COUNT(*) as count
        FROM appointments
        WHERE appointment_date BETWEEN ? AND ?
        GROUP BY DATE(appointment_date)
    ");
    $stmt->execute([$last_30_days[0], end($last_30_days)]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        if (isset($appointments_by_day[$row['date']])) {
            $appointments_by_day[$row['date']] = (int)$row['count'];
        }
    }
} catch (PDOException $e) {
    error_log("Appointments by day query error: " . $e->getMessage());
}

// Patient signups per day
try {
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users
        WHERE role = 'patient' AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ");
    $stmt->execute([$last_30_days[0] . ' 00:00:00', end($last_30_days) . ' 23:59:59']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        if (isset($patient_signups_by_day[$row['date']])) {
            $patient_signups_by_day[$row['date']] = (int)$row['count'];
        }
    }
} catch (PDOException $e) {
    error_log("Patient signups by day query error: " . $e->getMessage());
}

// Medicines added per day
try {
    $stmt = $conn->prepare("
        SELECT DATE(added_on) as date, COUNT(*) as count
        FROM medicines
        WHERE added_on BETWEEN ? AND ?
        GROUP BY DATE(added_on)
    ");
    $stmt->execute([$last_30_days[0] . ' 00:00:00', end($last_30_days) . ' 23:59:59']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        if (isset($medicine_added_by_day[$row['date']])) {
            $medicine_added_by_day[$row['date']] = (int)$row['count'];
        }
    }
} catch (PDOException $e) {
    error_log("Medicines added by day query error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Personal Care</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f0f7ff;
            color: #333;
            padding: 20px;
        }
        
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e7ff;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
        }
        
        .welcome-message {
            font-size: 18px;
            color: #5c7cfa;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-align: center;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-icon {
            font-size: 36px;
            margin-bottom: 15px;
            display: inline-block;
            width: 70px;
            height: 70px;
            line-height: 70px;
            border-radius: 50%;
            color: white;
        }
        
        .card h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .card .value {
            font-size: 32px;
            color: #2c3e50;
            font-weight: 600;
            margin: 10px 0;
        }
        
        .card .description {
            font-size: 14px;
            color: #777;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }
        
        .stats-card h2 {
            color: #2c3e50;
            font-size: 20px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .stats-card h2 i {
            margin-right: 10px;
            font-size: 24px;
        }
        
        .appointments-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }
        
        .chart-card h3 {
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f8f9ff;
            color: #5c7cfa;
            font-weight: 500;
        }
        
        tr:hover {
            background-color: #f8f9ff;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .patient-card .card-icon { background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); }
        .doctor-card .card-icon { background: linear-gradient(135deg, #2af598 0%, #009efd 100%); }
        .nutritionist-card .card-icon { background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%); }
        .trainer-card .card-icon { background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%); }
        .appointment-made .card-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .appointment-accepted .card-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .appointment-done .card-icon { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .medicine-card .card-icon { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .new-medicine-card .card-icon { background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%); }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #777;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <div class="welcome-message">
                <i class="fas fa-user-shield"></i> Welcome, Administrator
            </div>
        </div>
        
        <!-- User Count Cards -->
        <div class="cards-grid">
            <div class="card patient-card">
                <div class="card-icon">
                    <i class="fas fa-user-injured"></i>
                </div>
                <h3>Patients</h3>
                <div class="value"><?= $total_patients ?></div>
                <div class="description">Registered Patients</div>
            </div>
            
            <div class="card doctor-card">
                <div class="card-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Doctors</h3>
                <div class="value"><?= $total_doctors ?></div>
                <div class="description">Medical Professionals</div>
            </div>
            
            <div class="card nutritionist-card">
                <div class="card-icon">
                    <i class="fas fa-apple-alt"></i>
                </div>
                <h3>Nutritionists</h3>
                <div class="value"><?= $total_nutritionists ?></div>
                <div class="description">Diet Specialists</div>
            </div>
            
            <div class="card trainer-card">
                <div class="card-icon">
                    <i class="fas fa-running"></i>
                </div>
                <h3>Trainers</h3>
                <div class="value"><?= $total_trainers ?></div>
                <div class="description">Fitness Experts</div>
            </div>
        </div>
        
        <!-- Appointment Stats -->
        <div class="stats-grid">
            <div class="stats-card">
                <h2><i class="fas fa-calendar-check"></i> Appointments Overview</h2>
                <div class="cards-grid">
                    <div class="card appointment-made">
                        <div class="card-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <h3>Made</h3>
                        <div class="value"><?= $appointments_made ?></div>
                        <div class="description">Appointments Created</div>
                    </div>
                    
                    <div class="card appointment-accepted">
                        <div class="card-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>Accepted</h3>
                        <div class="value"><?= $appointments_accepted ?></div>
                        <div class="description">Confirmed Appointments</div>
                    </div>
                    
                    <div class="card appointment-done">
                        <div class="card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Completed</h3>
                        <div class="value"><?= $appointments_done ?></div>
                        <div class="description">Finished Appointments</div>
                    </div>
                </div>
            </div>
            
            <div class="stats-card">
                <h2><i class="fas fa-pills"></i> Medicine Inventory</h2>
                <div class="cards-grid">
                    <div class="card medicine-card">
                        <div class="card-icon">
                            <i class="fas fa-capsules"></i>
                        </div>
                        <h3>Total Medicines</h3>
                        <div class="value"><?= $total_medicines ?></div>
                        <div class="description">In System</div>
                    </div>
                    
                    <div class="card new-medicine-card">
                        <div class="card-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <h3>New This Week</h3>
                        <div class="value"><?= $new_medicines_this_week ?></div>
                        <div class="description">Recently Added</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Appointments -->
        <div class="appointments-card">
            <h2><i class="fas fa-calendar-day"></i> Today's Appointments (<?= date('F j, Y') ?>)</h2>
            <?php if (count($todays_appointments) > 0): ?>
                <table>
                    <tr>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Symptoms</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($todays_appointments as $appt): ?>
                        <tr>
                            <td><?= date('h:i A', strtotime($appt['appointment_time'])) ?></td>
                            <td><?= htmlspecialchars($appt['patient_name']) ?></td>
                            <td>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></td>
                            <td><?= htmlspecialchars($appt['symptoms'] ?? 'None') ?></td>
                            <td>
                                <span style="
                                    background: <?= 
                                        $appt['appointment_status'] === 'made' ? '#4facfe' : 
                                        ($appt['appointment_status'] === 'accepted' ? '#43e97b' : '#fa709a') 
                                    ?>;
                                    color: white;
                                    padding: 5px 10px;
                                    border-radius: 20px;
                                    font-size: 12px;
                                ">
                                    <?= ucfirst($appt['appointment_status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p>No appointments scheduled for today</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Charts Row -->
        <div class="chart-row">
            <div class="chart-card">
                <h3>Appointments Trend (Last 30 Days)</h3>
                <div class="chart-container">
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>Patient Signups (Last 30 Days)</h3>
                <div class="chart-container">
                    <canvas id="signupsChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-card">
                <h3>Medicine Inventory Trends (Last 30 Days)</h3>
                <div class="chart-container">
                    <canvas id="medicineChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Format dates for charts
        const labels = <?= json_encode(array_map(function($date) {
            return date('M d', strtotime($date));
        }, $last_30_days)) ?>;
        
        // Appointments Chart
        const appointmentsChart = new Chart(document.getElementById('appointmentsChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Appointments',
                    data: <?= json_encode(array_values($appointments_by_day)) ?>,
                    backgroundColor: 'rgba(79, 172, 254, 0.2)',
                    borderColor: 'rgba(79, 172, 254, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Appointments' }
                    },
                    x: {
                        title: { display: true, text: 'Date' }
                    }
                }
            }
        });
        
        // Signups Chart
        const signupsChart = new Chart(document.getElementById('signupsChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Patient Signups',
                    data: <?= json_encode(array_values($patient_signups_by_day)) ?>,
                    backgroundColor: 'rgba(67, 233, 123, 0.7)',
                    borderColor: 'rgba(56, 249, 215, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Signups' }
                    },
                    x: {
                        title: { display: true, text: 'Date' }
                    }
                }
            }
        });
        
        // Medicine Chart
        const medicineChart = new Chart(document.getElementById('medicineChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Medicines Added',
                    data: <?= json_encode(array_values($medicine_added_by_day)) ?>,
                    backgroundColor: 'rgba(250, 112, 154, 0.2)',
                    borderColor: 'rgba(250, 112, 154, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Medicines Added' }
                    },
                    x: {
                        title: { display: true, text: 'Date' }
                    }
                }
            }
        });
    </script>
</body>
</html>
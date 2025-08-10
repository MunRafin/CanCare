<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

require_once '../dbPC.php';

$user_id = $_SESSION['user_id'];
$date_today = date("Y-m-d");

// Debug: Check session and connection
try {
    if (!$conn) {
        die("Database connection failed");
    }
    
    // Test if user exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_exists['count'] == 0) {
        die("User not found with ID: " . $user_id);
    }
} catch (Exception $e) {
    die("Connection error: " . $e->getMessage());
}

// Range selection - applies to all dashboard components
$range = $_GET['range'] ?? 'week';
switch ($range) {
    case 'day':
        $start_date = $date_today;
        $end_date = $date_today;
        break;
    case 'week':
        $start_date = date("Y-m-d", strtotime("-6 days"));
        $end_date = $date_today;
        break;
    case 'month':
        $start_date = date("Y-m-d", strtotime("-29 days"));
        $end_date = $date_today;
        break;
    case 'year':
        $start_date = date("Y-m-d", strtotime("-1 year"));
        $end_date = $date_today;
        break;
    default:
        $start_date = date("Y-m-d", strtotime("-6 days"));
        $end_date = $date_today;
        break;
}

// Initialize default values
$calories_total = 0;
$food_logs_count = 0;
$avg_calories = 0;
$calories_burned_total = 0;
$exercises_count = 0;
$avg_burned = 0;
$steps_total = 0;
$steps_avg = 0;
$steps_max = 0;
$days_count = 0;
$medicines_count = 0;
$med_names = '';
$top_exercises = [];
$top_foods = [];
$nutrients = ['protein' => 0, 'carbs' => 0, 'fats' => 0];
$upcoming_appointments = [];

// 1. Calorie Intake (from food_logs)
try {
    $stmt = $conn->prepare("
        SELECT 
            SUM(calculated_calorie) AS total_calories,
            COUNT(*) AS food_count,
            AVG(calculated_calorie) AS avg_calories
        FROM food_logs 
        WHERE user_id = ? AND log_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
    $calorie_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $calories_total = $calorie_data['total_calories'] ?? 0;
    $food_logs_count = $calorie_data['food_count'] ?? 0;
    $avg_calories = $calorie_data['avg_calories'] ?? 0;
} catch (PDOException $e) {
    error_log("Calorie query error: " . $e->getMessage());
}

// 2. Calories Burned (from exercise_log)
try {
    $stmt = $conn->prepare("
        SELECT 
            SUM(calorie_burned) AS total_burned,
            COUNT(*) AS exercise_count,
            AVG(calorie_burned) AS avg_burned
        FROM exercise_log 
        WHERE user_id = ? AND log_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
    $burned_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $calories_burned_total = $burned_data['total_burned'] ?? 0;
    $exercises_count = $burned_data['exercise_count'] ?? 0;
    $avg_burned = $burned_data['avg_burned'] ?? 0;
} catch (PDOException $e) {
    error_log("Exercise query error: " . $e->getMessage());
}

// 3. Steps (from step_logs)
try {
    $stmt = $conn->prepare("
        SELECT 
            SUM(steps) AS total_steps,
            AVG(steps) AS avg_steps,
            MAX(steps) AS max_steps,
            COUNT(*) AS days_count
        FROM step_logs 
        WHERE user_id = ? AND log_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
    $step_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $steps_total = $step_data['total_steps'] ?? 0;
    $steps_avg = $step_data['avg_steps'] ?? 0;
    $steps_max = $step_data['max_steps'] ?? 0;
    $days_count = $step_data['days_count'] ?? 0;
} catch (PDOException $e) {
    error_log("Steps query error: " . $e->getMessage());
}

// 4. Medicines (from medicine_log) - FIXED TABLE NAME
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS medicine_count,
            GROUP_CONCAT(med_name SEPARATOR ', ') AS med_names
        FROM medicine_log 
        WHERE user_id = ? AND schedule_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
    $medicine_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $medicines_count = $medicine_data['medicine_count'] ?? 0;
    $med_names = $medicine_data['med_names'] ?? '';
} catch (PDOException $e) {
    error_log("Medicine query error: " . $e->getMessage());
}

// 5. Exercises Summary (from exercise_log)
try {
    $stmt = $conn->prepare("
        SELECT 
            ep.name AS exercise_name,
            COUNT(*) AS exercise_count
        FROM exercise_log el
        JOIN exercise_prs ep ON el.exercise_id = ep.id
        WHERE el.user_id = ? AND el.log_date BETWEEN ? AND ?
        GROUP BY ep.name
        ORDER BY exercise_count DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
    $top_exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Top exercises query error: " . $e->getMessage());
}

// 6. Food Summary (from food_logs)
try {
    $stmt = $conn->prepare("
        SELECT 
            fd.food_name,
            COUNT(*) AS food_count
        FROM food_logs fl
        JOIN food_datapg fd ON fl.food_id = fd.id
        WHERE fl.user_id = ? AND fl.log_date BETWEEN ? AND ?
        GROUP BY fd.food_name
        ORDER BY food_count DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
    $top_foods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Top foods query error: " . $e->getMessage());
}

// 7. Nutrient Breakdown (for today only)
try {
    $stmt = $conn->prepare("
        SELECT 
            SUM(fd.protein_mg_pg * fl.amount_in_grams / 1000) as protein,
            SUM(fd.carb_mg_pg * fl.amount_in_grams / 1000) as carbs,
            SUM(fd.fat_mg_pg * fl.amount_in_grams / 1000) as fats
        FROM food_logs fl
        JOIN food_datapg fd ON fl.food_id = fd.id
        WHERE fl.user_id = ? AND fl.log_date = ?
    ");
    $stmt->execute([$user_id, $date_today]);
    $nutrients = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Nutrients query error: " . $e->getMessage());
}

// Calculate percentages
$total_nutrients = ($nutrients['protein'] ?? 0) + ($nutrients['carbs'] ?? 0) + ($nutrients['fats'] ?? 0);
if ($total_nutrients > 0) {
    $protein_percent = round(($nutrients['protein'] / $total_nutrients) * 100);
    $carbs_percent = round(($nutrients['carbs'] / $total_nutrients) * 100);
    $fats_percent = round(($nutrients['fats'] / $total_nutrients) * 100);
} else {
    $protein_percent = $carbs_percent = $fats_percent = 0;
}

// Timeline Data for Charts
$labels = [];
$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'),
    (new DateTime($end_date))->modify('+1 day')
);
foreach ($period as $dt) {
    $labels[] = $dt->format("Y-m-d");
}
$week = array_fill_keys($labels, ['intake' => 0, 'burned' => 0, 'steps' => 0]);

// Calorie Intake History
try {
    $stmt = $conn->prepare("
        SELECT log_date, SUM(calculated_calorie) as total 
        FROM food_logs 
        WHERE user_id = ? AND log_date BETWEEN ? AND ? 
        GROUP BY log_date
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($week[$row['log_date']])) {
            $week[$row['log_date']]['intake'] = (int)$row['total'];
        }
    }
} catch (PDOException $e) {
    error_log("Calorie history query error: " . $e->getMessage());
}

// Calorie Burned History
try {
    $stmt = $conn->prepare("
        SELECT log_date, SUM(calorie_burned) as total 
        FROM exercise_log 
        WHERE user_id = ? AND log_date BETWEEN ? AND ? 
        GROUP BY log_date
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($week[$row['log_date']])) {
            $week[$row['log_date']]['burned'] = (int)$row['total'];
        }
    }
} catch (PDOException $e) {
    error_log("Exercise history query error: " . $e->getMessage());
}

// Step History
try {
    $stmt = $conn->prepare("
        SELECT log_date, steps
        FROM step_logs 
        WHERE user_id = ? AND log_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($week[$row['log_date']])) {
            $week[$row['log_date']]['steps'] = (int)$row['steps'];
        }
    }
} catch (PDOException $e) {
    error_log("Step history query error: " . $e->getMessage());
}

// Upcoming Appointments
try {
    $stmt = $conn->prepare("
        SELECT a.appointment_date, a.appointment_time, a.symptoms, 
               u.name as doctor_name, d.specialization
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        JOIN doctors d ON u.id = d.user_id
        WHERE a.patient_id = ? AND a.appointment_date >= ?
        ORDER BY a.appointment_date, a.appointment_time
        LIMIT 3
    ");
    $stmt->execute([$user_id, $date_today]);
    $upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Appointments query error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Care Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .range-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .range-btn {
            background-color: #5c7cfa;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .range-btn:hover {
            background-color: #3b5bdb;
            transform: translateY(-2px);
        }
        
        .range-btn.active {
            background-color: #2c3e50;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .card h3 i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        .card p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }
        
        .card strong {
            font-size: 24px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .card .small {
            font-size: 13px;
            color: #777;
        }
        
        .mini-list {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .mini-list p {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
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
        
        .nutrient-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .appointments-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
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
        
        .calorie-card {
            background: linear-gradient(to right, #caffbf, #9bf6ff);
        }
        
        .burned-card {
            background: linear-gradient(to right, #ffd6a5, #fdffb6);
        }
        
        .steps-card {
            background: linear-gradient(to right, #bdb2ff, #a0c4ff);
        }
        
        .medicine-card {
            background: linear-gradient(to right, #ffc6ff, #ffadad);
        }
        
        .nutrient-card {
            background: linear-gradient(to right, #e0f4ff, #a8dadc);
        }
        
        .appointments-card {
            background: linear-gradient(to right, #ffdee9, #b5ead7);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .nutrient-container {
            position: relative;
            height: 250px;
            width: 250px;
            margin: 20px auto;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Personal Care Dashboard</h1>
        </div>
        
        <!-- Range Filter -->
        <div class="range-buttons">
            <button class="range-btn <?= $range === 'day' ? 'active' : '' ?>" onclick="window.location.href='?page=dashboard&range=day'">Day</button>
            <button class="range-btn <?= $range === 'week' ? 'active' : '' ?>" onclick="window.location.href='?page=dashboard&range=week'">Week</button>
            <button class="range-btn <?= $range === 'month' ? 'active' : '' ?>" onclick="window.location.href='?page=dashboard&range=month'">Month</button>
            <button class="range-btn <?= $range === 'year' ? 'active' : '' ?>" onclick="window.location.href='?page=dashboard&range=year'">Year</button>
        </div>
        
        <!-- Top Cards -->
        <div class="cards-grid">
            <!-- Calorie Intake Card -->
            <div class="card calorie-card">
                <h3><i class="fas fa-utensils"></i> Calorie Intake</h3>
                <p><strong><?= number_format($calories_total) ?></strong> kcal total</p>
                <p class="small"><?= $food_logs_count ?> food logs (avg <?= number_format($avg_calories, 1) ?> kcal)</p>
                
                <?php if (count($top_foods) > 0): ?>
                    <div class="mini-list">
                        <p><strong>Top Foods:</strong></p>
                        <?php foreach ($top_foods as $food): ?>
                            <p><?= htmlspecialchars($food['food_name']) ?> <span>(<?= $food['food_count'] ?>x)</span></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Calories Burned Card -->
            <div class="card burned-card">
                <h3><i class="fas fa-fire"></i> Calories Burned</h3>
                <p><strong><?= number_format($calories_burned_total) ?></strong> kcal total</p>
                <p class="small"><?= $exercises_count ?> exercises (avg <?= number_format($avg_burned, 1) ?> kcal)</p>
                
                <?php if (count($top_exercises) > 0): ?>
                    <div class="mini-list">
                        <p><strong>Top Exercises:</strong></p>
                        <?php foreach ($top_exercises as $ex): ?>
                            <p><?= htmlspecialchars($ex['exercise_name']) ?> <span>(<?= $ex['exercise_count'] ?>x)</span></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Steps Card -->
            <div class="card steps-card">
                <h3><i class="fas fa-walking"></i> Steps Taken</h3>
                <p><strong><?= number_format($steps_total) ?></strong> total steps</p>
                <p class="small">Avg <?= number_format($steps_avg) ?>/day (max <?= number_format($steps_max) ?>)</p>
                <p class="small"><?= $days_count ?> days logged</p>
                <div class="chart-container">
                    <canvas id="stepsGauge"></canvas>
                </div>
            </div>
            
            <!-- Medicines Card -->
            <div class="card medicine-card">
                <h3><i class="fas fa-pills"></i> Medicines</h3>
                <p><strong><?= $medicines_count ?></strong> scheduled</p>
                
                <?php if ($medicines_count > 0): ?>
                    <p class="small">Medicines: <?= htmlspecialchars(substr($med_names, 0, 30)) ?><?= strlen($med_names) > 30 ? '...' : '' ?></p>
                    <p class="small">Check medicine schedule</p>
                <?php else: ?>
                    <p class="small">No medicines scheduled</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="chart-row">
            <div class="chart-card">
                <h3>Calorie Intake vs Burned</h3>
                <div class="chart-container">
                    <canvas id="calorieChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3>Daily Steps</h3>
                <div class="chart-container">
                    <canvas id="stepsChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Nutrient Card -->
        <div class="nutrient-card">
            <h3>Nutrient Distribution - Today</h3>
            <div class="nutrient-container">
                <canvas id="nutrientChart"></canvas>
            </div>
            <div style="text-align: center; margin-top: 10px;">
                <p><strong>Protein:</strong> <?= $protein_percent ?>% | 
                   <strong>Carbs:</strong> <?= $carbs_percent ?>% | 
                   <strong>Fats:</strong> <?= $fats_percent ?>%</p>
            </div>
        </div>
        
        <!-- Appointments Card -->
        <div class="appointments-card">
            <h3>Upcoming Appointments</h3>
            <?php if (count($upcoming_appointments) > 0): ?>
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Symptoms</th>
                    </tr>
                    <?php foreach ($upcoming_appointments as $appt): ?>
                        <tr>
                            <td><?= htmlspecialchars($appt['appointment_date']) ?></td>
                            <td><?= htmlspecialchars(substr($appt['appointment_time'], 0, 5)) ?></td>
                            <td>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></td>
                            <td><?= htmlspecialchars($appt['specialization']) ?></td>
                            <td><?= htmlspecialchars($appt['symptoms'] ?? 'None') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No upcoming appointments</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Font Awesome for Icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    
    <script>
        // Steps Gauge
        const stepsGauge = new Chart(document.getElementById('stepsGauge'), {
            type: 'doughnut',
            data: {
                labels: ['Steps Taken', 'Remaining'],
                datasets: [{
                    data: [<?= $steps_avg ?>, Math.max(10000 - <?= $steps_avg ?>, 0)],
                    backgroundColor: ['#06d6a0', '#e0e0e0'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
        
        // Calorie Chart
        const calorieChart = new Chart(document.getElementById('calorieChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($week)) ?>,
                datasets: [
                    {
                        label: 'Calorie Intake',
                        data: <?= json_encode(array_column($week, 'intake')) ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)'
                    },
                    {
                        label: 'Calorie Burned',
                        data: <?= json_encode(array_column($week, 'burned')) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw.toLocaleString() + ' kcal';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Calories (kcal)' }
                    },
                    x: {
                        title: { display: true, text: 'Date' }
                    }
                }
            }
        });
        
        // Steps Chart
        const stepsChart = new Chart(document.getElementById('stepsChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($week)) ?>,
                datasets: [{
                    label: 'Daily Steps',
                    data: <?= json_encode(array_column($week, 'steps')) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
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
                        title: { display: true, text: 'Steps' }
                    },
                    x: {
                        title: { display: true, text: 'Date' }
                    }
                }
            }
        });
        
        // Nutrient Chart
        const nutrientChart = new Chart(document.getElementById('nutrientChart'), {
            type: 'doughnut',
            data: {
                labels: ['Protein', 'Carbs', 'Fats'],
                datasets: [{
                    data: [<?= $protein_percent ?>, <?= $carbs_percent ?>, <?= $fats_percent ?>],
                    backgroundColor: ['#06d6a0', '#ffbe0b', '#ef476f'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
<?php
session_start();
require_once '../dbPC.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$doctor_id = $_SESSION['user_id'];

// Fetch doctor information
$doctor_query = "SELECT u.name, d.specialization, d.qualification, d.experience_years 
                FROM users u 
                JOIN doctors d ON u.id = d.user_id 
                WHERE u.id = :doctor_id";
$doctor_stmt = $pdo->prepare($doctor_query);
$doctor_stmt->bindParam(':doctor_id', $doctor_id);
$doctor_stmt->execute();
$doctor_info = $doctor_stmt->fetch(PDO::FETCH_ASSOC);

// Today's appointments
$today_appointments_query = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = :doctor_id AND appointment_date = CURDATE()";
$today_stmt = $pdo->prepare($today_appointments_query);
$today_stmt->bindParam(':doctor_id', $doctor_id);
$today_stmt->execute();
$today_appointments = $today_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Upcoming appointments (next 7 days)
$upcoming_query = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = :doctor_id AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$upcoming_stmt = $pdo->prepare($upcoming_query);
$upcoming_stmt->bindParam(':doctor_id', $doctor_id);
$upcoming_stmt->execute();
$upcoming_appointments = $upcoming_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total patients
$total_patients_query = "SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = :doctor_id";
$patients_stmt = $pdo->prepare($total_patients_query);
$patients_stmt->bindParam(':doctor_id', $doctor_id);
$patients_stmt->execute();
$total_patients = $patients_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Appointment status distribution
$status_query = "SELECT appointment_status, COUNT(*) as count FROM appointments WHERE doctor_id = :doctor_id GROUP BY appointment_status";
$status_stmt = $pdo->prepare($status_query);
$status_stmt->bindParam(':doctor_id', $doctor_id);
$status_stmt->execute();
$status_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent appointments for timeline
$recent_query = "SELECT a.*, u.name as patient_name, a.appointment_date, a.appointment_time, a.symptoms 
                FROM appointments a 
                JOIN users u ON a.patient_id = u.id 
                WHERE a.doctor_id = :doctor_id 
                ORDER BY a.appointment_date DESC, a.appointment_time DESC 
                LIMIT 5";
$recent_stmt = $pdo->prepare($recent_query);
$recent_stmt->bindParam(':doctor_id', $doctor_id);
$recent_stmt->execute();
$recent_appointments = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly appointment trends (last 6 months)
$trends_query = "SELECT 
                    DATE_FORMAT(appointment_date, '%Y-%m') as month,
                    COUNT(*) as appointment_count
                FROM appointments 
                WHERE doctor_id = :doctor_id 
                AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
                ORDER BY month";
$trends_stmt = $pdo->prepare($trends_query);
$trends_stmt->bindParam(':doctor_id', $doctor_id);
$trends_stmt->execute();
$monthly_trends = $trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Today's schedule
$schedule_query = "SELECT a.*, u.name as patient_name, u.phone 
                  FROM appointments a 
                  JOIN users u ON a.patient_id = u.id 
                  WHERE a.doctor_id = :doctor_id AND a.appointment_date = CURDATE()
                  ORDER BY a.appointment_time";
$schedule_stmt = $pdo->prepare($schedule_query);
$schedule_stmt->bindParam(':doctor_id', $doctor_id);
$schedule_stmt->execute();
$today_schedule = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.dashboard-container {
    padding: 0;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}

.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.welcome-text h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
    font-weight: 700;
}

.welcome-text p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 5px;
}

.current-time {
    background: rgba(255,255,255,0.2);
    padding: 15px 25px;
    border-radius: 50px;
    backdrop-filter: blur(10px);
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid rgba(255,255,255,0.2);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
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
}

.stat-icon.appointments { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-icon.patients { background: linear-gradient(135deg, #f093fb, #f5576c); }
.stat-icon.upcoming { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.stat-icon.completed { background: linear-gradient(135deg, #43e97b, #38f9d7); }

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
    line-height: 1;
}

.stat-label {
    color: #718096;
    font-weight: 500;
    font-size: 1.1rem;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
    font-size: 0.9rem;
}

.trend-up { color: #10b981; }
.trend-down { color: #ef4444; }

.charts-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

.chart-card {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f7fafc;
}

.chart-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d3748;
}

.chart-container {
    height: 300px;
    position: relative;
}

.bottom-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.schedule-card, .activity-card {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    height: fit-content;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f7fafc;
}

.section-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.schedule-item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    border-radius: 15px;
    background: #f8fafc;
    margin-bottom: 15px;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.schedule-item:hover {
    background: #edf2f7;
    border-left-color: #667eea;
    transform: translateX(5px);
}

.schedule-time {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 12px 20px;
    border-radius: 25px;
    font-weight: 600;
    white-space: nowrap;
    font-size: 0.9rem;
}

.schedule-details h4 {
    color: #2d3748;
    margin-bottom: 5px;
    font-weight: 600;
}

.schedule-details p {
    color: #718096;
    font-size: 0.9rem;
    margin-bottom: 3px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: auto;
}

.status-made { background: #fef3c7; color: #d97706; }
.status-accepted { background: #dcfce7; color: #16a34a; }
.status-done { background: #e0e7ff; color: #4f46e5; }

.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.quick-stat {
    background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.3);
}

.quick-stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
}

.quick-stat-label {
    color: #718096;
    font-size: 0.9rem;
    font-weight: 500;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #718096;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .charts-section {
        grid-template-columns: 1fr;
    }
    
    .bottom-section {
        grid-template-columns: 1fr;
    }
    
    .welcome-section {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

/* Chart Styles */
.chart-container canvas {
    max-height: 300px !important;
}

/* Animation for loading */
.loading-shimmer {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Welcome back, Dr. <?= htmlspecialchars($doctor_info['name'] ?? 'Doctor') ?>!</h1>
                <p><?= htmlspecialchars($doctor_info['specialization'] ?? 'Medical Practitioner') ?></p>
                <p><?= htmlspecialchars($doctor_info['qualification'] ?? '') ?></p>
                <?php if($doctor_info['experience_years']): ?>
                <p><?= $doctor_info['experience_years'] ?> years of experience</p>
                <?php endif; ?>
            </div>
            <div class="current-time">
                <div style="font-size: 1.5rem; font-weight: 600;" id="currentTime"></div>
                <div style="font-size: 0.9rem; opacity: 0.8;" id="currentDate"></div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= $today_appointments ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
                <div class="stat-icon appointments">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-arrow-up"></i>
                <span>Active today</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= $total_patients ?></div>
                    <div class="stat-label">Total Patients</div>
                </div>
                <div class="stat-icon patients">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-arrow-up"></i>
                <span>Growing practice</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= $upcoming_appointments ?></div>
                    <div class="stat-label">Upcoming (7 days)</div>
                </div>
                <div class="stat-icon upcoming">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-calendar-alt"></i>
                <span>Scheduled ahead</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-number"><?= count(array_filter($status_data, function($s) { return $s['appointment_status'] == 'done'; })) > 0 ? array_filter($status_data, function($s) { return $s['appointment_status'] == 'done'; })[0]['count'] : 0 ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-icon completed">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-thumbs-up"></i>
                <span>Treatment success</span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="fas fa-chart-pie" style="margin-right: 10px; color: #667eea;"></i>
                    Appointment Status
                </h3>
            </div>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">
                    <i class="fas fa-chart-line" style="margin-right: 10px; color: #667eea;"></i>
                    Monthly Trends
                </h3>
            </div>
            <div class="chart-container">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Bottom Section -->
    <div class="bottom-section">
        <div class="schedule-card">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Today's Schedule</h3>
            </div>
            
            <?php if (empty($today_schedule)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h4>No appointments scheduled for today</h4>
                    <p>Enjoy your free time or check upcoming appointments</p>
                </div>
            <?php else: ?>
                <?php foreach ($today_schedule as $appointment): ?>
                    <div class="schedule-item">
                        <div class="schedule-time">
                            <?= date('h:i A', strtotime($appointment['appointment_time'])) ?>
                        </div>
                        <div class="schedule-details">
                            <h4><?= htmlspecialchars($appointment['patient_name']) ?></h4>
                            <p><i class="fas fa-phone"></i> <?= htmlspecialchars($appointment['phone']) ?></p>
                            <?php if ($appointment['symptoms']): ?>
                                <p><i class="fas fa-stethoscope"></i> <?= htmlspecialchars($appointment['symptoms']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="status-badge status-<?= $appointment['appointment_status'] ?>">
                            <?= ucfirst($appointment['appointment_status']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="activity-card">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-activity"></i>
                </div>
                <h3>Recent Activity</h3>
            </div>
            
            <?php if (empty($recent_appointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h4>No recent activity</h4>
                    <p>Your recent appointments will appear here</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_appointments as $appointment): ?>
                    <div class="schedule-item">
                        <div class="schedule-time">
                            <?= date('M d', strtotime($appointment['appointment_date'])) ?>
                        </div>
                        <div class="schedule-details">
                            <h4><?= htmlspecialchars($appointment['patient_name']) ?></h4>
                            <p><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($appointment['appointment_time'])) ?></p>
                            <?php if ($appointment['symptoms']): ?>
                                <p><i class="fas fa-notes-medical"></i> <?= htmlspecialchars(substr($appointment['symptoms'], 0, 30)) ?>...</p>
                            <?php endif; ?>
                        </div>
                        <div class="status-badge status-<?= $appointment['appointment_status'] ?>">
                            <?= ucfirst($appointment['appointment_status']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
// Update time
function updateTime() {
    const now = new Date();
    const timeOptions = { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    };
    const dateOptions = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', timeOptions);
    document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', dateOptions);
}

updateTime();
setInterval(updateTime, 1000);

// Status Chart
const statusData = <?= json_encode($status_data) ?>;
const statusLabels = statusData.map(item => item.appointment_status.charAt(0).toUpperCase() + item.appointment_status.slice(1));
const statusCounts = statusData.map(item => item.count);

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusCounts,
            backgroundColor: [
                '#fbbf24',
                '#10b981', 
                '#3b82f6'
            ],
            borderColor: [
                '#f59e0b',
                '#059669',
                '#2563eb'
            ],
            borderWidth: 2,
            hoverOffset: 8
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
                        size: 12,
                        weight: '500'
                    }
                }
            }
        },
        cutout: '60%'
    }
});

// Trends Chart
const trendsData = <?= json_encode($monthly_trends) ?>;
const trendLabels = trendsData.map(item => {
    const date = new Date(item.month + '-01');
    return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
});
const trendCounts = trendsData.map(item => item.appointment_count);

const trendsCtx = document.getElementById('trendsChart').getContext('2d');
new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Appointments',
            data: trendCounts,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#667eea',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8
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
                    font: {
                        size: 12
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 12
                    }
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});

// Add smooth scrolling and animation effects
document.addEventListener('DOMContentLoaded', function() {
    // Animate stats cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Animate schedule items
    const scheduleItems = document.querySelectorAll('.schedule-item');
    scheduleItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, (index * 100) + 500);
    });
});
</script>
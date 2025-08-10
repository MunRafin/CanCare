<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

require_once '../dbPC.php';

$user_id = $_SESSION['user_id'];

// Handle AJAX requests for updating medication status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $log_id = intval($_POST['log_id']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE medicine_log SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$status, $log_id, $user_id]);
        
        echo json_encode(['success' => $result]);
        exit;
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'schedule_date';
$sort_order = $_GET['sort_order'] ?? 'ASC';

// Build query with filters
$where_conditions = ["ml.user_id = ?"];
$params = [$user_id];

if ($search) {
    $where_conditions[] = "(ml.med_name LIKE ? OR m.use_case LIKE ? OR m.core_ingredient LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter) {
    $where_conditions[] = "ml.status = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $where_conditions[] = "ml.schedule_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "ml.schedule_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get medication schedules with medicine details
$query = "
    SELECT 
        ml.*,
        m.producer_name,
        m.core_ingredient,
        m.use_case,
        m.side_effects,
        m.taking_condition,
        p.medicine_name as prescribed_name,
        p.frequency,
        p.duration,
        p.instructions,
        a.appointment_date,
        u.name as doctor_name
    FROM medicine_log ml
    LEFT JOIN medicines m ON ml.med_name = m.medicine_name
    LEFT JOIN prescriptions p ON ml.prescription_id = p.id
    LEFT JOIN appointments a ON ml.appointment_id = a.id
    LEFT JOIN users u ON a.doctor_id = u.id
    WHERE $where_clause
    ORDER BY $sort_by $sort_order, ml.time ASC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as pending
    FROM medicine_log 
    WHERE user_id = ? AND schedule_date >= CURDATE() - INTERVAL 30 DAY
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PersoCare | Medication Schedule</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .total { color: #695CFE; }
        .completed { color: #10B981; }
        .missed { color: #EF4444; }
        .pending { color: #F59E0B; }

        /* Filters Section */
        .filters-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 0.9rem;
        }

        .form-control {
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-control:focus {
            outline: none;
            border-color: #695CFE;
            background: white;
            box-shadow: 0 0 0 3px rgba(105, 92, 254, 0.1);
        }

        .search-btn, .clear-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }

        .search-btn {
            background: linear-gradient(135deg, #695CFE, #9C88FF);
            color: white;
        }

        .clear-btn {
            background: #6B7280;
            color: white;
        }

        .search-btn:hover, .clear-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        /* Medications Grid */
        .medications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .medication-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .medication-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .medication-card.scheduled::before { background: linear-gradient(90deg, #F59E0B, #FBBF24); }
        .medication-card.done::before { background: linear-gradient(90deg, #10B981, #34D399); }
        .medication-card.missed::before { background: linear-gradient(90deg, #EF4444, #F87171); }

        .medication-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .medication-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .medication-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .medication-time {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #695CFE;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: auto;
        }

        .status-scheduled { background: #FEF3C7; color: #92400E; }
        .status-done { background: #D1FAE5; color: #065F46; }
        .status-missed { background: #FEE2E2; color: #991B1B; }

        .medication-details {
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .detail-item i {
            color: #695CFE;
            width: 16px;
            margin-top: 2px;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            min-width: 70px;
        }

        .detail-value {
            color: #666;
            flex: 1;
        }

        .medication-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .action-btn {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-done {
            background: #10B981;
            color: white;
        }

        .btn-missed {
            background: #EF4444;
            color: white;
        }

        .btn-reset {
            background: #6B7280;
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: white;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.7;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .medications-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .medication-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animation */
        .medication-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
            transform: translateY(30px);
        }

        .medication-card:nth-child(1) { animation-delay: 0.1s; }
        .medication-card:nth-child(2) { animation-delay: 0.2s; }
        .medication-card:nth-child(3) { animation-delay: 0.3s; }
        .medication-card:nth-child(4) { animation-delay: 0.4s; }
        .medication-card:nth-child(5) { animation-delay: 0.5s; }
        .medication-card:nth-child(6) { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-pills"></i> Medication Schedule</h1>
            <p>Track your daily medications and stay healthy</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total"><i class="fas fa-capsules"></i></div>
                <div class="stat-number total"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">Total (30 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon completed"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number completed"><?= $stats['completed'] ?? 0 ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon missed"><i class="fas fa-times-circle"></i></div>
                <div class="stat-number missed"><?= $stats['missed'] ?? 0 ?></div>
                <div class="stat-label">Missed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                <div class="stat-number pending"><?= $stats['pending'] ?? 0 ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search Medicine</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Medicine name, condition, ingredient..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="scheduled" <?= $status_filter === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                            <option value="done" <?= $status_filter === 'done' ? 'selected' : '' ?>>Completed</option>
                            <option value="missed" <?= $status_filter === 'missed' ? 'selected' : '' ?>>Missed</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> From Date</label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> To Date</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-sort"></i> Sort By</label>
                        <select name="sort_by" class="form-control">
                            <option value="schedule_date" <?= $sort_by === 'schedule_date' ? 'selected' : '' ?>>Date</option>
                            <option value="time" <?= $sort_by === 'time' ? 'selected' : '' ?>>Time</option>
                            <option value="med_name" <?= $sort_by === 'med_name' ? 'selected' : '' ?>>Medicine Name</option>
                            <option value="status" <?= $sort_by === 'status' ? 'selected' : '' ?>>Status</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <a href="?" class="clear-btn">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Medications Grid -->
        <?php if (empty($medications)): ?>
            <div class="empty-state">
                <i class="fas fa-pills"></i>
                <h3>No Medications Found</h3>
                <p>No medications match your search criteria or you don't have any scheduled medications.</p>
            </div>
        <?php else: ?>
            <div class="medications-grid">
                <?php foreach ($medications as $med): 
                    $schedule_date = new DateTime($med['schedule_date']);
                    $today = new DateTime();
                    $is_today = $schedule_date->format('Y-m-d') === $today->format('Y-m-d');
                    $is_past = $schedule_date < $today;
                ?>
                    <div class="medication-card <?= $med['status'] ?>" id="med-card-<?= $med['id'] ?>">
                        <div class="medication-header">
                            <div>
                                <div class="medication-name"><?= htmlspecialchars($med['med_name']) ?></div>
                                <div class="medication-time">
                                    <i class="fas fa-clock"></i>
                                    <?= date('g:i A', strtotime($med['time'])) ?>
                                    <?php if ($is_today): ?>
                                        <span style="color: #10B981; font-size: 0.8rem;">(Today)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="status-badge status-<?= $med['status'] ?>">
                                <?= ucfirst($med['status']) ?>
                            </div>
                        </div>

                        <div class="medication-details">
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                <span class="detail-label">Date:</span>
                                <span class="detail-value"><?= $schedule_date->format('M d, Y') ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <i class="fas fa-prescription-bottle"></i>
                                <span class="detail-label">Dosage:</span>
                                <span class="detail-value"><?= htmlspecialchars($med['dosage']) ?></span>
                            </div>
                            
                            <?php if ($med['use_case']): ?>
                            <div class="detail-item">
                                <i class="fas fa-stethoscope"></i>
                                <span class="detail-label">For:</span>
                                <span class="detail-value"><?= htmlspecialchars($med['use_case']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($med['core_ingredient']): ?>
                            <div class="detail-item">
                                <i class="fas fa-flask"></i>
                                <span class="detail-label">Ingredient:</span>
                                <span class="detail-value"><?= htmlspecialchars($med['core_ingredient']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($med['taking_condition']): ?>
                            <div class="detail-item">
                                <i class="fas fa-info-circle"></i>
                                <span class="detail-label">Instructions:</span>
                                <span class="detail-value"><?= htmlspecialchars($med['taking_condition']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($med['doctor_name']): ?>
                            <div class="detail-item">
                                <i class="fas fa-user-md"></i>
                                <span class="detail-label">Doctor:</span>
                                <span class="detail-value">Dr. <?= htmlspecialchars($med['doctor_name']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($med['status'] === 'scheduled'): ?>
                        <div class="medication-actions">
                            <button class="action-btn btn-done" onclick="updateStatus(<?= $med['id'] ?>, 'done')">
                                <i class="fas fa-check"></i> Mark Done
                            </button>
                            <button class="action-btn btn-missed" onclick="updateStatus(<?= $med['id'] ?>, 'missed')">
                                <i class="fas fa-times"></i> Mark Missed
                            </button>
                        </div>
                        <?php elseif ($med['status'] !== 'scheduled'): ?>
                        <div class="medication-actions">
                            <button class="action-btn btn-reset" onclick="updateStatus(<?= $med['id'] ?>, 'scheduled')">
                                <i class="fas fa-undo"></i> Reset to Scheduled
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateStatus(medId, newStatus) {
            const card = document.getElementById(`med-card-${medId}`);
            card.classList.add('loading');
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                card.classList.remove('loading');
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Update card appearance
                        card.className = `medication-card ${newStatus}`;
                        
                        // Update status badge
                        const statusBadge = card.querySelector('.status-badge');
                        statusBadge.className = `status-badge status-${newStatus}`;
                        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                        
                        // Update actions
                        const actionsDiv = card.querySelector('.medication-actions');
                        if (newStatus === 'scheduled') {
                            actionsDiv.innerHTML = `
                                <button class="action-btn btn-done" onclick="updateStatus(${medId}, 'done')">
                                    <i class="fas fa-check"></i> Mark Done
                                </button>
                                <button class="action-btn btn-missed" onclick="updateStatus(${medId}, 'missed')">
                                    <i class="fas fa-times"></i> Mark Missed
                                </button>
                            `;
                        } else {
                            actionsDiv.innerHTML = `
                                <button class="action-btn btn-reset" onclick="updateStatus(${medId}, 'scheduled')">
                                    <i class="fas fa-undo"></i> Reset to Scheduled
                                </button>
                            `;
                        }
                        
                        // Show success feedback
                        showNotification(`Medication status updated to ${newStatus}`, 'success');
                        
                        // Update statistics (reload page after short delay)
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                        
                    } else {
                        showNotification('Failed to update medication status', 'error');
                    }
                } catch (e) {
                    showNotification('An error occurred', 'error');
                }
            };
            
            xhr.onerror = function() {
                card.classList.remove('loading');
                showNotification('Network error occurred', 'error');
            };
            
            xhr.send(`action=update_status&log_id=${medId}&status=${newStatus}`);
        }
        
        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 10px;
                color: white;
                font-weight: 600;
                z-index: 1000;
                animation: slideIn 0.3s ease;
                ${type === 'success' ? 'background: #10B981;' : 'background: #EF4444;'}
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
        
        // Add CSS for notification animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
<?php
session_start();
require_once '../dbPC.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date("Y-m-d");

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_medicine'])) {
    $med_name = $_POST['med_name'];
    $dosage = $_POST['dosage'];
    $time = $_POST['time'];
    $schedule_date = $_POST['schedule_date'];
    $status = $_POST['status'];
    $appointment_id = $_POST['appointment_id'] ?: null;
    $prescription_id = $_POST['prescription_id'] ?: null;

    try {
        $stmt = $conn->prepare("
            INSERT INTO medicine_log 
            (user_id, med_name, dosage, time, schedule_date, status, appointment_id, prescription_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, 
            $med_name, 
            $dosage, 
            $time, 
            $schedule_date, 
            $status, 
            $appointment_id, 
            $prescription_id
        ]);
        
        $success = "Medicine entry saved successfully!";
    } catch (PDOException $e) {
        $error = "Error saving medicine entry: " . $e->getMessage();
    }
}

// Fetch existing medicine logs
try {
    $stmt = $conn->prepare("
        SELECT * FROM medicine_log 
        WHERE user_id = ? 
        ORDER BY schedule_date DESC, time DESC
    ");
    $stmt->execute([$user_id]);
    $medicine_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching medicine logs: " . $e->getMessage();
}

// Fetch appointments for dropdown
$appointments = [];
try {
    $stmt = $conn->prepare("
        SELECT id, appointment_date 
        FROM appointments 
        WHERE patient_id = ? 
        ORDER BY appointment_date DESC
    ");
    $stmt->execute([$user_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fail silently if appointments can't be fetched
}

// Fetch prescriptions for dropdown
$prescriptions = [];
try {
    $stmt = $conn->prepare("
        SELECT id, created_at 
        FROM prescriptions 
        WHERE patient_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fail silently if prescriptions can't be fetched
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Log - Personal Care</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f2ff 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #5c7cfa;
            font-size: 18px;
        }
        
        .content-wrapper {
            display: flex;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .form-section {
            flex: 1;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        
        .history-section {
            flex: 1;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            max-height: 600px;
            overflow-y: auto;
        }
        
        .section-title {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e6f2ff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #5c7cfa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dbe4ff;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #5c7cfa;
            outline: none;
            box-shadow: 0 0 0 3px rgba(92, 124, 250, 0.2);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn {
            background: #5c7cfa;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 14px 25px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            margin-top: 15px;
            width: 100%;
            justify-content: center;
        }
        
        .btn:hover {
            background: #3b5bdb;
            transform: translateY(-2px);
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .message-success {
            background: #d3f9d8;
            color: #2b8a3e;
            border-left: 4px solid #2b8a3e;
        }
        
        .message-error {
            background: #ffe3e3;
            color: #c92a2a;
            border-left: 4px solid #c92a2a;
        }
        
        .medicine-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .medicine-table th, 
        .medicine-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #f1f3f9;
        }
        
        .medicine-table th {
            background-color: #f8f9ff;
            color: #5c7cfa;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        .medicine-table tr:hover {
            background-color: #f8f9ff;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-scheduled { background: #fef3c7; color: #92400e; }
        .status-done { background: #dcfce7; color: #166534; }
        .status-missed { background: #ffe3e3; color: #c92a2a; }
        
        .no-entries {
            text-align: center;
            padding: 40px 20px;
            color: #868e96;
        }
        
        .no-entries i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ced4da;
        }
        
        .date-header {
            background: #f1f5ff;
            padding: 8px 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 600;
            color: #3b5bdb;
        }
        
        @media (max-width: 900px) {
            .content-wrapper {
                flex-direction: column;
            }
        }
        
        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .header h1 {
                font-size: 26px;
            }
            
            .section-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-pills"></i> Medicine Log</h1>
            <p>Track your medication schedule and history</p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i>
                <div><?= $success ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>
        
        <div class="content-wrapper">
            <div class="form-section">
                <h2 class="section-title"><i class="fas fa-plus-circle"></i> Add New Medicine Entry</h2>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="med_name">Medicine Name</label>
                            <input type="text" id="med_name" name="med_name" required placeholder="e.g., Aspirin">
                        </div>
                        
                        <div class="form-group">
                            <label for="dosage">Dosage</label>
                            <input type="text" id="dosage" name="dosage" required placeholder="e.g., 500mg">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="time">Time</label>
                            <input type="time" id="time" name="time" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="schedule_date">Schedule Date</label>
                            <input type="date" id="schedule_date" name="schedule_date" required value="<?= $today ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="done" selected>Done</option>
                                <option value="missed">Missed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="appointment_id">Related Appointment (Optional)</label>
                            <select id="appointment_id" name="appointment_id">
                                <option value="">Select Appointment</option>
                                <?php foreach ($appointments as $appointment): ?>
                                    <option value="<?= $appointment['id'] ?>">
                                        <?= date('M d, Y', strtotime($appointment['appointment_date'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="prescription_id">Related Prescription (Optional)</label>
                            <select id="prescription_id" name="prescription_id">
                                <option value="">Select Prescription</option>
                                <?php foreach ($prescriptions as $prescription): ?>
                                    <option value="<?= $prescription['id'] ?>">
                                        <?= date('M d, Y', strtotime($prescription['created_at'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="save_medicine" class="btn">
                        <i class="fas fa-save"></i> Save Medicine Entry
                    </button>
                </form>
            </div>
            
            <div class="history-section">
                <h2 class="section-title"><i class="fas fa-history"></i> Medicine History</h2>
                
                <?php if (!empty($medicine_logs)): ?>
                    <?php
                    // Group logs by date
                    $grouped_logs = [];
                    foreach ($medicine_logs as $log) {
                        $date = date('Y-m-d', strtotime($log['schedule_date']));
                        $grouped_logs[$date][] = $log;
                    }
                    ?>
                    
                    <?php foreach ($grouped_logs as $date => $logs): ?>
                        <div class="date-header">
                            <i class="fas fa-calendar-day"></i> 
                            <?= date('F j, Y', strtotime($date)) ?>
                        </div>
                        
                        <table class="medicine-table">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Dosage</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['med_name']) ?></td>
                                        <td><?= htmlspecialchars($log['dosage']) ?></td>
                                        <td><?= date('g:i A', strtotime($log['time'])) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $log['status'] ?>">
                                                <?= ucfirst($log['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-entries">
                        <i class="fas fa-pills"></i>
                        <h3>No medicine entries found</h3>
                        <p>Add your first medicine entry to start tracking</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Set the time input to the current time
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            document.getElementById('time').value = `${hours}:${minutes}`;
            
            // Set focus to first input
            document.getElementById('med_name').focus();
        });
    </script>
</body>
</html>
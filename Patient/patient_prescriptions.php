<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

require_once '../dbPC.php';

$user_id = $_SESSION['user_id'];

// Get filter parameters
$doctor_filter = $_GET['doctor'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$medicine_filter = $_GET['medicine'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build WHERE clause based on filters
$where_conditions = ["a.patient_id = ?"];
$params = [$user_id];

if (!empty($doctor_filter)) {
    $where_conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "a.appointment_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "a.appointment_date <= ?";
    $params[] = $date_to;
}

if (!empty($medicine_filter)) {
    $where_conditions[] = "p.medicine_name LIKE ?";
    $params[] = "%$medicine_filter%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.appointment_status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get prescriptions with filters
try {
    $stmt = $conn->prepare("
        SELECT 
            p.id as prescription_id,
            p.medicine_name,
            p.dosage,
            p.frequency,
            p.duration,
            p.instructions,
            p.prescribed_at,
            a.id as appointment_id,
            a.appointment_date,
            a.appointment_time,
            a.symptoms,
            a.appointment_status,
            u.name as doctor_name,
            d.specialization,
            d.qualification,
            d.experience_years,
            patient.name as patient_name,
            patient.phone as patient_phone,
            patient.email as patient_email
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN users u ON a.doctor_id = u.id
        JOIN doctors d ON u.id = d.user_id
        JOIN users patient ON a.patient_id = patient.id
        WHERE $where_clause
        ORDER BY a.appointment_date DESC, p.prescribed_at DESC
    ");
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $prescriptions = [];
    $error_message = "Error fetching prescriptions: " . $e->getMessage();
}

// Get unique doctors for filter dropdown
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.name, d.specialization
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        JOIN doctors d ON u.id = d.user_id
        WHERE a.patient_id = ?
        ORDER BY u.name
    ");
    $stmt->execute([$user_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $doctors = [];
}

// Group prescriptions by appointment for better display
$grouped_prescriptions = [];
foreach ($prescriptions as $prescription) {
    $appointment_id = $prescription['appointment_id'];
    if (!isset($grouped_prescriptions[$appointment_id])) {
        $grouped_prescriptions[$appointment_id] = [
            'appointment_info' => $prescription,
            'medicines' => []
        ];
    }
    $grouped_prescriptions[$appointment_id]['medicines'][] = $prescription;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions - CanCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .filters-section {
            background: #f8fafc;
            padding: 30px;
            border-bottom: 1px solid #e2e8f0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 172, 254, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .content {
            padding: 30px;
        }

        .prescription-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            margin-bottom: 25px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .prescription-card:hover {
            border-color: #4facfe;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .prescription-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .doctor-info h3 {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .doctor-info p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .appointment-info {
            text-align: right;
        }

        .appointment-info .date {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .appointment-info .time {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .prescription-body {
            padding: 25px;
        }

        .appointment-details {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .appointment-details h4 {
            color: #374151;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .symptoms {
            color: #6b7280;
            font-style: italic;
        }

        .medicines-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .medicine-card {
            background: #f8faff;
            border: 1px solid #e0e7ff;
            border-radius: 10px;
            padding: 20px;
            position: relative;
        }

        .medicine-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 0 0 0 10px;
        }

        .medicine-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .medicine-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 1rem;
            color: #374151;
            font-weight: 500;
            margin-top: 2px;
        }

        .instructions {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
        }

        .instructions strong {
            color: #92400e;
        }

        .instructions p {
            color: #78350f;
            margin-top: 5px;
        }

        .prescription-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-made {
            background: #fef3c7;
            color: #92400e;
        }

        .status-accepted {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-done {
            background: #d1fae5;
            color: #065f46;
        }

        .no-prescriptions {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .no-prescriptions i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #d1d5db;
        }

        .no-prescriptions h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                margin: 0;
                padding: 0;
            }

            .filters-section,
            .prescription-actions,
            .btn {
                display: none !important;
            }

            .container {
                box-shadow: none;
                border-radius: 0;
            }

            .prescription-card {
                page-break-inside: avoid;
                border: 2px solid #333;
                margin-bottom: 30px;
            }

            .prescription-header {
                background: #333 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .header {
                background: #333 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-prescription-bottle-alt"></i> My Prescriptions</h1>
            <p>View and manage your medical prescriptions</p>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="">
                <input type="hidden" name="page" value="prescriptions">
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="doctor">Doctor</label>
                        <select name="doctor" id="doctor">
                            <option value="">All Doctors</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= $doctor['id'] ?>" <?= $doctor_filter == $doctor['id'] ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($doctor['name']) ?> (<?= htmlspecialchars($doctor['specialization']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="medicine">Medicine Name</label>
                        <input type="text" name="medicine" id="medicine" placeholder="Search medicine..." value="<?= htmlspecialchars($medicine_filter) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="status">Appointment Status</label>
                        <select name="status" id="status">
                            <option value="">All Status</option>
                            <option value="made" <?= $status_filter == 'made' ? 'selected' : '' ?>>Made</option>
                            <option value="accepted" <?= $status_filter == 'accepted' ? 'selected' : '' ?>>Accepted</option>
                            <option value="done" <?= $status_filter == 'done' ? 'selected' : '' ?>>Done</option>
                        </select>
                    </div>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter Prescriptions
                    </button>
                    <a href="?page=prescriptions" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <button type="button" onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print All
                    </button>
                </div>
            </form>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if (isset($error_message)): ?>
                <div class="no-prescriptions">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error</h3>
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php elseif (empty($grouped_prescriptions)): ?>
                <div class="no-prescriptions">
                    <i class="fas fa-prescription-bottle-alt"></i>
                    <h3>No Prescriptions Found</h3>
                    <p>No prescriptions match your current filters. Try adjusting your search criteria.</p>
                </div>
            <?php else: ?>
                <?php foreach ($grouped_prescriptions as $appointment_id => $prescription_group): ?>
                    <?php $appointment = $prescription_group['appointment_info']; ?>
                    <div class="prescription-card" id="prescription-<?= $appointment_id ?>">
                        <!-- Prescription Header -->
                        <div class="prescription-header">
                            <div class="doctor-info">
                                <h3>Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></h3>
                                <p><?= htmlspecialchars($appointment['specialization']) ?></p>
                                <p><?= htmlspecialchars($appointment['qualification']) ?> • <?= $appointment['experience_years'] ?> years exp.</p>
                            </div>
                            <div class="appointment-info">
                                <div class="date"><?= date('M d, Y', strtotime($appointment['appointment_date'])) ?></div>
                                <div class="time"><?= date('h:i A', strtotime($appointment['appointment_time'])) ?></div>
                                <span class="status-badge status-<?= $appointment['appointment_status'] ?>">
                                    <?= ucfirst($appointment['appointment_status']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Prescription Body -->
                        <div class="prescription-body">
                            <!-- Patient Info -->
                            <div class="appointment-details">
                                <h4><i class="fas fa-user"></i> Patient Information</h4>
                                <p><strong>Name:</strong> <?= htmlspecialchars($appointment['patient_name']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($appointment['patient_phone']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($appointment['patient_email']) ?></p>
                                <?php if (!empty($appointment['symptoms'])): ?>
                                    <p><strong>Symptoms:</strong> <span class="symptoms"><?= htmlspecialchars($appointment['symptoms']) ?></span></p>
                                <?php endif; ?>
                            </div>

                            <!-- Medicines -->
                            <div class="medicines-grid">
                                <?php foreach ($prescription_group['medicines'] as $medicine): ?>
                                    <div class="medicine-card">
                                        <div class="medicine-name">
                                            <i class="fas fa-pills"></i>
                                            <?= htmlspecialchars($medicine['medicine_name']) ?>
                                        </div>
                                        
                                        <div class="medicine-details">
                                            <div class="detail-item">
                                                <span class="detail-label">Dosage</span>
                                                <span class="detail-value"><?= htmlspecialchars($medicine['dosage']) ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Frequency</span>
                                                <span class="detail-value"><?= htmlspecialchars($medicine['frequency']) ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Duration</span>
                                                <span class="detail-value"><?= htmlspecialchars($medicine['duration']) ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Prescribed</span>
                                                <span class="detail-value"><?= date('M d, Y', strtotime($medicine['prescribed_at'])) ?></span>
                                            </div>
                                        </div>

                                        <?php if (!empty($medicine['instructions'])): ?>
                                            <div class="instructions">
                                                <strong><i class="fas fa-info-circle"></i> Instructions:</strong>
                                                <p><?= htmlspecialchars($medicine['instructions']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Prescription Actions -->
                        <div class="prescription-actions">
                            <button onclick="printPrescription(<?= $appointment_id ?>)" class="btn btn-primary">
                                <i class="fas fa-print"></i> Print This Prescription
                            </button>
                            <a href="?page=appointment&id=<?= $appointment_id ?>" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> View Appointment
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function printPrescription(appointmentId) {
            // Hide all prescription cards except the selected one
            const allCards = document.querySelectorAll('.prescription-card');
            const selectedCard = document.getElementById('prescription-' + appointmentId);
            
            allCards.forEach(card => {
                if (card !== selectedCard) {
                    card.style.display = 'none';
                }
            });
            
            // Print
            window.print();
            
            // Show all cards again after printing
            setTimeout(() => {
                allCards.forEach(card => {
                    card.style.display = 'block';
                });
            }, 100);
        }

        // Auto-focus on search fields
        document.addEventListener('DOMContentLoaded', function() {
            const medicineInput = document.getElementById('medicine');
            if (medicineInput.value === '') {
                medicineInput.focus();
            }
        });
    </script>
</body>
</html>
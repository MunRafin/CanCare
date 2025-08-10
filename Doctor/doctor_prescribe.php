<?php
session_start();
require_once '../dbPC.php';

// Verify doctor authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

// Initialize variables
$success = '';
$error = '';

// Handle prescription submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prescription'])) {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $medicine_name = trim($_POST['medicine_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    
    // Validation
    if (!$appointment_id || empty($medicine_name) || empty($dosage) || empty($frequency) || empty($duration)) {
        $error = "All required fields must be filled out.";
    } else {
        try {
            // Verify appointment belongs to this doctor and is accepted
            $verify_stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ? AND appointment_status = 'accepted'");
            $verify_stmt->execute([$appointment_id, $_SESSION['user_id']]);
            
            if (!$verify_stmt->fetch()) {
                $error = "Invalid appointment or you don't have permission to prescribe for this appointment.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO prescriptions (appointment_id, medicine_name, dosage, frequency, duration, instructions) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$appointment_id, $medicine_name, $dosage, $frequency, $duration, $instructions]);
                $success = "Prescription added successfully!";
                
                // Clear form data after successful submission
                $_POST = array();
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get accepted appointments for current doctor
$doctor_id = $_SESSION['user_id'];
try {
    $appointments_query = "
        SELECT a.id, a.appointment_date, a.appointment_time, a.symptoms, 
               u.name as patient_name, u.phone as patient_phone
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        WHERE a.doctor_id = ? AND a.appointment_status = 'accepted'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ";
    $appointments_stmt = $pdo->prepare($appointments_query);
    $appointments_stmt->execute([$doctor_id]);
    $appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching appointments: " . $e->getMessage();
    $appointments = [];
}

// Get selected appointment details if exists
$selected_appointment = null;
if (isset($_GET['selected_appointment']) && !empty($_GET['selected_appointment'])) {
    $selected_id = filter_input(INPUT_GET, 'selected_appointment', FILTER_VALIDATE_INT);
    if ($selected_id) {
        foreach ($appointments as $appointment) {
            if ($appointment['id'] == $selected_id) {
                $selected_appointment = $appointment;
                break;
            }
        }
    }
}

// Get existing prescriptions for selected appointment
$existing_prescriptions = [];
if ($selected_appointment) {
    try {
        $prescriptions_query = "SELECT * FROM prescriptions WHERE appointment_id = ? ORDER BY prescribed_at DESC";
        $prescriptions_stmt = $pdo->prepare($prescriptions_query);
        $prescriptions_stmt->execute([$selected_appointment['id']]);
        $existing_prescriptions = $prescriptions_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching prescriptions: " . $e->getMessage();
    }
}

// Get medicines from database for autocomplete
$medicines = [];
try {
    $medicines_stmt = $pdo->prepare("SELECT medicine_name, dosage FROM medicines ORDER BY medicine_name");
    $medicines_stmt->execute();
    $medicines = $medicines_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Continue without medicines data if table doesn't exist or has issues
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Prescription</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        .prescribe-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 35px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .card-header {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 24px 30px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header i {
            font-size: 1.3rem;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .required {
            color: #ef4444;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
            background: #fefffe;
        }

        .form-control:hover {
            border-color: #cbd5e1;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }

        .btn {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            border: 2px solid transparent;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.3);
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #475569 0%, #334155 100%);
            box-shadow: 0 8px 25px rgba(100, 116, 139, 0.3);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid;
        }

        .alert.success {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert.error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #b91c1c;
            border-color: #fecaca;
        }

        .appointment-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 24px;
            border-radius: 12px;
            border-left: 5px solid #0ea5e9;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .appointment-info .info-row {
            display: flex;
            margin-bottom: 12px;
            align-items: flex-start;
        }

        .appointment-info .info-row:last-child {
            margin-bottom: 0;
        }

        .appointment-info strong {
            color: #0c4a6e;
            min-width: 100px;
            display: inline-block;
            font-weight: 600;
        }

        .appointment-info .info-content {
            flex: 1;
            color: #1e293b;
        }

        select.form-control {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            cursor: pointer;
        }

        .no-appointments {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .no-appointments i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .no-appointments h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #475569;
        }

        .existing-prescriptions {
            margin-top: 30px;
        }

        .prescription-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .prescription-item h4 {
            color: #1e293b;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .prescription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .prescription-detail {
            display: flex;
            flex-direction: column;
        }

        .prescription-detail strong {
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .prescription-detail span {
            color: #1e293b;
            font-weight: 500;
        }

        .prescription-instructions {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #059669;
            margin-top: 10px;
        }

        .prescription-meta {
            text-align: right;
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 10px;
        }

        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .prescribe-container {
                margin: 1rem auto;
                padding: 0 15px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .appointment-info {
                padding: 20px;
            }
            
            .prescription-details {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="prescribe-container">
        <?php if (isset($success)): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> 
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> 
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-search"></i>
                <span>Select Patient Appointment</span>
            </div>
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                    <div class="no-appointments">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Accepted Appointments</h3>
                        <p>You don't have any accepted appointments available for prescription.</p>
                    </div>
                <?php else: ?>
                    <form method="GET" id="appointmentForm">
                        <div class="form-group">
                            <label for="selected_appointment">Choose Appointment <span class="required">*</span></label>
                            <select name="selected_appointment" id="selected_appointment" class="form-control" onchange="this.form.submit()">
                                <option value="">Select an appointment to prescribe medication</option>
                                <?php foreach ($appointments as $appointment): ?>
                                    <option value="<?= $appointment['id'] ?>" 
                                        <?= (isset($_GET['selected_appointment']) && $_GET['selected_appointment'] == $appointment['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($appointment['patient_name']) ?> - 
                                        <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?> at 
                                        <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_appointment): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-injured"></i>
                    <span>Appointment Details</span>
                </div>
                <div class="card-body">
                    <div class="appointment-info">
                        <div class="info-row">
                            <strong>Patient:</strong>
                            <div class="info-content"><?= htmlspecialchars($selected_appointment['patient_name']) ?></div>
                        </div>
                        <div class="info-row">
                            <strong>Date:</strong>
                            <div class="info-content"><?= date('F j, Y', strtotime($selected_appointment['appointment_date'])) ?></div>
                        </div>
                        <div class="info-row">
                            <strong>Time:</strong>
                            <div class="info-content"><?= date('g:i A', strtotime($selected_appointment['appointment_time'])) ?></div>
                        </div>
                        <?php if ($selected_appointment['symptoms']): ?>
                            <div class="info-row">
                                <strong>Symptoms:</strong>
                                <div class="info-content"><?= htmlspecialchars($selected_appointment['symptoms']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-prescription-bottle-alt"></i>
                    <span>Write New Prescription</span>
                </div>
                <div class="card-body">
                    <form method="POST" id="prescriptionForm">
                        <input type="hidden" name="appointment_id" value="<?= $selected_appointment['id'] ?>">
                        
                        <div class="form-group">
                            <label for="medicine_name">Medicine Name <span class="required">*</span></label>
                            <input type="text" 
                                   name="medicine_name" 
                                   id="medicine_name"
                                   class="form-control" 
                                   required 
                                   placeholder="e.g., Paracetamol 500mg"
                                   autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="dosage">Dosage <span class="required">*</span></label>
                            <input type="text" 
                                   name="dosage" 
                                   id="dosage"
                                   class="form-control" 
                                   required 
                                   placeholder="e.g., 1 tablet, 5ml syrup, 2 capsules"
                                   autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="frequency">Frequency <span class="required">*</span></label>
                            <select name="frequency" id="frequency" class="form-control" required>
                                <option value="">Select frequency</option>
                                <option value="Once daily">Once daily</option>
                                <option value="Twice daily">Twice daily</option>
                                <option value="Three times daily">Three times daily</option>
                                <option value="Four times daily">Four times daily</option>
                                <option value="Every 4 hours">Every 4 hours</option>
                                <option value="Every 6 hours">Every 6 hours</option>
                                <option value="Every 8 hours">Every 8 hours</option>
                                <option value="After meals">After meals</option>
                                <option value="Before meals">Before meals</option>
                                <option value="At bedtime">At bedtime</option>
                                <option value="As needed">As needed (PRN)</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Monthly">Monthly</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration <span class="required">*</span></label>
                            <select name="duration" id="duration" class="form-control" required>
                                <option value="">Select duration</option>
                                <option value="1 day">1 day</option>
                                <option value="3 days">3 days</option>
                                <option value="5 days">5 days</option>
                                <option value="7 days">7 days</option>
                                <option value="10 days">10 days</option>
                                <option value="14 days">14 days</option>
                                <option value="21 days">21 days</option>
                                <option value="1 month">1 month</option>
                                <option value="2 months">2 months</option>
                                <option value="3 months">3 months</option>
                                <option value="Until finished">Until finished</option>
                                <option value="Ongoing">Ongoing</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="instructions">Special Instructions</label>
                            <textarea name="instructions" 
                                      id="instructions"
                                      class="form-control" 
                                      placeholder="e.g., Take with food, Avoid driving, Store in refrigerator, Complete full course"></textarea>
                        </div>
                        
                        <button type="submit" name="submit_prescription" class="btn">
                            <i class="fas fa-paper-plane"></i>
                            Submit Prescription
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($existing_prescriptions)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <span>Previously Prescribed Medications</span>
                    </div>
                    <div class="card-body">
                        <div class="existing-prescriptions">
                            <?php foreach ($existing_prescriptions as $prescription): ?>
                                <div class="prescription-item">
                                    <h4><?= htmlspecialchars($prescription['medicine_name']) ?></h4>
                                    <div class="prescription-details">
                                        <div class="prescription-detail">
                                            <strong>Dosage</strong>
                                            <span><?= htmlspecialchars($prescription['dosage']) ?></span>
                                        </div>
                                        <div class="prescription-detail">
                                            <strong>Frequency</strong>
                                            <span><?= htmlspecialchars($prescription['frequency']) ?></span>
                                        </div>
                                        <div class="prescription-detail">
                                            <strong>Duration</strong>
                                            <span><?= htmlspecialchars($prescription['duration']) ?></span>
                                        </div>
                                    </div>
                                    <?php if ($prescription['instructions']): ?>
                                        <div class="prescription-instructions">
                                            <strong>Instructions:</strong> <?= htmlspecialchars($prescription['instructions']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="prescription-meta">
                                        Prescribed on <?= date('M j, Y \a\t g:i A', strtotime($prescription['prescribed_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Add loading states
        document.getElementById('appointmentForm')?.addEventListener('submit', function() {
            document.querySelector('.prescribe-container').classList.add('loading');
        });

        document.getElementById('prescriptionForm')?.addEventListener('submit', function(e) {
            const btn = e.target.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            btn.disabled = true;
        });

        // Auto-save form data to prevent loss
        const form = document.getElementById('prescriptionForm');
        if (form) {
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    localStorage.setItem('prescription_' + this.name, this.value);
                });
                
                // Restore saved data
                const saved = localStorage.getItem('prescription_' + input.name);
                if (saved && !input.value) {
                    input.value = saved;
                }
            });

            // Clear saved data on successful submission
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    inputs.forEach(input => {
                        localStorage.removeItem('prescription_' + input.name);
                    });
                }, 1000);
            });
        }

        // Auto-focus first input
        const firstInput = document.querySelector('#medicine_name');
        if (firstInput) {
            firstInput.focus();
        }
    </script>
</body>
</html>
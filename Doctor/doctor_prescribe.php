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
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $patient_name = trim($_POST['patient_name'] ?? '');
    $doctor_name = trim($_POST['doctor_name'] ?? '');
    $symptoms = trim($_POST['symptoms'] ?? '');
    $prescribed_medicines = trim($_POST['prescribed_medicines'] ?? '');
    $suggested_habits = trim($_POST['suggested_habits'] ?? '');
    $suggested_supplements = trim($_POST['suggested_supplements'] ?? '');
    $suggested_diet = trim($_POST['suggested_diet'] ?? '');
    $medical_tests = trim($_POST['medical_tests'] ?? '');

    // Validation
    if (!$appointment_id || !$patient_id || !$doctor_id || empty($patient_name) || empty($doctor_name)) {
        $error = "All required appointment fields must be filled out.";
    } elseif (empty($prescribed_medicines) && empty($suggested_habits) && empty($suggested_supplements) && empty($suggested_diet) && empty($medical_tests)) {
        $error = "At least one prescription field (medicines, habits, supplements, diet, or tests) must be filled out.";
    } else {
        try {
            // Verify appointment belongs to this doctor and is accepted
            $verify_stmt = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ? AND appointment_status = 'accepted'");
            $verify_stmt->execute([$appointment_id, $_SESSION['user_id']]);

            if (!$verify_stmt->fetch()) {
                $error = "Invalid appointment or you don't have permission to prescribe for this appointment.";
            } else {
                // Prepare a single comprehensive instruction string
                $comprehensive_instructions = "Suggested Habits/Activities: " . ($suggested_habits ?: "None specified") . "\n"
                                            . "Suggested Supplements: " . ($suggested_supplements ?: "None specified") . "\n"
                                            . "Suggested Diet: " . ($suggested_diet ?: "None specified");

                // Prepare a single string for medical tests
                $medical_tests_string = $medical_tests ?: "None specified";

                // Split the medicines string into individual lines
                $medicine_lines = explode("\n", $prescribed_medicines);
                $is_any_medicine_inserted = false;

                foreach ($medicine_lines as $line) {
                    $line = trim($line);
                    if (!empty($line)) {
                        // Assuming the format: [Medicine Name], [Dosage], [Frequency], [Duration]
                        // Simple example: "Paracetamol 500mg, 1 tablet, 3 times a day, 7 days"
                        // This is a simple parsing method, you may need a more robust one
                        $parts = explode(',', $line);
                        $medicine_name = trim($parts[0] ?? '');
                        $dosage = trim($parts[1] ?? 'N/A');
                        $frequency = trim($parts[2] ?? 'N/A');
                        $duration = trim($parts[3] ?? 'N/A');

                        // Insert into prescriptions table
                        $stmt = $conn->prepare("INSERT INTO prescriptions (appointment_id, medicine_name, dosage, frequency, duration, instructions, medical_tests) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        
                        $stmt->execute([
                            $appointment_id,
                            $medicine_name,
                            $dosage,
                            $frequency,
                            $duration,
                            $comprehensive_instructions,
                            $medical_tests_string
                        ]);
                        $is_any_medicine_inserted = true;
                    }
                }
                
                // If no medicines were provided, insert a single row with other instructions
                if (!$is_any_medicine_inserted) {
                    $stmt = $conn->prepare("INSERT INTO prescriptions (appointment_id, medicine_name, dosage, frequency, duration, instructions, medical_tests) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $appointment_id,
                        'N/A',
                        'N/A',
                        'N/A',
                        'N/A',
                        $comprehensive_instructions,
                        $medical_tests_string
                    ]);
                }

                // Update appointment status to 'prescribed'
                $update_stmt = $conn->prepare("UPDATE appointments SET appointment_status = 'prescribed' WHERE id = ?");
                $update_stmt->execute([$appointment_id]);

                // Redirect to avoid form re-submission
                header("Location: doctor_prescribe.php?success=true");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get accepted appointments for current doctor
$doctor_id = $_SESSION['user_id'];
$appointments = [];
try {
    $appointments_query = "
        SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time, a.symptoms, 
               u.name as patient_name, u.phone as patient_phone
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        WHERE a.doctor_id = ? AND a.appointment_status = 'accepted'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ";
    $appointments_stmt = $conn->prepare($appointments_query);
    $appointments_stmt->execute([$doctor_id]);
    $appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching appointments: " . $e->getMessage();
}

// Get doctor name
$doctor_name = '';
try {
    $doctor_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $doctor_stmt->execute([$doctor_id]);
    $doctor_result = $doctor_stmt->fetch(PDO::FETCH_ASSOC);
    $doctor_name = $doctor_result ? $doctor_result['name'] : 'Unknown Doctor';
} catch (PDOException $e) {
    $doctor_name = 'Unknown Doctor';
}

// Get prescribed appointments to show completion status
$prescribed_appointments = [];
try {
    $prescribed_query = "
        SELECT a.id, a.appointment_date, a.appointment_time, 
               u.name as patient_name, p.prescribed_at
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        JOIN prescriptions p ON a.id = p.appointment_id
        WHERE a.doctor_id = ? AND a.appointment_status = 'prescribed'
        ORDER BY p.prescribed_at DESC
        LIMIT 10
    ";
    $prescribed_stmt = $conn->prepare($prescribed_query);
    $prescribed_stmt->execute([$doctor_id]);
    $prescribed_appointments = $prescribed_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Continue without prescribed appointments if there's an error
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
            max-width: 1200px;
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
        }

        .form-control:disabled {
            background-color: #f1f5f9;
            color: #64748b;
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
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.3);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .btn-primary:hover {
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
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

        .appointment-item {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .appointment-details h4 {
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .appointment-meta {
            color: #64748b;
            font-size: 0.9rem;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .no-appointments {
            text-align: center;
            color: #64748b;
            font-style: italic;
            padding: 20px;
        }

        .action-button {
            display: flex;
            gap: 10px;
        }

        .action-button .btn {
            white-space: nowrap;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .prescribe-container {
                padding: 0 15px;
            }
            .card-body {
                padding: 20px;
            }
            .appointment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .action-button {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>

    <div class="prescribe-container">
        
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1 style="font-size: 2rem; color: #059669; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-prescription-bottle-alt"></i> Doctor Prescription
            </h1>
        </header>

        <?php if ($success) : ?>
            <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error) : ?>
            <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-list-ul"></i> Accepted Appointments
            </div>
            <div class="card-body">
                <?php if (!empty($appointments)) : ?>
                    <p style="margin-bottom: 20px; color: #64748b;">Select an appointment to write a prescription.</p>
                    <?php foreach ($appointments as $appointment) : ?>
                        <div class="appointment-item">
                            <div class="appointment-details">
                                <h4>Appointment with: <?php echo htmlspecialchars($appointment['patient_name']); ?></h4>
                                <div class="appointment-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars(date('M d, Y', strtotime($appointment['appointment_date']))); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars(date('h:i A', strtotime($appointment['appointment_time']))); ?></span>
                                    <span><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?></span>
                                </div>
                                <p style="margin-top: 10px; color: #475569;">Symptoms: <?php echo htmlspecialchars($appointment['symptoms']); ?></p>
                            </div>
                            <div class="action-button">
                                <a href="#prescription-form" class="btn btn-small"
                                   onclick="populateForm(
                                        <?php echo $appointment['id']; ?>, 
                                        <?php echo $appointment['patient_id']; ?>, 
                                        '<?php echo htmlspecialchars($appointment['patient_name'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($appointment['symptoms'], ENT_QUOTES); ?>'
                                   )">
                                   <i class="fas fa-plus"></i> Prescribe
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="no-appointments">No accepted appointments to prescribe for at this time.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" id="prescription-form">
            <div class="card-header">
                <i class="fas fa-edit"></i> Write New Prescription
            </div>
            <div class="card-body">
                <form action="doctor_prescribe.php" method="POST">
                    <div class="form-group">
                        <label for="appointment_id">Appointment ID <span class="required">*</span></label>
                        <input type="number" id="appointment_id" name="appointment_id" class="form-control" required readonly>
                    </div>
                    <div class="form-group" style="display: none;">
                        <label for="patient_id">Patient ID</label>
                        <input type="hidden" id="patient_id" name="patient_id" class="form-control" required>
                    </div>
                    <div class="form-group" style="display: none;">
                        <label for="doctor_id">Doctor ID</label>
                        <input type="hidden" id="doctor_id" name="doctor_id" class="form-control" value="<?php echo $_SESSION['user_id']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="patient_name">Patient Name <span class="required">*</span></label>
                        <input type="text" id="patient_name" name="patient_name" class="form-control" required readonly>
                    </div>

                    <div class="form-group">
                        <label for="doctor_name">Doctor Name <span class="required">*</span></label>
                        <input type="text" id="doctor_name" name="doctor_name" class="form-control" value="<?php echo htmlspecialchars($doctor_name); ?>" required readonly>
                    </div>

                    <div class="form-group">
                        <label for="symptoms">Symptoms</label>
                        <textarea id="symptoms" name="symptoms" class="form-control" readonly></textarea>
                    </div>

                    <div style="background-color: #f1f5f9; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                        <h5 style="font-weight: 600; margin-bottom: 15px; color: #1e293b;">Prescription Details: <span class="required">* (at least one field)</span></h5>
                        
                        <div class="form-group">
                            <label for="prescribed_medicines">Prescribed Medicines <br><small>Format: Medicine Name, Dosage, Frequency, Duration (one per line)</small></label>
                            <textarea id="prescribed_medicines" name="prescribed_medicines" class="form-control" placeholder="e.g., Paracetamol 500mg, 1 tablet, 3 times a day, 7 days"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="suggested_habits">Suggested Habits & Activities</label>
                            <textarea id="suggested_habits" name="suggested_habits" class="form-control" placeholder="e.g., Get 8 hours of sleep, daily 30-min walk, quit smoking..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="suggested_supplements">Suggested Supplements</label>
                            <textarea id="suggested_supplements" name="suggested_supplements" class="form-control" placeholder="e.g., Vitamin D3, Omega-3 fatty acids..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="suggested_diet">Suggested Diet</label>
                            <textarea id="suggested_diet" name="suggested_diet" class="form-control" placeholder="e.g., Eat more leafy greens, reduce sugar intake, increase protein..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="medical_tests">Medical Tests</label>
                            <textarea id="medical_tests" name="medical_tests" class="form-control" placeholder="e.g., CBC, Lipid Panel, Blood Sugar..."></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_prescription" class="btn"><i class="fas fa-prescription"></i> Create Prescription</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i> Last 10 Prescribed Appointments
            </div>
            <div class="card-body">
                <?php if (!empty($prescribed_appointments)) : ?>
                    <?php foreach ($prescribed_appointments as $appointment) : ?>
                        <div class="appointment-item">
                            <div class="appointment-details">
                                <h4>Appointment with: <?php echo htmlspecialchars($appointment['patient_name']); ?></h4>
                                <div class="appointment-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars(date('M d, Y', strtotime($appointment['appointment_date']))); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars(date('h:i A', strtotime($appointment['appointment_time']))); ?></span>
                                    <span><i class="fas fa-file-prescription"></i> Prescribed At: <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($appointment['prescribed_at']))); ?></span>
                                </div>
                            </div>
                            <div class="action-button">
                                <span class="badge" style="background-color: #059669; color: white; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">
                                    <i class="fas fa-check-circle"></i> Completed
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="no-appointments">No prescriptions have been completed yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <script>
        function populateForm(appointment_id, patient_id, patient_name, symptoms) {
            document.getElementById('appointment_id').value = appointment_id;
            document.getElementById('patient_id').value = patient_id;
            document.getElementById('patient_name').value = patient_name;
            document.getElementById('symptoms').value = symptoms;
            
            // Clear prescription fields for new form
            document.getElementById('prescribed_medicines').value = '';
            document.getElementById('suggested_habits').value = '';
            document.getElementById('suggested_supplements').value = '';
            document.getElementById('suggested_diet').value = '';
            document.getElementById('medical_tests').value = '';
        }

        // Check URL for success message and scroll to form if needed
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'true') {
                document.getElementById('prescription-form').scrollIntoView({ behavior: 'smooth' });
            }
        };
    </script>
</body>
</html>
<?php
session_start();
require_once '../dbPC.php'; // Adjust path if needed

// Verify user authentication and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit;
}

$prescription_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$prescription_id) {
    die("Invalid prescription ID.");
}

// Fetch all prescription details using a transaction
$conn->beginTransaction();
try {
    // Main prescription record
    $main_stmt = $conn->prepare("
        SELECT
            p.prescribed_at,
            pat.name AS patient_name,
            pat.phone AS patient_phone,
            pat.email AS patient_email,
            doc.name AS doctor_name,
            d.specialization AS doctor_specialization,
            a.symptoms
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN users pat ON a.patient_id = pat.id
        JOIN users doc ON a.doctor_id = doc.id
        LEFT JOIN doctors d ON doc.id = d.user_id
        WHERE p.id = ? AND a.patient_id = ?
    ");
    $main_stmt->execute([$prescription_id, $_SESSION['user_id']]);
    $prescription = $main_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prescription) {
        die("Prescription not found or you do not have permission to view it.");
    }

    // Prescribed medicines
    $medicines_stmt = $conn->prepare("SELECT * FROM prescription_medicines WHERE prescription_id = ?");
    $medicines_stmt->execute([$prescription_id]);
    $medicines = $medicines_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Suggested habits
    $habits_stmt = $conn->prepare("SELECT habit FROM prescription_habits WHERE prescription_id = ?");
    $habits_stmt->execute([$prescription_id]);
    $habits = $habits_stmt->fetch(PDO::FETCH_ASSOC);

    // Suggested supplements
    $supplements_stmt = $conn->prepare("SELECT supplement FROM prescription_supplements WHERE prescription_id = ?");
    $supplements_stmt->execute([$prescription_id]);
    $supplements = $supplements_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Suggested diet
    $diet_stmt = $conn->prepare("SELECT diet FROM prescription_diet WHERE prescription_id = ?");
    $diet_stmt->execute([$prescription_id]);
    $diet = $diet_stmt->fetch(PDO::FETCH_ASSOC);

    // Medical tests
    $tests_stmt = $conn->prepare("SELECT medical_test FROM prescription_medical_tests WHERE prescription_id = ?");
    $tests_stmt->execute([$prescription_id]);
    $tests = $tests_stmt->fetch(PDO::FETCH_ASSOC);

    $conn->commit();
} catch (PDOException $e) {
    $conn->rollBack();
    die("Error fetching prescription details: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Report - #<?php echo htmlspecialchars($prescription_id); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .report-container {
            width: 100%;
            max-width: 850px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: 20px;
        }
        .report-header {
            text-align: center;
            border-bottom: 2px solid #059669;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .report-header h1 {
            color: #059669;
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        .report-header p {
            color: #64748b;
            font-size: 1rem;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            background: linear-gradient(135deg, #e2e8f0 0%, #f1f5f9 100%);
            color: #1e293b;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
        }
        .info-item i {
            color: #059669;
            font-size: 1.2rem;
        }
        .info-item strong {
            color: #475569;
        }
        ul.prescription-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .prescription-list li {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.2s ease-in-out;
        }
        .prescription-list li:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .prescription-item-header {
            font-weight: 600;
            color: #059669;
            font-size: 1.1rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .prescription-item-details {
            font-size: 0.9rem;
            color: #64748b;
            margin-left: 28px;
        }
        .prescription-item-details span {
            display: block;
        }
        p.content-text {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            white-space: pre-wrap;
            line-height: 1.7;
            color: #475569;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            margin-top: 30px;
            border-top: 2px solid #e2e8f0;
            color: #64748b;
            font-size: 0.9rem;
        }

        @media print {
            body { background: white; }
            .report-container { box-shadow: none; border: none; }
            .section-title { background: #f1f5f9; }
            .info-item i { color: #059669; }
            .prescription-item-header { color: #059669; }
            .btn-print { display: none; }
        }

        .btn-print {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1e293b;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn-print:hover {
            background: #334155;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <a href="javascript:window.print()" class="btn-print"><i class="fas fa-print"></i> Print Report</a>
        
        <header class="report-header">
            <h1>CanCare Medical Prescription</h1>
            <p>Prescription issued on: <?php echo htmlspecialchars(date('F d, Y', strtotime($prescription['prescribed_at']))); ?></p>
        </header>

        <section class="section">
            <div class="section-title"><i class="fas fa-user-md"></i> Patient & Doctor Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <i class="fas fa-user-injured"></i>
                    <div>
                        <strong>Patient:</strong>
                        <span><?php echo htmlspecialchars($prescription['patient_name']); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-phone-alt"></i>
                    <div>
                        <strong>Contact:</strong>
                        <span><?php echo htmlspecialchars($prescription['patient_phone']); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-user-md"></i>
                    <div>
                        <strong>Doctor:</strong>
                        <span>Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-stethoscope"></i>
                    <div>
                        <strong>Specialization:</strong>
                        <span><?php echo htmlspecialchars($prescription['doctor_specialization'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-title"><i class="fas fa-notes-medical"></i> Symptoms</div>
            <p class="content-text"><?php echo htmlspecialchars($prescription['symptoms']); ?></p>
        </section>

        <?php if (!empty($medicines)): ?>
        <section class="section">
            <div class="section-title"><i class="fas fa-pills"></i> Prescribed Medicines</div>
            <ul class="prescription-list">
                <?php foreach ($medicines as $med): ?>
                <li>
                    <div class="prescription-item-header"><i class="fas fa-prescription-bottle-alt"></i> <?php echo htmlspecialchars($med['medicine_name']); ?></div>
                    <div class="prescription-item-details">
                        <span><strong>Dosage:</strong> <?php echo htmlspecialchars($med['dosage']); ?></span>
                        <span><strong>Frequency:</strong> <?php echo htmlspecialchars($med['frequency']); ?></span>
                        <span><strong>Duration:</strong> <?php echo htmlspecialchars($med['duration']); ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if (!empty($habits['habit'])): ?>
        <section class="section">
            <div class="section-title"><i class="fas fa-running"></i> Suggested Habits & Activities</div>
            <p class="content-text"><?php echo nl2br(htmlspecialchars($habits['habit'])); ?></p>
        </section>
        <?php endif; ?>

        <?php if (!empty($supplements['supplement'])): ?>
        <section class="section">
            <div class="section-title"><i class="fas fa-capsules"></i> Suggested Supplements</div>
            <p class="content-text"><?php echo nl2br(htmlspecialchars($supplements['supplement'])); ?></p>
        </section>
        <?php endif; ?>
        
        <?php if (!empty($diet['diet'])): ?>
        <section class="section">
            <div class="section-title"><i class="fas fa-utensils"></i> Suggested Diet</div>
            <p class="content-text"><?php echo nl2br(htmlspecialchars($diet['diet'])); ?></p>
        </section>
        <?php endif; ?>

        <?php if (!empty($tests['medical_test'])): ?>
        <section class="section">
            <div class="section-title"><i class="fas fa-file-medical-alt"></i> Medical Tests</div>
            <p class="content-text"><?php echo nl2br(htmlspecialchars($tests['medical_test'])); ?></p>
        </section>
        <?php endif; ?>

        <footer class="footer">
            <p>&copy; <?php echo date("Y"); ?> CanCare. All rights reserved.</p>
            <p>This is a digital copy of your prescription. Please consult your doctor for any clarifications.</p>
        </footer>
    </div>
</body>
</html>
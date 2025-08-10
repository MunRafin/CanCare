<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

require_once '../dbPC.php';

$user_id = $_SESSION['user_id'];

// Handle AJAX appointment booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_appointment') {
    $doctor_id = intval($_POST['doctor_id']);
    $date = $_POST['date'];       // format: YYYY-MM-DD
    $time = $_POST['time'];       // format: HH:MM:SS
    $symptoms = $_POST['symptoms'] ?? null;

    $hour_start = substr($time, 0, 2) . ':00:00';
    $hour_end = substr($time, 0, 2) . ':59:59';

    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time BETWEEN ? AND ?");
    $stmt->execute([$doctor_id, $date, $hour_start, $hour_end]);
    $count = $stmt->fetchColumn();

    if ($count >= 10) {
        echo json_encode(['status' => 'error', 'message' => 'This time slot is fully booked. Please choose another time.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ?");
    $stmt->execute([$user_id, $date, $time]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You already have an appointment at this time.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, appointment_status, created_at) VALUES (?, ?, ?, ?, ?, 'made', NOW())");
    $result = $stmt->execute([$user_id, $doctor_id, $date, $time, $symptoms]);

    if ($result) {
        echo json_encode(['status' => 'success', 'message' => 'Appointment booked successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to book appointment. Please try again.']);
    }
    exit;
}

// Fetch doctors with profile images
$stmt = $conn->prepare("SELECT u.id as user_id, u.name, u.email, d.qualification, d.specialization, d.service_days, d.service_time, d.profile_image FROM users u JOIN doctors d ON u.id = d.user_id WHERE u.role = 'doctor'");
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

function parseServiceDays($str) {
    return array_map('trim', explode(',', $str));
}

$maxDays = 7;
$todayStr = (new DateTime('today'))->format('Y-m-d');
$maxDate = (new DateTime('today'))->modify("+$maxDays days")->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PersoCare | Book Appointment</title>
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
            margin-bottom: 40px;
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

        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .doctor-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .doctor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #695CFE, #9C88FF);
        }

        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .doctor-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .doctor-image-container {
            position: relative;
        }

        .doctor-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #695CFE;
            box-shadow: 0 4px 15px rgba(105, 92, 254, 0.3);
        }

        .doctor-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            background: #10B981;
            border: 3px solid white;
            border-radius: 50%;
        }

        .doctor-info h2 {
            font-size: 1.5rem;
            color: #1a1a1a;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .doctor-info .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 0.9rem;
            color: #666;
        }

        .doctor-info .info-item i {
            color: #695CFE;
            width: 16px;
        }

        .service-days, .service-time {
            font-weight: 600;
            color: #695CFE;
        }

        .appointment-section {
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
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

        .form-control:hover {
            border-color: #695CFE;
            background: white;
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .book-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #695CFE, #9C88FF);
            border: none;
            color: white;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .book-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .book-btn:hover::before {
            left: 100%;
        }

        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(105, 92, 254, 0.4);
        }

        .book-btn:active {
            transform: translateY(0);
        }

        .message {
            margin-top: 15px;
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
        }

        .success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .specialty-badge {
            display: inline-block;
            background: linear-gradient(135deg, #695CFE, #9C88FF);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 8px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .doctors-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .doctors-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .doctor-header {
                flex-direction: column;
                text-align: center;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .doctor-card {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .doctor-card {
                padding: 15px;
            }
        }

        /* Animation for cards */
        .doctor-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
            transform: translateY(30px);
        }

        .doctor-card:nth-child(1) { animation-delay: 0.1s; }
        .doctor-card:nth-child(2) { animation-delay: 0.2s; }
        .doctor-card:nth-child(3) { animation-delay: 0.3s; }
        .doctor-card:nth-child(4) { animation-delay: 0.4s; }
        .doctor-card:nth-child(5) { animation-delay: 0.5s; }
        .doctor-card:nth-child(6) { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Book Your Appointment</h1>
            <p>Choose from our qualified doctors and schedule your visit</p>
        </div>

        <div class="doctors-grid">
            <?php foreach ($doctors as $doc):
                $serviceTime = $doc['service_time'];
                $imagePath = "../zphotos/" . htmlspecialchars($doc['profile_image']);
                if (!file_exists($imagePath)) {
                    $imagePath = "../zphotos/doctor.png"; // fallback image
                }
            ?>
            <div class="doctor-card">
                <div class="doctor-header">
                    <div class="doctor-image-container">
                        <img src="<?= $imagePath ?>" alt="Dr <?= htmlspecialchars($doc['name']) ?>" class="doctor-image">
                        <div class="doctor-status" title="Available"></div>
                    </div>
                    <div class="doctor-info">
                        <h2>Dr. <?= htmlspecialchars($doc['name']) ?></h2>
                        <div class="info-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?= htmlspecialchars($doc['qualification']) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-stethoscope"></i>
                            <span><?= htmlspecialchars($doc['specialization']) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="service-days"><?= htmlspecialchars($doc['service_days']) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <span class="service-time"><?= htmlspecialchars($serviceTime) ?></span>
                        </div>
                        <div class="specialty-badge"><?= htmlspecialchars($doc['specialization']) ?></div>
                    </div>
                </div>

                <div class="appointment-section">
                    <div class="form-group">
                        <label for="date-<?= $doc['user_id'] ?>">
                            <i class="fas fa-calendar"></i> Select Date:
                        </label>
                        <input type="date" id="date-<?= $doc['user_id'] ?>" name="date" 
                               min="<?= $todayStr ?>" max="<?= $maxDate ?>" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="time-<?= $doc['user_id'] ?>">
                            <i class="fas fa-clock"></i> Select Time:
                        </label>
                        <select id="time-<?= $doc['user_id'] ?>" name="time" class="form-control">
                            <option value="">--Select time--</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="symptoms-<?= $doc['user_id'] ?>">
                            <i class="fas fa-notes-medical"></i> Symptoms (optional):
                        </label>
                        <textarea id="symptoms-<?= $doc['user_id'] ?>" name="symptoms" 
                                  class="form-control" placeholder="Describe your symptoms..."></textarea>
                    </div>

                    <button class="book-btn" onclick="bookAppointment(<?= $doc['user_id'] ?>)">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </button>
                    <div id="message-<?= $doc['user_id'] ?>" class="message" style="display: none;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function parseTime12to24(time12h) {
            const [time, modifier] = time12h.trim().split(' ');
            let [hours, minutes] = time.split(':');
            hours = parseInt(hours, 10);
            if (modifier === 'PM' && hours !== 12) hours += 12;
            if (modifier === 'AM' && hours === 12) hours = 0;
            return (hours < 10 ? '0' : '') + hours + ':00:00';
        }

        function updateTimeSlots(doctorId, serviceTime, dateId, timeId) {
            const dateInput = document.getElementById(dateId);
            const timeSelect = document.getElementById(timeId);
            const selectedDateStr = dateInput.value;
            
            // Clear existing options
            timeSelect.innerHTML = '<option value="">Select a time</option>';
            
            if (!selectedDateStr) return;

            // Parse service time range (e.g., "09:00 AM - 12:00 PM")
            const [startTimeStr, endTimeStr] = serviceTime.split('-').map(s => s.trim());
            
            // Convert to 24-hour format
            const startTime = parseTime12to24(startTimeStr);
            const endTime = parseTime12to24(endTimeStr);
            
            // Extract hours
            const startHour = parseInt(startTime.split(':')[0], 10);
            const endHour = parseInt(endTime.split(':')[0], 10);
            
            // Generate time slots (one per hour)
            for (let h = startHour; h < endHour; h++) {
                const hourStr = (h < 10 ? '0' : '') + h + ':00:00';
                const displayHour = h % 12 === 0 ? 12 : h % 12;
                const ampm = h >= 12 ? 'PM' : 'AM';
                
                const option = document.createElement('option');
                option.value = hourStr;
                option.textContent = `${displayHour}:00 ${ampm}`;
                timeSelect.appendChild(option);
            }
        }

        function bookAppointment(doctorId) {
            const dateInput = document.getElementById(`date-${doctorId}`);
            const timeSelect = document.getElementById(`time-${doctorId}`);
            const symptomsInput = document.getElementById(`symptoms-${doctorId}`);
            const messageElem = document.getElementById(`message-${doctorId}`);

            if (!dateInput.value) {
                showMessage(messageElem, "Please select an appointment date.", "error");
                return;
            }
            if (!timeSelect.value) {
                showMessage(messageElem, "Please select an appointment time.", "error");
                return;
            }

            const confirmed = confirm(`Confirm appointment with Doctor on ${dateInput.value} at ${timeSelect.options[timeSelect.selectedIndex].text}?`);
            if (!confirmed) return;

            // Show loading state
            const bookBtn = event.target;
            const originalText = bookBtn.innerHTML;
            bookBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
            bookBtn.disabled = true;

            const xhr = new XMLHttpRequest();
            xhr.open("POST", "", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            const symptoms = encodeURIComponent(symptomsInput.value || '');
            
            xhr.onload = function() {
                bookBtn.innerHTML = originalText;
                bookBtn.disabled = false;
                
                try {
                    const res = JSON.parse(xhr.responseText);
                    showMessage(messageElem, res.message, res.status);
                    
                    if (res.status === 'success') {
                        // Reset form on success
                        dateInput.value = '';
                        timeSelect.innerHTML = '<option value="">--Select time--</option>';
                        symptomsInput.value = '';
                    }
                } catch {
                    showMessage(messageElem, "Unexpected response from server.", "error");
                }
            };
            
            xhr.onerror = function() {
                bookBtn.innerHTML = originalText;
                bookBtn.disabled = false;
                showMessage(messageElem, "Network error. Please try again.", "error");
            };
            
            xhr.send(`action=book_appointment&doctor_id=${doctorId}&date=${dateInput.value}&time=${timeSelect.value}&symptoms=${symptoms}`);
        }

        function showMessage(messageElem, text, type) {
            messageElem.textContent = text;
            messageElem.className = `message ${type}`;
            messageElem.style.display = 'block';
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    messageElem.style.display = 'none';
                }, 5000);
            }
        }

        // Initialize event listeners for date changes
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[type="date"]').forEach(dateInput => {
                dateInput.addEventListener('change', function() {
                    const doctorId = this.id.split('-')[1];
                    const doctorCard = this.closest('.doctor-card');
                    const serviceTime = doctorCard.querySelector('.service-time').textContent;
                    updateTimeSlots(doctorId, serviceTime, this.id, `time-${doctorId}`);
                });
            });
        });
    </script>
</body>
</html>
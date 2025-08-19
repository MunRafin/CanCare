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
    // Set content type to JSON immediately to prevent browser parsing issues
    header('Content-Type: application/json');
    
    try {
        $doctor_id = intval($_POST['doctor_id']);
        $date = $_POST['date'];    // format: YYYY-MM-DD
        $time = $_POST['time'];    // format: HH:MM:SS
        $symptoms = $_POST['symptoms'] ?? null;

        // Validate inputs
        if (empty($doctor_id) || empty($date) || empty($time)) {
            throw new Exception('Please fill in all required fields.');
        }

        // Validate date format
        if (!DateTime::createFromFormat('Y-m-d', $date)) {
            throw new Exception('Invalid date format.');
        }

        // Validate time format
        if (!DateTime::createFromFormat('H:i:s', $time)) {
            throw new Exception('Invalid time format.');
        }

        // Check if date is not in the past
        $selectedDate = new DateTime($date);
        $today = new DateTime('today');
        if ($selectedDate < $today) {
            throw new Exception('Cannot book appointments for past dates.');
        }

        // Check if doctor exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users u JOIN doctors d ON u.id = d.user_id WHERE u.id = ? AND u.role = 'doctor'");
        $stmt->execute([$doctor_id]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Selected doctor not found.');
        }

        $hour_start = substr($time, 0, 2) . ':00:00';
        $hour_end = substr($time, 0, 2) . ':59:59';

        // Check if the time slot is fully booked (up to 10 appointments per hour)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time BETWEEN ? AND ?");
        $stmt->execute([$doctor_id, $date, $hour_start, $hour_end]);
        $count = $stmt->fetchColumn();

        if ($count >= 10) {
            throw new Exception('This time slot is fully booked. Please choose another time.');
        }

        // Check if the patient already has an appointment at this specific time
        $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ?");
        $stmt->execute([$user_id, $date, $time]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('You already have an appointment at this time.');
        }

        // Check if patient has another appointment with the same doctor on the same date
        $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ?");
        $stmt->execute([$user_id, $doctor_id, $date]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('You already have an appointment with this doctor on this date.');
        }

        // Begin transaction
        $conn->beginTransaction();

        // Insert the new appointment using the correct column names from your table
        $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, appointment_status, created_at) VALUES (?, ?, ?, ?, ?, 'made', NOW())");
        $result = $stmt->execute([$user_id, $doctor_id, $date, $time, $symptoms]);

        if (!$result) {
            throw new Exception('Failed to insert appointment into database.');
        }

        // Commit transaction
        $conn->commit();

        // Get doctor name for confirmation
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctorName = $stmt->fetchColumn();

        echo json_encode([
            'status' => 'success', 
            'message' => "Appointment booked successfully with Dr. " . htmlspecialchars($doctorName)
        ]);

    } catch (Exception $e) {
        // Rollback transaction if it was started
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        
        // Use a single, clear error log entry
        error_log("Appointment booking error: " . $e->getMessage() . " | Patient ID: " . (isset($user_id) ? $user_id : 'N/A') . " | Doctor ID: " . (isset($doctor_id) ? $doctor_id : 'N/A'));

        echo json_encode([
            'status' => 'error', 
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Fetch doctors with profile images based on search term
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    $stmt = $conn->prepare("SELECT u.id as user_id, u.name, u.email, d.qualification, d.specialization, d.service_days, d.service_time, d.profile_image FROM users u JOIN doctors d ON u.id = d.user_id WHERE u.role = 'doctor' AND (u.name LIKE ? OR d.specialization LIKE ? OR d.qualification LIKE ?) ORDER BY u.name");
    $search_param = '%' . $search_term . '%';
    $stmt->execute([$search_param, $search_param, $search_param]);
} else {
    $stmt = $conn->prepare("SELECT u.id as user_id, u.name, u.email, d.qualification, d.specialization, d.service_days, d.service_time, d.profile_image FROM users u JOIN doctors d ON u.id = d.user_id WHERE u.role = 'doctor' ORDER BY u.name");
    $stmt->execute();
}
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

        /* Search Section */
        .search-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input-wrapper {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #695CFE;
            box-shadow: 0 0 0 3px rgba(105, 92, 254, 0.1);
        }

        .search-btn, .clear-btn {
            padding: 15px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 120px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .search-btn {
            background: linear-gradient(135deg, #695CFE, #9C88FF);
            color: white;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(105, 92, 254, 0.4);
        }

        .clear-btn {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e1e5e9;
        }

        .clear-btn:hover {
            background: #e9ecef;
            color: #495057;
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        /* Results counter */
        .results-info {
            background: rgba(105, 92, 254, 0.1);
            color: #695CFE;
            padding: 10px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        /* Doctors Grid - 3 per row */
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #695CFE;
            box-shadow: 0 4px 15px rgba(105, 92, 254, 0.3);
        }

        .doctor-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 16px;
            height: 16px;
            background: #10B981;
            border: 3px solid white;
            border-radius: 50%;
        }

        .doctor-info h2 {
            font-size: 1.3rem;
            color: #1a1a1a;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .doctor-info .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 0.85rem;
            color: #666;
        }

        .doctor-info .info-item i {
            color: #695CFE;
            width: 14px;
        }

        .service-days, .service-time {
            font-weight: 600;
            color: #695CFE;
        }

        .appointment-section {
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
            font-size: 0.85rem;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 13px;
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
            min-height: 70px;
        }

        .book-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #695CFE, #9C88FF);
            border: none;
            color: white;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
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

        .book-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(105, 92, 254, 0.4);
        }

        .book-btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .book-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .message {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            font-size: 0.85rem;
            display: none;
        }

        .message.success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .message.error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .specialty-badge {
            display: inline-block;
            background: linear-gradient(135deg, #695CFE, #9C88FF);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 8px;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            color: #666;
        }

        .no-results i {
            font-size: 3rem;
            color: #695CFE;
            margin-bottom: 20px;
        }

        .no-results h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #333;
        }

        /* Loading state for buttons */
        .loading {
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input-wrapper {
                min-width: auto;
            }

            .search-btn, .clear-btn {
                min-width: auto;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .doctor-card {
                padding: 15px;
            }

            .search-section {
                padding: 20px;
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

        <div class="search-section">
            <form method="GET" class="search-form">
                <div class="search-input-wrapper">
                    <input type="text" name="search" class="search-input" 
                           placeholder="Search doctors by name or specialization (e.g., Cardiology, Endocrinology)"
                           value="<?= htmlspecialchars($search_term) ?>">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="clear-btn">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>
        </div>

        <?php if (!empty($search_term) || count($doctors) > 0): ?>
        <div class="results-info">
            <?php if (!empty($search_term)): ?>
                Search results for "<?= htmlspecialchars($search_term) ?>": Found <?= count($doctors) ?> doctor(s)
            <?php else: ?>
                Showing all <?= count($doctors) ?> available doctors
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (count($doctors) > 0): ?>
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
                    <form class="appointment-form" data-doctor-id="<?= $doc['user_id'] ?>">
                        <div class="form-group">
                            <label for="date-<?= $doc['user_id'] ?>">
                                <i class="fas fa-calendar"></i> Select Date:
                            </label>
                            <input type="date" id="date-<?= $doc['user_id'] ?>" name="date" 
                                   min="<?= $todayStr ?>" max="<?= $maxDate ?>" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="time-<?= $doc['user_id'] ?>">
                                <i class="fas fa-clock"></i> Select Time:
                            </label>
                            <select id="time-<?= $doc['user_id'] ?>" name="time" class="form-control" required>
                                <option value="">--Select time--</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="symptoms-<?= $doc['user_id'] ?>">
                                <i class="fas fa-notes-medical"></i> Symptoms (optional):
                            </label>
                            <textarea id="symptoms-<?= $doc['user_id'] ?>" name="symptoms" 
                                     class="form-control" placeholder="Describe your symptoms..." maxlength="500"></textarea>
                        </div>

                        <button type="button" class="book-btn" onclick="bookAppointment(<?= $doc['user_id'] ?>, this)">
                            <i class="fas fa-calendar-plus"></i> Book Appointment
                        </button>
                        <div id="message-<?= $doc['user_id'] ?>" class="message"></div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-results">
            <i class="fas fa-search"></i>
            <h3>No Doctors Found</h3>
            <p>We couldn't find any doctors matching your search criteria "<?= htmlspecialchars($search_term) ?>". Try adjusting your search terms or clear the search to see all available doctors.</p>
        </div>
        <?php endif; ?>
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
            timeSelect.innerHTML = '<option value="">--Select time--</option>';
            
            if (!selectedDateStr) return;

            try {
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
            } catch (error) {
                console.error('Error parsing service time:', error);
                showMessage(doctorId, 'Error loading time slots. Please refresh the page.', 'error');
            }
        }

        function bookAppointment(doctorId, buttonElement) {
            const form = buttonElement.closest('.appointment-form');
            const dateInput = document.getElementById(`date-${doctorId}`);
            const timeSelect = document.getElementById(`time-${doctorId}`);
            const symptomsInput = document.getElementById(`symptoms-${doctorId}`);

            // Validate inputs
            if (!dateInput.value) {
                showMessage(doctorId, "Please select an appointment date.", "error");
                dateInput.focus();
                return;
            }
            if (!timeSelect.value) {
                showMessage(doctorId, "Please select an appointment time.", "error");
                timeSelect.focus();
                return;
            }

            // Confirm appointment
            const selectedTime = timeSelect.options[timeSelect.selectedIndex].text;
            const selectedDate = new Date(dateInput.value).toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const confirmed = confirm(`Confirm appointment for ${selectedDate} at ${selectedTime}?`);
            if (!confirmed) return;

            // Show loading state
            const originalText = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
            buttonElement.disabled = true;

            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'book_appointment');
            formData.append('doctor_id', doctorId);
            formData.append('date', dateInput.value);
            formData.append('time', timeSelect.value);
            formData.append('symptoms', symptomsInput.value || '');

            // Send request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                showMessage(doctorId, data.message, data.status);
                
                if (data.status === 'success') {
                    // Reset form on success
                    dateInput.value = '';
                    timeSelect.innerHTML = '<option value="">--Select time--</option>';
                    symptomsInput.value = '';
                }
            })
            .catch(error => {
                console.error('Booking error:', error);
                showMessage(doctorId, 'Network error. Please check your connection and try again.', 'error');
            })
            .finally(() => {
                // Reset button state
                buttonElement.innerHTML = originalText;
                buttonElement.disabled = false;
            });
        }

        function showMessage(doctorId, text, type) {
            const messageElem = document.getElementById(`message-${doctorId}`);
            messageElem.textContent = text;
            messageElem.className = `message ${type}`;
            messageElem.style.display = 'block';
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    messageElem.style.display = 'none';
                }, 5000);
            }
            
            // Scroll to message
            messageElem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Initialize event listeners when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners for date changes
            document.querySelectorAll('input[type="date"]').forEach(dateInput => {
                dateInput.addEventListener('change', function() {
                    const doctorId = this.id.split('-')[1];
                    const doctorCard = this.closest('.doctor-card');
                    const serviceTime = doctorCard.querySelector('.service-time').textContent;
                    updateTimeSlots(doctorId, serviceTime, this.id, `time-${doctorId}`);
                });
            });

            // Add input validation
            document.querySelectorAll('textarea[name="symptoms"]').forEach(textarea => {
                const maxLength = parseInt(textarea.getAttribute('maxlength'));
                if (maxLength) {
                    textarea.addEventListener('input', function() {
                        if (this.value.length > maxLength) {
                            this.value = this.value.substring(0, maxLength);
                        }
                    });
                }
            });

            // Prevent form submission on Enter key in textarea
            document.querySelectorAll('textarea').forEach(textarea => {
                textarea.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                    }
                });
            });

            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.querySelectorAll('input[type="date"]').forEach(dateInput => {
                if (!dateInput.getAttribute('min')) {
                    dateInput.setAttribute('min', today);
                }
            });
        });

        // Add error handling for image loading
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.doctor-image').forEach(img => {
                img.addEventListener('error', function() {
                    this.src = '../zphotos/doctor.png';
                });
            });
        });

        // Prevent double-clicking on book buttons
    </script>
</body>
</html>
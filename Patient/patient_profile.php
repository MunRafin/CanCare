<?php
require_once '../dbPC.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}



$user_id = $_SESSION['user_id'];

// Fetch basic user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch additional patient info
$stmt = $conn->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate age from DOB
$age = null;
if ($user['dob']) {
    $dob = new DateTime($user['dob']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $height = $_POST['height'] ?? null;
    $weight = $_POST['weight'] ?? null;
    $blood_type = $_POST['blood_type'] ?? null;
    $allergies = $_POST['allergies'] ?? null;
    $past_diseases = $_POST['past_diseases'] ?? null;
    $current_medications = $_POST['current_medications'] ?? null;
    $emergency_contact_name = $_POST['emergency_contact_name'] ?? null;
    $emergency_contact_phone = $_POST['emergency_contact_phone'] ?? null;
    $insurance_provider = $_POST['insurance_provider'] ?? null;
    $insurance_policy_number = $_POST['insurance_policy_number'] ?? null;
    $smoking_status = $_POST['smoking_status'] ?? null;
    $alcohol_consumption = $_POST['alcohol_consumption'] ?? null;
    $exercise_frequency = $_POST['exercise_frequency'] ?? null;
    $dietary_restrictions = $_POST['dietary_restrictions'] ?? null;

    // Remove profile picture upload logic
    $profile_picture = null;

    if ($patient) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE patients SET 
            height_cm = ?, weight_kg = ?, blood_type = ?, allergies = ?, past_diseases = ?, 
            current_medications = ?, emergency_contact_name = ?, emergency_contact_phone = ?, 
            insurance_provider = ?, insurance_policy_number = ?, smoking_status = ?, 
            alcohol_consumption = ?, exercise_frequency = ?, dietary_restrictions = ?, 
            profile_picture = ? 
            WHERE user_id = ?");
        $stmt->execute([
            $height, $weight, $blood_type, $allergies, $past_diseases, 
            $current_medications, $emergency_contact_name, $emergency_contact_phone, 
            $insurance_provider, $insurance_policy_number, $smoking_status, 
            $alcohol_consumption, $exercise_frequency, $dietary_restrictions, 
            $profile_picture, $user_id
        ]);
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO patients (
            user_id, height_cm, weight_kg, blood_type, allergies, past_diseases, 
            current_medications, emergency_contact_name, emergency_contact_phone, 
            insurance_provider, insurance_policy_number, smoking_status, 
            alcohol_consumption, exercise_frequency, dietary_restrictions, profile_picture
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id, $height, $weight, $blood_type, $allergies, $past_diseases, 
            $current_medications, $emergency_contact_name, $emergency_contact_phone, 
            $insurance_provider, $insurance_policy_number, $smoking_status, 
            $alcohol_consumption, $exercise_frequency, $dietary_restrictions, $profile_picture
        ]);
    }

    // Refresh patient data
    $stmt = $conn->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    $success_message = "Profile updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | CanCare</title>
    <style>
        :root {
            --primary-color: #695CFE;
            --secondary-color: #4a3db7;
            --light-gray: #f5f5f5;
            --medium-gray: #e0e0e0;
            --dark-gray: #333;
            --white: #fff;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light-gray);
            color: var(--dark-gray);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .profile-header {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }
        
        .profile-name {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .profile-meta {
            color: #666;
            margin-bottom: 20px;
        }
        
        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        
        .profile-sidebar {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .profile-main {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }
        
        .info-value {
            padding: 8px 12px;
            background-color: var(--light-gray);
            border-radius: 5px;
        }
        
        .edit-profile-btn {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
            margin-top: 20px;
            width: 100%;
        }
        
        .edit-profile-btn:hover {
            background-color: var(--secondary-color);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--medium-gray);
            border-radius: 5px;
            font-size: 14px;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .save-btn {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        
        .save-btn:hover {
            background-color: var(--secondary-color);
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .profile-picture-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-picture-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 5px solid var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-header">
            <?php
            // The profile picture is now a static image, no longer user-changeable
            $profile_pic = '../zphotos/profile_img.png';
            ?>
            <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" class="profile-picture">
            <h1 class="profile-name"><?= htmlspecialchars($user['name']) ?></h1>
            <div class="profile-meta">
                <?= htmlspecialchars($age) ?> years • <?= htmlspecialchars($user['gender']) ?> • <?= htmlspecialchars($user['address']) ?>
            </div>
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= $patient ? htmlspecialchars($patient['height_cm'] ?? '--') : '--' ?></div>
                    <div class="stat-label">Height (cm)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $patient ? htmlspecialchars($patient['weight_kg'] ?? '--') : '--' ?></div>
                    <div class="stat-label">Weight (kg)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $patient ? htmlspecialchars($patient['blood_type'] ?? '--') : '--' ?></div>
                    <div class="stat-label">Blood Type</div>
                </div>
            </div>
        </div>
        
        <div class="profile-content">
            <div class="profile-sidebar">
                <h2 class="section-title">About</h2>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone</div>
                    <div class="info-value"><?= htmlspecialchars($user['phone']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date of Birth</div>
                    <div class="info-value"><?= htmlspecialchars(date('F j, Y', strtotime($user['dob']))) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Address</div>
                    <div class="info-value"><?= htmlspecialchars($user['address']) ?></div>
                </div>
                
                <h2 class="section-title" style="margin-top: 30px;">Health Summary</h2>
                <div class="info-item">
                    <div class="info-label">Allergies</div>
                    <div class="info-value"><?= $patient ? nl2br(htmlspecialchars($patient['allergies'] ?? 'Not specified')) : 'Not specified' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Past Diseases</div>
                    <div class="info-value"><?= $patient ? nl2br(htmlspecialchars($patient['past_diseases'] ?? 'Not specified')) : 'Not specified' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Current Medications</div>
                    <div class="info-value"><?= $patient ? nl2br(htmlspecialchars($patient['current_medications'] ?? 'None')) : 'None' ?></div>
                </div>
                
                <button onclick="document.getElementById('edit-profile-form').scrollIntoView()" class="edit-profile-btn">
                    Edit Profile
                </button>
            </div>
            
            <div class="profile-main">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                
                <h2 class="section-title">Edit Profile</h2>
                <form id="edit-profile-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="profile-picture-upload">
                        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture Preview" class="profile-picture-preview" id="profile-picture-preview">
                    </div>
                    
                    <div class="form-group">
                        <label for="height" class="form-label">Height (cm)</label>
                        <input type="number" id="height" name="height" class="form-control" 
                               value="<?= $patient ? htmlspecialchars($patient['height_cm'] ?? '') : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="weight" class="form-label">Weight (kg)</label>
                        <input type="number" step="0.1" id="weight" name="weight" class="form-control" 
                               value="<?= $patient ? htmlspecialchars($patient['weight_kg'] ?? '') : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="blood_type" class="form-label">Blood Type</label>
                        <select id="blood_type" name="blood_type" class="form-control">
                            <option value="">Select blood type</option>
                            <option value="A+" <?= ($patient['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                            <option value="A-" <?= ($patient['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                            <option value="B+" <?= ($patient['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                            <option value="B-" <?= ($patient['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                            <option value="AB+" <?= ($patient['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                            <option value="AB-" <?= ($patient['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                            <option value="O+" <?= ($patient['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                            <option value="O-" <?= ($patient['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="allergies" class="form-label">Allergies</label>
                        <textarea id="allergies" name="allergies" class="form-control"><?= $patient ? htmlspecialchars($patient['allergies'] ?? '') : '' ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="past_diseases" class="form-label">Past Diseases</label>
                        <textarea id="past_diseases" name="past_diseases" class="form-control"><?= $patient ? htmlspecialchars($patient['past_diseases'] ?? '') : '' ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="current_medications" class="form-label">Current Medications</label>
                        <textarea id="current_medications" name="current_medications" class="form-control"><?= $patient ? htmlspecialchars($patient['current_medications'] ?? '') : '' ?></textarea>
                    </div>
                    
                    <h3 class="section-title">Emergency Contact</h3>
                    <div class="form-group">
                        <label for="emergency_contact_name" class="form-label">Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" 
                               value="<?= $patient ? htmlspecialchars($patient['emergency_contact_name'] ?? '') : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact_phone" class="form-label">Phone</label>
                        <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" 
                               value="<?= $patient ? htmlspecialchars($patient['emergency_contact_phone'] ?? '') : '' ?>">
                    </div>
                    
                    <h3 class="section-title">Insurance Information</h3>
                    <div class="form-group">
                        <label for="insurance_provider" class="form-label">Provider</label>
                        <input type="text" id="insurance_provider" name="insurance_provider" class="form-control" 
                               value="<?= $patient ? htmlspecialchars($patient['insurance_provider'] ?? '') : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="insurance_policy_number" class="form-label">Policy Number</label>
                        <input type="text" id="insurance_policy_number" name="insurance_policy_number" class="form-control" 
                               value="<?= $patient ? htmlspecialchars($patient['insurance_policy_number'] ?? '') : '' ?>">
                    </div>
                    
                    <h3 class="section-title">Lifestyle</h3>
                    <div class="form-group">
                        <label for="smoking_status" class="form-label">Smoking Status</label>
                        <select id="smoking_status" name="smoking_status" class="form-control">
                            <option value="">Select status</option>
                            <option value="Never" <?= ($patient['smoking_status'] ?? '') === 'Never' ? 'selected' : '' ?>>Never</option>
                            <option value="Former" <?= ($patient['smoking_status'] ?? '') === 'Former' ? 'selected' : '' ?>>Former</option>
                            <option value="Current" <?= ($patient['smoking_status'] ?? '') === 'Current' ? 'selected' : '' ?>>Current</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="alcohol_consumption" class="form-label">Alcohol Consumption</label>
                        <select id="alcohol_consumption" name="alcohol_consumption" class="form-control">
                            <option value="">Select frequency</option>
                            <option value="Never" <?= ($patient['alcohol_consumption'] ?? '') === 'Never' ? 'selected' : '' ?>>Never</option>
                            <option value="Occasionally" <?= ($patient['alcohol_consumption'] ?? '') === 'Occasionally' ? 'selected' : '' ?>>Occasionally</option>
                            <option value="Regularly" <?= ($patient['alcohol_consumption'] ?? '') === 'Regularly' ? 'selected' : '' ?>>Regularly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="exercise_frequency" class="form-label">Exercise Frequency</label>
                        <select id="exercise_frequency" name="exercise_frequency" class="form-control">
                            <option value="">Select frequency</option>
                            <option value="Never" <?= ($patient['exercise_frequency'] ?? '') === 'Never' ? 'selected' : '' ?>>Never</option>
                            <option value="Rarely" <?= ($patient['exercise_frequency'] ?? '') === 'Rarely' ? 'selected' : '' ?>>Rarely</option>
                            <option value="Weekly" <?= ($patient['exercise_frequency'] ?? '') === 'Weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="Daily" <?= ($patient['exercise_frequency'] ?? '') === 'Daily' ? 'selected' : '' ?>>Daily</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="dietary_restrictions" class="form-label">Dietary Restrictions</label>
                        <textarea id="dietary_restrictions" name="dietary_restrictions" class="form-control"><?= $patient ? htmlspecialchars($patient['dietary_restrictions'] ?? '') : '' ?></textarea>
                    </div>
                    
                    <button type="submit" class="save-btn">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
<?php
session_start();
header('Content-Type: application/json');

require_once 'dbPC.php'; // Adjust the path if necessary

// --- SECURE YOUR API KEY ---
// IMPORTANT: Replace with your actual Gemini API Key
$GEMINI_API_KEY = 'AIzaSyAK3up8NGJn9PFAQHJDY1uYiyoKJlL5LAE';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Authentication failed. Please log in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_message = trim($_POST['message'] ?? '');

if (empty($user_message)) {
    echo json_encode(['error' => 'Message cannot be empty.']);
    exit;
}


// 1. Fetch user-specific data based on their role
$context_data = '';
try {
    if ($user_role === 'patient') {
        // Fetch patient's details from 'patients' table
        $patient_details_stmt = $conn->prepare("
            SELECT
                u.name AS user_name, u.email, u.phone, u.dob, u.gender, u.address,
                p.height_cm, p.weight_kg, p.blood_type, p.allergies, p.past_diseases,
                p.current_medications, p.smoking_status, p.alcohol_consumption,
                p.exercise_frequency, p.dietary_restrictions
            FROM users u
            JOIN patients p ON u.id = p.user_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $patient_details_stmt->execute([$user_id]);
        $patient_profile = $patient_details_stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch patient's most recent prescription
        $prescription_stmt = $conn->prepare("
            SELECT
                pr.prescribed_at, a.symptoms, u_doc.name AS doctor_name
            FROM prescriptions pr
            JOIN appointments a ON pr.appointment_id = a.id
            JOIN users u_doc ON a.doctor_id = u_doc.id
            WHERE a.patient_id = ?
            ORDER BY pr.prescribed_at DESC
            LIMIT 1
        ");
        $prescription_stmt->execute([$user_id]);
        $last_prescription = $prescription_stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch medicines from the last prescription if available
        $prescription_medicines = [];
        if ($last_prescription) {
            $meds_stmt = $conn->prepare("SELECT medicine_name, dosage, frequency, duration FROM prescription_medicines WHERE prescription_id = ?");
            $meds_stmt->execute([$last_prescription['prescription_id']]); // Assuming prescriptions table has prescription_id now, which it should from previous steps
            $prescription_medicines = $meds_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fetch recent food logs
        $food_log_stmt = $conn->prepare("
            SELECT fl.log_date, fl.amount_in_grams, fd.food_name, fd.calorie_per_g
            FROM food_logs fl
            JOIN food_datapg fd ON fl.food_id = fd.id
            WHERE fl.user_id = ?
            ORDER BY fl.log_date DESC, fl.id DESC
            LIMIT 3
        ");
        $food_log_stmt->execute([$user_id]);
        $recent_food_logs = $food_log_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch recent step logs
        $step_log_stmt = $conn->prepare("
            SELECT log_date, steps, goal
            FROM step_logs
            WHERE user_id = ?
            ORDER BY log_date DESC
            LIMIT 3
        ");
        $step_log_stmt->execute([$user_id]);
        $recent_step_logs = $step_log_stmt->fetchAll(PDO::FETCH_ASSOC);


        if ($patient_profile) {
            $context_data = "You are an AI assistant in a medical application. The current user is a patient. I will provide you with their health data from the database. Based on this data and the user's question, provide helpful and relevant suggestions or information. Do NOT mention that you cannot access a database; instead, phrase your answers based on the provided information as if you know it.\n\n";
            $context_data .= "--- Patient Profile ---\n";
            $context_data .= "Name: " . ($patient_profile['user_name'] ?? 'N/A') . "\n";
            $context_data .= "Gender: " . ($patient_profile['gender'] ?? 'N/A') . "\n";
            $context_data .= "Date of Birth: " . ($patient_profile['dob'] ?? 'N/A') . "\n";
            $context_data .= "Height: " . ($patient_profile['height_cm'] ?? 'N/A') . " cm\n";
            $context_data .= "Weight: " . ($patient_profile['weight_kg'] ?? 'N/A') . " kg\n";
            $context_data .= "Blood Type: " . ($patient_profile['blood_type'] ?? 'N/A') . "\n";
            $context_data .= "Allergies: " . ($patient_profile['allergies'] ?? 'None') . "\n";
            $context_data .= "Past Diseases: " . ($patient_profile['past_diseases'] ?? 'None') . "\n";
            $context_data .= "Current Medications: " . ($patient_profile['current_medications'] ?? 'None') . "\n";
            $context_data .= "Smoking Status: " . ($patient_profile['smoking_status'] ?? 'N/A') . "\n";
            $context_data .= "Alcohol Consumption: " . ($patient_profile['alcohol_consumption'] ?? 'N/A') . "\n";
            $context_data .= "Exercise Frequency: " . ($patient_profile['exercise_frequency'] ?? 'N/A') . "\n";
            $context_data .= "Dietary Restrictions: " . ($patient_profile['dietary_restrictions'] ?? 'None') . "\n";
            $context_data .= "----------------------\n\n";

            if ($last_prescription) {
                $context_data .= "--- Last Prescription ---\n";
                $context_data .= "Prescribed Date: " . ($last_prescription['prescribed_at'] ?? 'N/A') . "\n";
                $context_data .= "Doctor: Dr. " . ($last_prescription['doctor_name'] ?? 'N/A') . "\n";
                $context_data .= "Symptoms: " . ($last_prescription['symptoms'] ?? 'N/A') . "\n";
                if (!empty($prescription_medicines)) {
                    $context_data .= "Medicines:\n";
                    foreach ($prescription_medicines as $med) {
                        $context_data .= "  - " . ($med['medicine_name'] ?? 'N/A') . " (Dosage: " . ($med['dosage'] ?? 'N/A') . ", Freq: " . ($med['frequency'] ?? 'N/A') . ", Dur: " . ($med['duration'] ?? 'N/A') . ")\n";
                    }
                } else {
                    $context_data .= "Medicines: None listed for last prescription.\n";
                }
                $context_data .= "-------------------------\n\n";
            } else {
                $context_data .= "--- No Recent Prescription Data Available ---\n\n";
            }

            if (!empty($recent_food_logs)) {
                $context_data .= "--- Recent Food Logs ---\n";
                foreach ($recent_food_logs as $log) {
                    $context_data .= "- " . ($log['log_date'] ?? 'N/A') . ": " . ($log['amount_in_grams'] ?? 'N/A') . "g of " . ($log['food_name'] ?? 'N/A') . " (" . ($log['calorie_per_g'] * $log['amount_in_grams'] ?? 'N/A') . " calories)\n";
                }
                $context_data .= "------------------------\n\n";
            } else {
                $context_data .= "--- No Recent Food Log Data Available ---\n\n";
            }

            if (!empty($recent_step_logs)) {
                $context_data .= "--- Recent Step Logs ---\n";
                foreach ($recent_step_logs as $log) {
                    $context_data .= "- " . ($log['log_date'] ?? 'N/A') . ": Steps: " . ($log['steps'] ?? 'N/A') . ", Goal: " . ($log['goal'] ?? 'N/A') . "\n";
                }
                $context_data .= "------------------------\n\n";
            } else {
                $context_data .= "--- No Recent Step Log Data Available ---\n\n";
            }

        } else {
            $context_data = "You are an AI assistant for a medical app, helping a patient. No detailed patient profile data is available for this user. Only consider general medical advice based on the user's question.\n\n";
        }

    } elseif ($user_role === 'doctor') {
        // Fetch doctor's details
        $doctor_profile_stmt = $conn->prepare("
            SELECT
                u.name AS user_name, u.email, u.phone,
                d.specialization, d.qualification, d.experience_years, d.bio
            FROM users u
            LEFT JOIN doctors d ON u.id = d.user_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $doctor_profile_stmt->execute([$user_id]);
        $doctor_profile = $doctor_profile_stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch doctor's next appointment and patient symptoms
        $next_appointment_stmt = $conn->prepare("
            SELECT
                a.appointment_date, a.appointment_time, a.symptoms, u_pat.name AS patient_name,
                p.height_cm, p.weight_kg, p.blood_type, p.allergies, p.past_diseases,
                p.current_medications, p.smoking_status, p.alcohol_consumption,
                p.exercise_frequency, p.dietary_restrictions
            FROM appointments a
            JOIN users u_pat ON a.patient_id = u_pat.id
            LEFT JOIN patients p ON u_pat.id = p.user_id
            WHERE a.doctor_id = ? AND a.appointment_status = 'accepted' -- Only show accepted for upcoming
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
            LIMIT 1
        ");
        $next_appointment_stmt->execute([$user_id]);
        $next_appointment = $next_appointment_stmt->fetch(PDO::FETCH_ASSOC);

        if ($doctor_profile) {
            $context_data = "You are an AI assistant in a medical application, helping a doctor. I will provide you with their professional details and their next patient's information from the database. Based on this data and the doctor's question, provide helpful and relevant suggestions or information. Do NOT mention that you cannot access a database; instead, phrase your answers based on the provided information as if you know it.\n\n";
            $context_data .= "--- Doctor Profile ---\n";
            $context_data .= "Name: Dr. " . ($doctor_profile['user_name'] ?? 'N/A') . "\n";
            $context_data .= "Specialization: " . ($doctor_profile['specialization'] ?? 'N/A') . "\n";
            $context_data .= "Qualification: " . ($doctor_profile['qualification'] ?? 'N/A') . "\n";
            $context_data .= "Experience: " . ($doctor_profile['experience_years'] ?? 'N/A') . " years\n";
            $context_data .= "Bio: " . ($doctor_profile['bio'] ?? 'N/A') . "\n";
            $context_data .= "---------------------\n\n";

            if ($next_appointment) {
                $context_data .= "--- Next Appointment Details ---\n";
                $context_data .= "Patient: " . ($next_appointment['patient_name'] ?? 'N/A') . "\n";
                $context_data .= "Date: " . ($next_appointment['appointment_date'] ?? 'N/A') . "\n";
                $context_data .= "Time: " . ($next_appointment['appointment_time'] ?? 'N/A') . "\n";
                $context_data .= "Patient Symptoms: " . ($next_appointment['symptoms'] ?? 'N/A') . "\n";
                $context_data .= "Patient Height: " . ($next_appointment['height_cm'] ?? 'N/A') . " cm\n";
                $context_data .= "Patient Weight: " . ($next_appointment['weight_kg'] ?? 'N/A') . " kg\n";
                $context_data .= "Patient Blood Type: " . ($next_appointment['blood_type'] ?? 'N/A') . "\n";
                $context_data .= "Patient Allergies: " . ($next_appointment['allergies'] ?? 'None') . "\n";
                $context_data .= "Patient Past Diseases: " . ($next_appointment['past_diseases'] ?? 'None') . "\n";
                $context_data .= "Patient Current Medications: " . ($next_appointment['current_medications'] ?? 'None') . "\n";
                $context_data .= "Patient Smoking Status: " . ($next_appointment['smoking_status'] ?? 'N/A') . "\n";
                $context_data .= "Patient Alcohol Consumption: " . ($next_appointment['alcohol_consumption'] ?? 'N/A') . "\n";
                $context_data .= "Patient Exercise Frequency: " . ($next_appointment['exercise_frequency'] ?? 'N/A') . "\n";
                $context_data .= "Patient Dietary Restrictions: " . ($next_appointment['dietary_restrictions'] ?? 'None') . "\n";
                $context_data .= "------------------------------\n\n";
            } else {
                $context_data .= "--- No Upcoming Accepted Appointments --- \n\n";
            }
        } else {
            $context_data = "You are an AI assistant for a medical app, helping a doctor. No detailed doctor profile or next appointment data is available for this user. Only consider general medical advice based on the user's question.\n\n";
        }
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error during context fetching: ' . $e->getMessage()]);
    exit;
}

// 2. Construct the full prompt for Gemini
// Explicitly tell the AI it's *receiving* data, not accessing it directly.
$full_prompt = "You are an AI assistant for a medical web application. I am providing you with context about the current user (either a patient or a doctor) and their relevant data from the database. Your role is to answer questions and provide helpful, medically-related suggestions *based on the provided context*. Do NOT state that you cannot access a database; instead, synthesize information from the provided data as if you are knowledgeable about the user's history and current status. If information is not available in the provided context, state that you don't have enough specific information and provide general advice.\n\n" .
               "--- PROVIDED USER DATA CONTEXT ---\n" .
               $context_data .
               "--- END PROVIDED USER DATA CONTEXT ---\n\n" .
               "User's Question: " . $user_message;


// 3. Call the Gemini API using cURL
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=" . $GEMINI_API_KEY;
$payload = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => $full_prompt]
            ]
        ]
    ]
]);

// Check if the JSON payload was created successfully
if ($payload === false) {
    echo json_encode(['error' => 'JSON Encoding Error: ' . json_last_error_msg()]);
    exit;
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['error' => 'cURL Error: ' . $curl_error]);
    exit;
}

if ($http_code != 200) {
    $error_details = json_decode($response, true);
    $error_message = $error_details['error']['message'] ?? 'No specific error message provided by the API.';
    echo json_encode(['error' => "Gemini API call failed with HTTP code: $http_code. Details: " . $error_message]);
    exit;
}

$data = json_decode($response, true);
$gemini_response = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I couldn\'t generate a response.';

echo json_encode(['response' => $gemini_response]);
?>
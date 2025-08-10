<?php
session_start();
require_once 'dbPC.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usernameInput = $_POST['username'];
    $passwordInput = $_POST['password'];

    // Prepare query: check if input matches either username or email
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :input OR email = :input LIMIT 1");
    $stmt->execute([':input' => $usernameInput]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Verify password
        if (password_verify($passwordInput, $user['password'])) {
            // Login success: set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            // Role-based redirection
            switch ($user['role']) {
                case 'admin':
                    header("Location: Admin/admin.php");
                    break;
                case 'doctor':
                    header("Location: Doctor/doctor_home.php");
                    break;
                case 'patient':
                    header("Location: Patient/patient_home.php");
                    break;
                case 'nutritionist':
                    header("Location: Nutritionists/nutritionist.php");
                    break;
                case 'trainer':
                    header("Location: Trainer/trainer.php");
                    break;
                default:
                    echo "Unknown user role.";
                    exit();
            }
            exit();
        } else {
            echo "Incorrect password.";
        }
    } else {
        echo "User not found.";
    }
} else {
    echo "Invalid access.";
}
?>

<?php
require_once 'dbPC.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Fetch and sanitize input
    $name     = htmlspecialchars($_POST['name']);
    $email    = htmlspecialchars($_POST['email']);
    $phone    = htmlspecialchars($_POST['phone']);
    $dob      = $_POST['dob'];
    $gender   = $_POST['gender'];
    $address  = htmlspecialchars($_POST['address']);
    $role     = $_POST['role'];
    $username = htmlspecialchars($_POST['username']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    // Password match check
    if ($password !== $confirm) {
        die("Passwords do not match.");
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert into database
    try {
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, dob, gender, address, role, username, password) 
                                VALUES (:name, :email, :phone, :dob, :gender, :address, :role, :username, :password)");

        $stmt->execute([
            ':name'     => $name,
            ':email'    => $email,
            ':phone'    => $phone,
            ':dob'      => $dob,
            ':gender'   => $gender,
            ':address'  => $address,
            ':role'     => $role,
            ':username' => $username,
            ':password' => $hashedPassword
        ]);

        echo "Registration successful! You can now <a href='loginPC.html'>login</a>.";

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "Error: Email or Username already exists.";
        } else {
            echo "Database error: " . $e->getMessage();
        }
    }
} else {
    echo "Invalid access.";
}
?>

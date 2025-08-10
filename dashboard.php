<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: loginPC.html");
    exit();
}

echo "Welcome, " . htmlspecialchars($_SESSION['name']) . "!<br>";
echo "Role: " . htmlspecialchars($_SESSION['role']) . "<br>";
echo "<a href='logout.php'>Logout</a>";
?>

<?php
session_start();
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PersoCare - Logged Out</title>
  <meta http-equiv="refresh" content="3;url=loginPC.html">
  <style>
    body {
      margin: 0;
      padding: 0;
      background: linear-gradient(to right, #e0e7ff, #f0f4ff);
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      color: #333;
    }
    .card {
      background: white;
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
      text-align: center;
      max-width: 400px;
    }
    .card h1 {
      color: #4F46E5;
      margin-bottom: 10px;
    }
    .card p {
      font-size: 16px;
    }
    .redirect {
      margin-top: 20px;
      font-size: 14px;
      color: #666;
    }
    .loader {
      margin-top: 20px;
      border: 4px solid #e5e7eb;
      border-top: 4px solid #4F46E5;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      animation: spin 1s linear infinite;
      display: inline-block;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Logged Out</h1>
    <p>You have been successfully logged out.</p>
    <div class="loader"></div>
    <div class="redirect">
      Redirecting to login page...
    </div>
  </div>
</body>
</html>

<?php
// Replace with your actual API key
$GEMINI_API_KEY = 'AIzaSyCwgkQ10ECAhiJDoCT8LYxiV_rPGOQ80Lw'; 

$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

header('Content-Type: application/json');
echo $response;
?>
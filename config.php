<?php
// සෙෂන් එක දැනටමත් ආරම්භ කර නොමැති නම්, සෙෂන් එක ආරම්භ කිරීම
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ඩේටාබේස් සම්බන්ධතා විස්තර
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sg_food_city_db';

// ඩේටාබේස් එක සමඟ සම්බන්ධතාවය ඇති කිරීම
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// සම්බන්ධතාවයේ දෝෂ පරීක්ෂා කිරීම
if ($conn->connect_error) {
    die("සම්බන්ධතාවය අසාර්ථකයි: " . $conn->connect_error);
}

// යුනිකෝඩ් සිංහල අකුරු සඳහා සහය දැක්වීමට charset එක සැකසීම
$conn->set_charset("utf8mb4");
?>

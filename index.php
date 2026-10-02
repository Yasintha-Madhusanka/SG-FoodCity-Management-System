<?php
// සෙෂන් එක ආරම්භ කිරීම සහ ඩේටාබේස් සම්බන්ධතාවය ලබා ගැනීම
require_once 'config.php';



// පරිශීලකයා නික්ම යාම (Logout) සක්‍රීය කිරීම
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    // සෙෂන් විචල්‍යයන් මකා දැමීමෙන් පසු ලොගින් පිටුවට යොමු කිරීම
    header("Location: index.php");
    exit();
}

// ලොගින් ෆෝම් එක සබ්මිට් කළ පසු දත්ත සැකසීම
if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    $username = trim($_POST['username']);
    $role = trim($_POST['role']);
    $pass = trim($_POST['password']);

    // SQL Injection වළක්වා ගැනීම සඳහා Prepared Statements භාවිතය
    $sql = "SELECT * FROM employee WHERE employee_name=? AND role=?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        // ගැලපෙන පරිශීලකයෙක් සිටී නම් සෙෂන් දත්ත ගබඩා කිරීම
        if($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            
            // Checking if password matches plain-text password
            $password_matches = ($pass === $row['password']);

            if ($password_matches) {
                $_SESSION['username'] = $row['employee_name'];
                $_SESSION['pass'] = $row['password'];
                // Standardize role capitalization for dashboards session keys 
                $_SESSION['role'] = ucwords(strtolower($row['role'])); // e.g. "Admin", "Cashier", "Security"
                $_SESSION['employee_id'] = $row['employee_id'];

                // පරිශීලකයාගේ භූමිකාව අනුව අදාල ඩෑෂ්බෝඩ් එකට යොමු කිරීම
                if($_SESSION['role'] == 'Admin') {
                    header("Location: admin_dashbord.php");
                }
                elseif($_SESSION['role'] == 'Cashier') {
                    header("Location: cashier_dashbord.php");
                }
                elseif($_SESSION['role'] == 'Security') {
                    header("Location: security_dashbord.php");
                }
                exit();
            } else {
                // ඇතුළත් කළ තොරතුරු වැරදි නම් ඇඟවීමක් පෙන්වීම
                echo "<script>
                    alert('Username, Password හෝ Role එක වැරදියි!');
                    window.location='index.php';
                </script>";
            }
        } else {
            // ඇතුළත් කළ තොරතුරු වැරදි නම් ඇඟවීමක් පෙන්වීම
            echo "<script>
                alert('Username, Password හෝ Role එක වැරදියි!');
                window.location='index.php';
            </script>";
        }
        $stmt->close();
    } else {
        die("ඩේටාබේස් විමසුම් දෝෂයකි: " . $conn->error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | SuperGills Food City Management System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="bg-overlay"></div>
    
    <header class="main-header">
        <div class="header-left">
            <!-- Logo removed per user request -->
        </div>
        <div class="header-center">
            <h1>SuperGills Food City Management <br> System</h1>
        </div>
        <div class="header-right">
            <!-- Space for user status or help icon -->
        </div>
    </header>

    <main class="login-container">
        <div class="login-card">
            <div class="card-header">
                <h2>Welcome Back</h2>
                <p>Please enter your credentials to continue</p>
            </div>
            
            <form class="login-form" method="post">
                <div class="input-group">
                    <label for="name">name</label>
                    <div class="input-wrapper">
                        <input type="text" id="name" name="username" placeholder="Enter your username" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="role">Role</label>
                    <div class="input-wrapper">
                        <select id="role" name="role" required>
                            <option value=""> Select Job Role </option>
                            <option value="Admin">Admin</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Security">Security</option>
                        </select>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
        </div>
    </main>

    <footer class="main-footer">
        <p>&copy; 2026 Supergirls Food City. All rights reserved.<br>
            Monaragala Road, Buttala <br>
            0721258751 | suppergills@gmail.com <br></p>
    </footer>

    <script src="script.js"></script>
</body>
</html>

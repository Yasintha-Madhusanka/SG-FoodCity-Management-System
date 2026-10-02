<?php
// සෙෂන් සහ ඩේටාබේස් සම්බන්ධතාවය ඇතුළත් කිරීම
require_once 'config.php';

// පද්ධතියට ලොග් වී ඇත්ද සහ ආරක්ෂක නිලධාරී (Security) ද යන්න පරීක්ෂා කිරීම
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Security') {
    header("Location: index.php");
    exit();
}

// ආරක්ෂක ලොග් වගුව (security_logs) නොමැති නම් එය ස්වයංක්‍රීයව නිර්මාණය කිරීම
$conn->query("CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_no VARCHAR(100) NULL,
    driver_name VARCHAR(150) NULL,
    action_type VARCHAR(20) NULL,
    purpose TEXT NULL,
    status ENUM('safe', 'danger') NOT NULL DEFAULT 'safe',
    date_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// පණිවිඩ විචල්‍යය සකස් කිරීම
$message = '';
if (isset($_SESSION['msg'])) {
    $message = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// හදිසි ලොක්ඩවුන් (System Lockdown) API ඇමතුම සැකසීම
if (isset($_GET['api']) && $_GET['api'] === 'lockdown') {
    header('Content-Type: application/json');
    $vehicle_no = 'LOCKDOWN';
    $driver_name = 'SYSTEM ALARM';
    $action_type = 'LOCKDOWN';
    $purpose = 'Emergency lockdown triggered by security terminal.';
    
    $stmt = $conn->prepare("INSERT INTO security_logs (vehicle_no, driver_name, action_type, purpose, status) VALUES (?, ?, ?, ?, 'danger')");
    $stmt->bind_param("ssss", $vehicle_no, $driver_name, $action_type, $purpose);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true]);
    exit();
}

// ගේට්ටු පරීක්ෂණ වාර්තා ඇතුළත් කිරීම (Gate Log POST Submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type_post'])) {
    if ($_POST['action_type_post'] === 'gate_log') {
         $vehicle_no = trim($_POST['vehicle_no']);
        $driver_name = trim($_POST['driver_name']);
        $action_type = $_POST['action_type'];
        $purpose = trim($_POST['purpose']);
        
        if (empty($driver_name) || empty($action_type) || empty($purpose)) {
            $_SESSION['msg'] = "Error: රියදුරු නම, ක්‍රියාව සහ අරමුණ පිරවීම අනිවාර්ය වේ!";
            header("Location: security_dashbord.php");
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO security_logs (vehicle_no, driver_name, action_type, purpose, status) VALUES (?, ?, ?, ?, 'safe')");
        $stmt->bind_param("ssss", $vehicle_no, $driver_name, $action_type, $purpose);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['msg'] = "ගේට්ටු වාර්තාව සාර්ථකව ඇතුළත් කරන ලදී!";
        header("Location: security_dashbord.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard | SuperGills Food City</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="bg-overlay"></div>
    
    <!-- හදිසි අවස්ථා රතු ඇඟවීම් පුවරුව (Emergency Alarm Overlay Display) -->
    <div id="alarm-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:99999; pointer-events:none; background: radial-gradient(circle, rgba(255,0,0,0) 0%, rgba(0,0,0,0) 100%); transition: background 1s ease-in-out;"></div>

    <header class="main-header">
        <div class="header-left"></div>
        <div class="header-center">
            <h1>SuperGills Food City Management <br> System</h1>
        </div>
        <div class="header-right">
            <span class="status-badge status-danger" style="padding: 10px; font-weight: bold;">
                <i class="fas fa-shield-halved"></i> Security: <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
        </div>
    </header>

    <div class="dashboard-container">
        <aside class="sidebar">
            <a href="security_dashbord.php" class="nav-item active">
                <i class="fas fa-shield-halved"></i> Checkpoint Gate Log
            </a>
            <div style="margin-top: auto; border-top: 1px solid var(--glass-border); padding-top: 20px;">
                <a href="index.php?action=logout" class="nav-item" style="color: #ff4d4d;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <!-- පණිවිඩ ඇඟවීම් -->
            <?php if (!empty($message)): ?>
                <div class="glass-card" style="padding: 15px; margin-bottom: 5px; border-left: 5px solid var(--success-color); background: rgba(0, 242, 254, 0.1);" id="alert-banner">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></span>
                        <button onclick="document.getElementById('alert-banner').style.display='none'" style="background:none; border:none; color:#fff; font-size:1.1rem; cursor:pointer;">&times;</button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid-2">
                <!-- වම්පස: ගේට්ටු සටහන් කිරීමේ පෝරමය (Log Form) -->
                <div class="glass-card">
                    <h3 style="color: var(--primary-color); margin-bottom: 25px;"><i class="fas fa-truck-moving"></i> Vehicle Checkpoint Registry</h3>
                    <form action="security_dashbord.php" method="POST">
                        <input type="hidden" name="action_type_post" value="gate_log">
                        
                        <div class="input-group">
                            <label>Vehicle Registration Number</label>
                            <input type="text" name="vehicle_no" placeholder="E.g. WP WP-1234 or LK-9876" required>
                        </div>
                        
                        <div class="input-group">
                            <label>Driver / Supplier Name</label>
                            <input type="text" name="driver_name" placeholder="E.g. Kimal Perera" required>
                        </div>
                        
                        <div class="input-group">
                            <label>Action Type (IN/OUT)</label>
                            <select name="action_type" required>
                                <option value="IN">Gate Entry (IN)</option>
                                <option value="OUT">Gate Exit (OUT)</option>
                            </select>
                        </div>
                        
                        <div class="input-group">
                            <label>Purpose of Visit</label>
                            <textarea name="purpose" rows="3" placeholder="E.g. Goods Delivery, Garbage collection..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                            <i class="fas fa-check"></i> Register Gate Event
                        </button>
                    </form>

                    <!-- හදිසි ලොක්ඩවුන් බොත්තම (Emergency Lockdown Action) -->
                    <hr style="border:0; border-top: 1px solid var(--glass-border); margin: 30px 0;">
                    <div style="text-align: center;">
                        <h4 style="margin-bottom: 10px; color: var(--accent-color);"><i class="fas fa-warning"></i> EMERGENCY COMMANDS</h4>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom: 15px;">Triggering lockdown logs breach to ledger, closes all terminal checkouts immediately.</p>
                        <button class="btn btn-accent" style="width: 100%; height: 55px;" onclick="triggerSystemLockdown()"><i class="fas fa-biohazard"></i> SYSTEM LOCKDOWN</button>
                    </div>
                </div>

                <!-- දකුණුපස: සජීවී ගේට්ටු ලොග් සටහන් (Checkpoint Logs Activity) -->
                <div class="glass-card">
                    <h3 style="color: var(--success-color); margin-bottom: 20px;"><i class="fas fa-clock"></i> Recent Checkpoint Activity Logs</h3>
                    <div class="table-container" style="max-height: 580px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Vehicle / Details</th>
                                    <th>Driver</th>
                                    <th>Gate Action</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $logs = $conn->query("SELECT * FROM security_logs ORDER BY id DESC LIMIT 30");
                                if ($logs && $logs->num_rows > 0):
                                    while ($row = $logs->fetch_assoc()):
                                        $is_lockdown = ($row['action_type'] === 'LOCKDOWN' || $row['status'] === 'danger');
                                        $badge_class = $is_lockdown ? 'background: #ff1744; color: #fff;' : (($row['action_type'] === 'IN') ? 'background: rgba(0, 242, 254, 0.15); color: var(--success-color);' : 'background: rgba(255, 0, 128, 0.15); color: var(--accent-color);');
                                ?>
                                    <tr style="<?php echo $is_lockdown ? 'background: rgba(255, 0, 128, 0.08);' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['vehicle_no']); ?></strong><br>
                                            <small style="color: var(--text-muted);"><?php echo htmlspecialchars($row['purpose'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                                        <td>
                                            <span class="status-badge" style="<?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($row['action_type']); ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.8rem; white-space: nowrap;"><?php echo date('h:i A', strtotime($row['date_time'])); ?></td>
                                    </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No entries logged today.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <footer class="main-footer">
        <p>&copy; 2026 Supergirls Food City. All rights reserved.<br>
            Monaragala Road, Buttala <br>
            0721258751 | suppergills@gmail.com <br></p>
    </footer>

    <script src="script.js"></script>
</body>
</html>

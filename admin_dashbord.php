<?php
// සෙෂන් සහ ඩේටාබේස් සම්බන්ධතාවය සක්‍රීය කිරීම
require_once 'config.php';

// පද්ධතියට ලොග් වී ඇත්ද සහ පරිපාලකයෙක් (Admin) ද යන්න පරීක්ෂා කිරීම
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// සැපයුම්කරු වගුව (supplier) නොමැති නම් ස්වයංක්‍රීයව නිර්මාණය කිරීම
$conn->query("CREATE TABLE IF NOT EXISTS supplier (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    contact VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    address VARCHAR(255) NOT NULL,
    product VARCHAR(255) NOT NULL,
    brand VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// කාණ්ඩ වගුවට පෙරනිමි කාණ්ඩ ඇතුළත් කිරීම (වගුව හිස් නම් පමණි)
$conn->query("INSERT IGNORE INTO category (category_id, category_name) VALUES 
(1, 'Dairy & Coolers'), (2, 'Fresh Produce'), (3, 'Bakery'), (4, 'Beverages'), (5, 'Snacks')");

// පණිවිඩ විචල්‍යයන් සැකසීම
$message = '';
if (isset($_SESSION['msg'])) {
    $message = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// සේවක, භාණ්ඩ, සැපයුම්කරු සහ කාණ්ඩ දත්ත එකතු කිරීම, සංස්කරණය කිරීම (POST CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    
    // සේවක දත්ත සුරැකීම
    if ($_POST['action_type'] === 'save_employee') {
        $emp_id   = intval($_POST['employee_id']);
        $emp_name = trim($_POST['employee_name']);
        $role     = strtolower(trim($_POST['role'])); // DB stores lowercase: admin, cashier, security
        $contact  = trim($_POST['contact']);
        $password = trim($_POST['password']);
        $address  = trim($_POST['address']);
        $email    = trim($_POST['email']);
        
        // Server-side Validation)
         if (!ctype_alpha(str_replace('','',$emp_name))) {
            $_SESSION['msg'] = "Error: Use only letters for the name.";
            header("Location: admin_dashbord.php?tab=employees");
            exit();
        }
        if (empty($emp_name) || empty($role) || empty($contact) || empty($password) || empty($address) || empty($email)) {
            $_SESSION['msg'] = "Filling in all fields is mandatory!";
            header("Location: admin_dashbord.php?tab=employees");
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['msg'] = "Invalid email address!";
            header("Location: admin_dashbord.php?tab=employees");
            exit();
        }

        if (!preg_match("/^(?:\+94|0)?7[0-9]{8}$/", $contact) && !preg_match("/^[0-9]{10}$/", $contact)) {
            $_SESSION['msg'] = "Invalid phone number! (Eg: 0771234567)";
            header("Location: admin_dashbord.php?tab=employees");
            exit();
        }

        // Email uniqueness check
        $check_stmt = $conn->prepare("SELECT employee_id FROM employee WHERE email=? AND employee_id!=?");
        $check_stmt->bind_param("si", $email, $emp_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        if ($check_res->num_rows > 0) {
            $_SESSION['msg'] = "This email address is already in use for another employee!";
            header("Location: admin_dashbord.php?tab=employees");
            exit();
        }
        $check_stmt->close();

        if ($emp_id > 0) {
            // සේවක විස්තර යාවත්කාලීන කිරීම (Update) — table: employee
            $stmt = $conn->prepare("UPDATE employee SET employee_name=?, role=?, contact=?, password=?, address=?, email=? WHERE employee_id=?");
            $stmt->bind_param("ssssssi", $emp_name, $role, $contact, $password, $address, $email , $emp_id);
            $stmt->execute();
            $stmt->close();
            
            // භූමිකාව අනුව උප-වගු දත්ත යාවත්කාලීන කිරීම
            $conn->query("DELETE FROM admin WHERE employee_id=$emp_id");
            $conn->query("DELETE FROM cashier WHERE employee_id=$emp_id");
            $conn->query("DELETE FROM security WHERE employee_id=$emp_id");
            
            if ($role === 'admin') {
                $stmt = $conn->prepare("INSERT INTO admin (employee_id, password, email) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $emp_id, $password, $email);
            } elseif ($role === 'cashier') {
                $stmt = $conn->prepare("INSERT INTO cashier (employee_id, contact, address, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $emp_id, $contact, $address, $password);
            } elseif ($role === 'security') {
                $stmt = $conn->prepare("INSERT INTO security (employee_id, contact, address, email) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $emp_id, $contact, $address, $email);
            }
            if (isset($stmt)) {
                $stmt->execute();
                $stmt->close();
            }
            $_SESSION['msg'] = "Employee details successfully updated!";
        } else {
            // නව සේවකයෙකු ඇතුළත් කිරීම (Insert) — table: employee
            $code_res = $conn->query("SELECT employee_code FROM employee WHERE employee_code REGEXP '^EMP[0-9]+$' ORDER BY CAST(SUBSTRING(employee_code, 4) AS UNSIGNED) DESC LIMIT 1");
            $next_num = 1;
            if ($code_res && $code_res->num_rows > 0) {
                $code_row = $code_res->fetch_assoc();
                $last_code = $code_row['employee_code'];
                $last_num = intval(substr($last_code, 3));
                $next_num = $last_num + 1;
            }
            $emp_code = 'EMP' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("INSERT INTO employee (employee_code, employee_name, role, contact, password, address, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $emp_code, $emp_name, $role, $contact, $password, $address, $email);
            $stmt->execute();
            $new_emp_id = $conn->insert_id;
            $stmt->close();
            
            // භූමිකාව අනුව උප-වගුවට දත්ත ඇතුළත් කිරීම
            if ($role === 'admin') {
                $stmt = $conn->prepare("INSERT INTO admin (employee_id, password, email) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $new_emp_id, $password, $email);
            } elseif ($role === 'cashier') {
                $stmt = $conn->prepare("INSERT INTO cashier (employee_id, contact, address, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $new_emp_id, $contact, $address, $password);
            } elseif ($role === 'security') {
                $stmt = $conn->prepare("INSERT INTO security (employee_id, contact, address, email) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $new_emp_id, $contact, $address, $email);
            }
            if (isset($stmt)) {
                $stmt->execute();
                $stmt->close();
            }
            $_SESSION['msg'] = "The new employee has been successfully onboarded!";
        }
        header("Location: admin_dashbord.php?tab=employees");
        exit();
    }
    
    // භාණ්ඩ සහ තොග දත්ත සුරැකීම
    elseif ($_POST['action_type'] === 'save_product') {
        $p_id = intval($_POST['product_id']);
        $p_name = trim($_POST['product_name']);
        $price = floatval($_POST['price']);
        $cat_id = intval($_POST['category_id']);
        $qty = intval($_POST['quantity']);

        // validation
        if (empty($p_name) || $price < 0 || $qty < 0 || $cat_id <= 0) {
            $_SESSION['msg'] = " Invalid item data! Price and quantity must be positive values.";
            header("Location: admin_dashbord.php?tab=products");
            exit();
        }
        
        if ($p_id > 0) {
            // භාණ්ඩ විස්තර යාවත්කාලීන කිරීම (Update)
            $stmt = $conn->prepare("UPDATE product SET product_name=?, price=?, quantity=?, category_id=? WHERE product_id=?");
            $stmt->bind_param("sdiii", $p_name, $price, $qty, $cat_id, $p_id);
            $stmt->execute();
            $stmt->close();
            
            // update inventory status and quantity to reflect new stock level
            $inv_status = ($qty <= 0) ? 'out-of-stock' : (($qty < 5) ? 'low-stock' : 'in-stock');
            $stmt = $conn->prepare("UPDATE inventory SET status=?, quantity=? WHERE product_id=?");
            $stmt->bind_param("sii", $inv_status, $qty, $p_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['msg'] = "Product details successfully updated!";
        } else {
            // නව භාණ්ඩයක් ඇතුළත් කිරීම (Insert)
            $sku_res = $conn->query("SELECT sku FROM product WHERE sku REGEXP '^SFC-[0-9]+$' ORDER BY CAST(SUBSTRING(sku, 5) AS UNSIGNED) DESC LIMIT 1");
            $next_num = 10;
            if ($sku_res && $sku_res->num_rows > 0) {
                $sku_row = $sku_res->fetch_assoc();
                $last_sku = $sku_row['sku'];
                $last_num = intval(substr($last_sku, 4));
                $next_num = $last_num + 1;
            }
            $sku = 'SFC-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("INSERT INTO product (sku, product_name, price, quantity, category_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdii", $sku, $p_name, $price, $qty, $cat_id);
            $stmt->execute();
            $new_p_id = $conn->insert_id;
            $stmt->close();
            
            // update inventory status and quantity
            $inv_status = ($qty <= 0) ? 'out-of-stock' : (($qty < 5) ? 'low-stock' : 'in-stock');
            $stmt = $conn->prepare("INSERT INTO inventory (product_id, quantity, status, stock_out) VALUES (?, ?, ?, 0)");
            $stmt->bind_param("iis", $new_p_id, $qty, $inv_status);
            $stmt->execute();
            $stmt->close();
            $_SESSION['msg'] = "The new item was successfully added!";
        }
        header("Location: admin_dashbord.php?tab=products");
        exit();
    }

    // නව කාණ්ඩයක් ඇතුළත් කිරීම (Category)
    elseif ($_POST['action_type'] === 'save_category') {
        $cat_name = trim($_POST['category_name']);
        if (!empty($cat_name)) {
            // duplicate name check
            $check = $conn->prepare("SELECT category_id FROM category WHERE category_name=?");
            $check->bind_param("s", $cat_name);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                $stmt = $conn->prepare("INSERT INTO category (category_name) VALUES (?)");
                $stmt->bind_param("s", $cat_name);
                $stmt->execute();
                $stmt->close();
                $_SESSION['msg'] = "The new category was successfully added!";
            } else {
                $_SESSION['msg'] = "This category already exists!";
            }
            $check->close();
        } else {
            $_SESSION['msg'] = "The category cannot be empty!";
        }
        header("Location: admin_dashbord.php?tab=products");
        exit();
    }

    // Supplier Save & Update
    elseif ($_POST['action_type'] === 'save_supplier') {
        $supplier_id    = intval($_POST['supplier_id']);
        $supplier_name  = trim($_POST['supplier_name']);
        $contact        = trim($_POST['contact']);
        $email          = trim($_POST['email']);
        $address        = trim($_POST['address']);
        $product        = trim($_POST['product']);
        $brand          = trim($_POST['brand']);

        // validation
        if (!ctype_alpha(str_replace('','',$supplier_name))) {
            $_SESSION['msg'] = "Error: Use only letters for the name.";
            header("Location: admin_dashbord.php?tab=suppluyer");
            exit();
        }

        if (empty($supplier_name) || empty($contact) || empty($email) || empty($address) || empty($product) || empty($brand)) {
            $_SESSION['msg'] = "Error: සියලුම සැපයුම්කරු විස්තර සම්පූර්ණ කළ යුතුය!";
            header("Location: admin_dashbord.php?tab=suppluyer");
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['msg'] = "Error: වලංගු නොවන ඊමේල් ලිපිනයකි!";
            header("Location: admin_dashbord.php?tab=suppluyer");
            exit();
        }

        if (!preg_match("/^(?:\+94|0)?7[0-9]{8}$/", $contact) && !preg_match("/^[0-9]{10}$/", $contact)) {
            $_SESSION['msg'] = "Error: වලංගු නොවන දුරකථන අංකයකි! (Eg: 0771234567)";
            header("Location: admin_dashbord.php?tab=suppluyer");
            exit();
        }

        // duplicate email check 
        $check_stmt = $conn->prepare("SELECT supplier_id FROM supplier WHERE email=? AND supplier_id!=?");
        $check_stmt->bind_param("si", $email, $supplier_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        if ($check_res->num_rows > 0) {
            $_SESSION['msg'] = "Error: මෙම ඊමේල් ලිපිනය දැනටමත් වෙනත් සැපයුම්කරුවෙකු සඳහා භාවිතා කර ඇත!";
            header("Location: admin_dashbord.php?tab=suppluyer");
            exit();
        }
        $check_stmt->close();

        if ($supplier_id > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE supplier SET supplier_name=?, contact=?, email=?, address=?, product=?, brand=? WHERE supplier_id=?");
            $stmt->bind_param("ssssssi", $supplier_name, $contact, $email, $address, $product, $brand, $supplier_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['msg'] = "Supplier details successfully updated!";
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO supplier (supplier_name, contact, email, address, product, brand) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $supplier_name, $contact, $email, $address, $product, $brand);
            $stmt->execute();
            $stmt->close();
            $_SESSION['msg'] = "New supplier successfully added!";
        }
        header("Location: admin_dashbord.php?tab=suppluyer");
        exit();
    }
}

// sewaka , bhanda , sapayumkaru data (DELETE)
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['delete'] === 'employee') {
        $conn->query("DELETE FROM admin WHERE employee_id=$id");
        $conn->query("DELETE FROM cashier WHERE employee_id=$id");
        $conn->query("DELETE FROM security WHERE employee_id=$id");
        $conn->query("DELETE FROM employee WHERE employee_id=$id");
        $_SESSION['msg'] = "The employee was successfully deleted!";
        header("Location: admin_dashbord.php?tab=employees");
        exit();
    } elseif ($_GET['delete'] === 'product') {
        $conn->query("DELETE FROM inventory WHERE product_id=$id");
        $conn->query("DELETE FROM product WHERE product_id=$id");
        $_SESSION['msg'] = "The item was successfully deleted!";
        header("Location: admin_dashbord.php?tab=products");
        exit();
    } elseif ($_GET['delete'] === 'supplier') {
        $conn->query("DELETE FROM supplier WHERE supplier_id=$id");
        $_SESSION['msg'] = "Supplier successfully deleted!";
        header("Location: admin_dashbord.php?tab=suppluyer");
        exit();
    }
}

// දත්ත සමුදායෙන් දත්ත ලබා ගැනීම (Metrics & Statistics Calculations)
$total_sales_res = $conn->query("SELECT SUM(total_amount) AS total FROM bill");
$total_sales = $total_sales_res ? floatval($total_sales_res->fetch_assoc()['total']) : 0.00;

$total_products_res = $conn->query("SELECT COUNT(*) AS total FROM product");
$total_products = $total_products_res ? intval($total_products_res->fetch_assoc()['total']) : 0;

$total_employees_res = $conn->query("SELECT COUNT(*) AS total FROM employee");
$total_employees = $total_employees_res ? intval($total_employees_res->fetch_assoc()['total']) : 0;

// සංස්කරණය සඳහා සෙවීම (Edit Prefill Fetch)
$edit_emp = null;
if (isset($_GET['edit']) && $_GET['edit'] === 'employee' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM employee WHERE employee_id=$id");
    if ($res && $res->num_rows > 0) {
        $edit_emp = $res->fetch_assoc();
    }
}

$edit_prod = null;
if (isset($_GET['edit']) && $_GET['edit'] === 'product' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT p.* FROM product p WHERE p.product_id=$id");
    if ($res && $res->num_rows > 0) {
        $edit_prod = $res->fetch_assoc();
    }
}

$edit_supplier = null;
if (isset($_GET['edit']) && $_GET['edit'] === 'supplier' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM supplier WHERE supplier_id=$id");
    if ($res && $res->num_rows > 0) {
        $edit_supplier = $res->fetch_assoc();
    }
}

// වර්තමාන ටැබ් එක තෝරා ගැනීම
$active_tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SuperGills Food City</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="bg-overlay"></div>
    
    <header class="main-header">
        <div class="header-left"></div>
        <div class="header-center">
            <h1>SuperGills Food City Management <br> System</h1>
        </div>
        <div class="header-right">
            <span class="status-badge status-safe" style="padding: 10px; font-weight: bold;">
                <i class="fas fa-user-shield"></i> Admin: <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
        </div>
    </header>

    <div class="dashboard-container">
        <aside class="sidebar">
            <a href="admin_dashbord.php?tab=dashboard" class="nav-item <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Dashboard Metrics
            </a>
            <a href="admin_dashbord.php?tab=employees" class="nav-item <?php echo $active_tab === 'employees' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> Manage Employees
            </a>
            <a href="admin_dashbord.php?tab=products" class="nav-item <?php echo $active_tab === 'products' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i> Product Inventory
            </a>
            <a href="admin_dashbord.php?tab=sales" class="nav-item <?php echo $active_tab === 'sales' ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i> View Sales Ledger
            </a>
            <a href="admin_dashbord.php?tab=security" class="nav-item <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-shield-halved"></i> Gate Security Logs
            </a>
            <a href="admin_dashbord.php?tab=suppluyer" class="nav-item <?php echo $active_tab === 'suppluyer' ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i> Suppliers
            </a>
            <div style="margin-top: auto; border-top: 1px solid var(--glass-border); padding-top: 20px;">
                <a href="index.php?action=logout" class="nav-item" style="color: #ff4d4d;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <!-- සාර්ථක හෝ අනතුරු ඇඟවීමේ පණිවිඩ පෙන්වීම -->
            <?php if (!empty($message)): ?>
                <div class="glass-card" style="padding: 15px; margin-bottom: 5px; border-left: 5px solid var(--success-color); background: rgba(0, 242, 254, 0.1); display: flex; justify-content: space-between; align-items: center;" id="alert-banner">
                    <span><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></span>
                    <button onclick="document.getElementById('alert-banner').style.display='none'" style="background:none; border:none; color:#fff; font-size:1.1rem; cursor:pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- DASHBOARD OVERVIEW TAB -->
            <?php if ($active_tab === 'dashboard'): ?>
                <section id="dashboard" class="view-section active">
                    <div class="grid-3" style="margin-bottom: 25px;">
                        <div class="glass-card stat-card">
                            <div class="stat-icon"><i class="fas fa-hand-holding-dollar"></i></div>
                            <div class="stat-info">
                                <h3>Total Gross Sales</h3>
                                <p>Rs. <?php echo number_format($total_sales, 2); ?></p>
                            </div>
                        </div>
                        <div class="glass-card stat-card">
                            <div class="stat-icon"><i class="fas fa-pizza-slice"></i></div>
                            <div class="stat-info">
                                <h3>Total Products</h3>
                                <p><?php echo $total_products; ?></p>
                            </div>
                        </div>
                        <div class="glass-card stat-card">
                            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                            <div class="stat-info">
                                <h3>Total Staff</h3>
                                <p><?php echo $total_employees; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- අඩු තොග පවතින භාණ්ඩ පිළිබඳ අනතුරු ඇඟවීම් (Low Stock Alerts < 5 units) -->
                    <div class="glass-card" style="border-left: 4px solid var(--accent-color);">
                        <h3 style="color: var(--accent-color); margin-bottom: 15px;"><i class="fas fa-triangle-exclamation"></i> Critical Low Stock Alerts (Less than 5 Units)</h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Selling Price</th>
                                        <th>Current Stock</th>
                                        <th>Status Warning</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // quantity is stored on the product table; inventory.status flags it
                                    $low_stock_res = $conn->query("SELECT p.* FROM product p WHERE p.quantity < 5 ORDER BY p.quantity ASC");
                                    if ($low_stock_res && $low_stock_res->num_rows > 0):
                                        while ($low_item = $low_stock_res->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><code>PRD-<?php echo $low_item['product_id']; ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($low_item['product_name']); ?></strong></td>
                                            <td>Rs. <?php echo number_format($low_item['price'], 2); ?></td>
                                            <td style="color:#ff3333; font-weight: bold;"><?php echo $low_item['quantity']; ?></td>
                                            <td><span class="status-badge status-danger" style="animation: pulse 1.5s infinite;">CRITICAL LOW</span></td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted);"><i class="fas fa-shield"></i> All products are well stocked above critical levels.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- EMPLOYEES CRUD TAB -->
            <?php if ($active_tab === 'employees'): ?>
                <section id="employees" class="view-section active">
                    <div class="grid-2">
                        <!-- සේවා නියුක්ත කිරීමේ හෝ සංස්කරණය කිරීමේ පෝරමය -->
                        <div class="glass-card">
                            <h3 style="color: var(--primary-color); margin-bottom: 20px;">
                                <?php echo $edit_emp ? 'Edit Staff Credentials' : 'Add Staff Member'; ?>
                            </h3>
                            <form action="admin_dashbord.php?tab=employees" method="POST">
                                <input type="hidden" name="action_type" value="save_employee">
                                <input type="hidden" name="employee_id" value="<?php echo $edit_emp ? $edit_emp['employee_id'] : '0'; ?>">
                                
                                <div class="input-group">
                                    <label>Full Name</label>
                                    <input type="text" name="employee_name" value="<?php echo $edit_emp ? htmlspecialchars($edit_emp['employee_name']) : ''; ?>" placeholder="E.g. Amal Silva" required>
                                </div>
                                <div class="input-group">
                                    <label>Role</label>
                                    <select name="role" required>
                                        <option value="" disabled <?php echo !$edit_emp ? 'selected' : ''; ?>>Select System Role</option>
                                        <option value="Admin" <?php echo ($edit_emp && strtolower($edit_emp['role']) == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                        <option value="Cashier" <?php echo ($edit_emp && strtolower($edit_emp['role']) == 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                                        <option value="Security" <?php echo ($edit_emp && strtolower($edit_emp['role']) == 'security') ? 'selected' : ''; ?>>Security</option>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label>Phone Number</label>
                                    <input type="text" name="contact" value="<?php echo $edit_emp ? htmlspecialchars($edit_emp['contact']) : ''; ?>" placeholder="E.g. 0712345678" required>
                                </div>
                                <div class="input-group">
                                    <label>Email</label>
                                    <input type="email" name="email" value="<?php echo $edit_emp ? htmlspecialchars($edit_emp['email']) : ''; ?>" placeholder="E.g. nimal@gmail.com" required>
                                </div>
                                <div class="input-group">
                                    <label>Address</label>
                                    <input type="text" name="address" value="<?php echo $edit_emp ? htmlspecialchars($edit_emp['address']) : ''; ?>" placeholder="E.g. Buttala Road, Monaragala" required>
                                </div>
                                <div class="input-group">
                                    <label>Login Password</label>
                                    <input type="password" name="password" value="<?php echo $edit_emp ? htmlspecialchars($edit_emp['password']) : ''; ?>" placeholder="Enter password for dashboard" required>
                                </div>
                                
                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Save Record</button>
                                    <?php if ($edit_emp): ?>
                                        <a href="admin_dashbord.php?tab=employees" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <!-- සේවක ලේඛනය (Table) -->
                        <div class="glass-card">
                            <h3 style="color: var(--success-color); margin-bottom: 20px;">Staff Registry</h3>
                            <div class="table-container" style="max-height: 480px; overflow-y: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Contact</th>
                                            <th>Email</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $emp_list = $conn->query("SELECT * FROM employee ORDER BY employee_id DESC");
                                        while ($emp = $emp_list->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($emp['employee_name']); ?></strong></td>
                                                <td><span class="status-badge" style="background: rgba(0, 210, 255, 0.15); color: var(--primary-color);"><?php echo htmlspecialchars($emp['role']); ?></span></td>
                                                <td><?php echo htmlspecialchars($emp['contact']); ?></td>
                                                <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 10px;">
                                                        <a href="admin_dashbord.php?tab=employees&edit=employee&id=<?php echo $emp['employee_id']; ?>" style="color: var(--primary-color);"><i class="fas fa-edit"></i></a>
                                                        <a href="admin_dashbord.php?tab=employees&delete=employee&id=<?php echo $emp['employee_id']; ?>" onclick="return confirm('මෙම සේවකයා මකා දැමීමට අවශ්‍යද?')" style="color: var(--accent-color);"><i class="fas fa-trash-alt"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- suppluyer -->
            <!-- suppluyer -->
             <?php if ($active_tab === 'suppluyer'): ?>
                <section id="suppluyer" class="view-section active">
                    <div class="grid-2">
                        <!-- සැපයුම්කරු එකතු කිරීමේ හෝ සංස්කරණය කිරීමේ පෝරමය -->
                        <div class="glass-card">
                            <h3 style="color: var(--primary-color); margin-bottom: 20px;">
                                <?php echo $edit_supplier ? 'Edit Supplier Details' : 'Add Supplier'; ?>
                            </h3>
                            <form action="admin_dashbord.php?tab=suppluyer" method="POST">
                                <input type="hidden" name="action_type" value="save_supplier">
                                <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier ? $edit_supplier['supplier_id'] : '0'; ?>">
                                
                                <div class="input-group">
                                    <label>Full Name</label>
                                    <input type="text" name="supplier_name" value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['supplier_name']) : ''; ?>" placeholder="E.g. Amal Silva" required>
                                </div>
                                <div class="input-group">
                                    <label>Phone Number</label>
                                    <input type="text" name="contact" value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['contact']) : ''; ?>" placeholder="E.g. 0712345678" required>
                                </div>
                                <div class="input-group">
                                    <label>Email</label>
                                    <input type="email" name="email" value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['email']) : ''; ?>" placeholder="E.g. nimal@gmail.com" required>
                                </div>
                                <div class="input-group">
                                    <label>Address</label>
                                    <input type="text" name="address" value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['address']) : ''; ?>" placeholder="E.g. Buttala Road, Monaragala" required>
                                </div>
                                <div class="input-group">
                                    <label>Product Supplied</label>
                                    <input type="text" name="product" value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['product']) : ''; ?>" placeholder="E.g. Fresh Milk, Biscuits" required>
                                </div>
                                <div class="input-group">
                                    <label>Company Brand</label>
                                    <input type="text" name="brand" value="<?php echo $edit_supplier ? htmlspecialchars($edit_supplier['brand']) : ''; ?>" placeholder="E.g. Pelwatte, Munchee" required>
                                </div>
                                
                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Save Supplier</button>
                                    <?php if ($edit_supplier): ?>
                                        <a href="admin_dashbord.php?tab=suppluyer" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        
                        <!-- සැපයුම්කරුවන්ගේ ලේඛනය (Table) -->
                        <div class="glass-card">
                            <h3 style="color: var(--success-color); margin-bottom: 20px;">Supplier Registry</h3>
                            <div class="table-container" style="max-height: 480px; overflow-y: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact</th>
                                            <th>Product / Brand</th>
                                            <th>Email</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sup_list = $conn->query("SELECT * FROM supplier ORDER BY supplier_id DESC");
                                        if ($sup_list && $sup_list->num_rows > 0):
                                            while ($sup = $sup_list->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($sup['supplier_name']); ?></strong><br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($sup['address']); ?></small></td>
                                                <td><?php echo htmlspecialchars($sup['contact']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($sup['product']); ?></strong><br><small style="color: var(--primary-color);"><?php echo htmlspecialchars($sup['brand']); ?></small></td>
                                                <td><?php echo htmlspecialchars($sup['email']); ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 10px;">
                                                        <a href="admin_dashbord.php?tab=suppluyer&edit=supplier&id=<?php echo $sup['supplier_id']; ?>" style="color: var(--primary-color);"><i class="fas fa-edit"></i></a>
                                                        <a href="admin_dashbord.php?tab=suppluyer&delete=supplier&id=<?php echo $sup['supplier_id']; ?>" onclick="return confirm('මෙම සැපයුම්කරු මකා දැමීමට අවශ්‍යද?')" style="color: var(--accent-color);"><i class="fas fa-trash-alt"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php
                                            endwhile;
                                        else:
                                        ?>
                                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No suppliers registered yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- PRODUCTS CRUD TAB -->
            <?php if ($active_tab === 'products'): ?>
                <section id="products" class="view-section active">
                    <div class="grid-2">
                        <!-- භාණ්ඩයක් ඇතුළත් කිරීම්/සංස්කරණ පෝරමය -->
                        <div class="glass-card">
                            <h3 style="color: var(--primary-color); margin-bottom: 20px;">
                                <?php echo $edit_prod ? 'Edit Product Parameters' : 'Add Inventory Product'; ?>
                            </h3>
                            <form action="admin_dashbord.php?tab=products" method="POST">
                                <input type="hidden" name="action_type" value="save_product">
                                <input type="hidden" name="product_id" value="<?php echo $edit_prod ? $edit_prod['product_id'] : '0'; ?>">
                                
                                <div class="input-group">
                                    <label>Product Name</label>
                                    <input type="text" name="product_name" value="<?php echo $edit_prod ? htmlspecialchars($edit_prod['product_name']) : ''; ?>" placeholder="E.g. Munchee Cream Crackers" required>
                                </div>
                                <div class="input-group">
                                    <label>Category</label>
                                    <select name="category_id" required>
                                        <option value="" disabled <?php echo !$edit_prod ? 'selected' : ''; ?>>Select Category</option>
                                        <?php
                                        $cat_list = $conn->query("SELECT * FROM category ORDER BY category_name ASC");
                                        while ($cat = $cat_list->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $cat['category_id']; ?>" <?php echo ($edit_prod && $edit_prod['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label>Selling Price (Rs.)</label>
                                    <input type="number" step="0.01" name="price" value="<?php echo $edit_prod ? htmlspecialchars($edit_prod['price']) : ''; ?>" placeholder="E.g. 150.00" required>
                                </div>
                                <div class="input-group">
                                    <label>Quantity / Stock Balance</label>
                                    <input type="number" name="quantity" value="<?php echo $edit_prod ? htmlspecialchars($edit_prod['quantity']) : ''; ?>" placeholder="E.g. 50" required>
                                </div>
                                
                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Save Product</button>
                                    <?php if ($edit_prod): ?>
                                        <a href="admin_dashbord.php?tab=products" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                            
                            <!-- නව කාණ්ඩයක් එකතු කිරීමේ පෝරමය -->
                            <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 25px 0;">
                            <h3 style="color: var(--primary-color); margin-bottom: 20px;">Create New Category</h3>
                            <form action="admin_dashbord.php?tab=products" method="POST">
                                <input type="hidden" name="action_type" value="save_category">
                                <div class="input-group">
                                    <label>Category Name</label>
                                    <input type="text" name="category_name" placeholder="E.g. Grains, Frozen Food" required>
                                </div>
                                <button type="submit" class="btn btn-secondary" style="width: 100%;"><i class="fas fa-folder-plus"></i> Add Category</button>
                            </form>
                        </div>

                        <!-- භාණ්ඩ ලැයිස්තුව (Catalog Ledger) -->
                        <div class="glass-card">
                            <h3 style="color: var(--success-color); margin-bottom: 20px;">Product Ledger</h3>
                            <div class="table-container" style="max-height: 520px; overflow-y: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // quantity lives on product table; join category for display name
                        $prd_list = $conn->query("SELECT p.*, c.category_name FROM product p LEFT JOIN category c ON p.category_id=c.category_id ORDER BY p.product_id DESC");
                                        while ($prod = $prd_list->fetch_assoc()):
                                            $is_low = ($prod['quantity'] < 5);
                                        ?>
                                            <tr style="<?php echo $is_low ? 'background: rgba(255, 0, 128, 0.05);' : ''; ?>">
                                                <td><code>PRD-<?php echo $prod['product_id']; ?></code></td>
                                                <td><strong><?php echo htmlspecialchars($prod['product_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($prod['category_name'] ?? 'General'); ?></td>
                                                <td>Rs. <?php echo number_format($prod['price'], 2); ?></td>
                                                <td style="<?php echo $is_low ? 'color:#ff3333; font-weight:bold;' : ''; ?>"><?php echo intval($prod['quantity']); ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 10px;">
                                                        <a href="admin_dashbord.php?tab=products&edit=product&id=<?php echo $prod['product_id']; ?>" style="color: var(--primary-color);"><i class="fas fa-edit"></i></a>
                                                        <a href="admin_dashbord.php?tab=products&delete=product&id=<?php echo $prod['product_id']; ?>" onclick="return confirm('මෙම භාණ්ඩය මකා දැමීමට අවශ්‍යද?')" style="color: var(--accent-color);"><i class="fas fa-trash-alt"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- SALES LEDGER TAB -->
            <?php if ($active_tab === 'sales'): ?>
                <section id="sales" class="view-section active">
                    <div class="glass-card">
                        <h3 style="color: var(--primary-color); margin-bottom: 20px;"><i class="fas fa-file-invoice-dollar"></i> Global Sales Transaction Ledger</h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Bill ID</th>
                                        <th>Bill Code</th>
                                        <th>Total Amount (Rs.)</th>
                                        <th>Customer ID</th>
                                        <th>Cashier ID</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $bill_list = $conn->query("SELECT * FROM bill ORDER BY bill_id DESC");
                                    if ($bill_list && $bill_list->num_rows > 0):
                                        while ($b_row = $bill_list->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><code>BLL-<?php echo $b_row['bill_id']; ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($b_row['bill_code']); ?></strong></td>
                                            <td>Rs. <?php echo number_format($b_row['total_amount'], 2); ?></td>
                                            <td><code><?php echo $b_row['customer_id'] ? 'CUST-'.$b_row['customer_id'] : 'Walk-in'; ?></code></td>
                                            <td><code>CSH-<?php echo $b_row['cashier_id'] ? $b_row['cashier_id'] : 'System'; ?></code></td>
                                            <td><?php echo $b_row['bill_date']; ?></td>
                                        </tr>
                                    <?php
                                        endwhile;
                                    else:
                                    ?>
                                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No checkout sales recorded.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- SECURITY LOGS TAB -->
            <?php if ($active_tab === 'security'): ?>
                <section id="security" class="view-section active">
                    <div class="glass-card">
                        <h3 style="color: var(--primary-color); margin-bottom: 20px;">
                            <i class="fas fa-shield-halved"></i> Live Physical Security Gate Entry Logs
                        </h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Log ID</th>
                                        <th>Vehicle Registration No.</th>
                                        <th>Driver/Supplier Name</th>
                                        <th>Gate Action</th>
                                        <th>Purpose of Visit</th>
                                        <th>Registry Date & Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Check if the security_logs table exists and fetch elements
                                    $logs_exist = $conn->query("SHOW TABLES LIKE 'security_logs'")->num_rows > 0;
                                    if ($logs_exist) {
                                        $logs_list = $conn->query("SELECT * FROM security_logs ORDER BY id DESC LIMIT 50");
                                        if ($logs_list && $logs_list->num_rows > 0) {
                                            while ($log = $logs_list->fetch_assoc()) {
                                                // check if vehicle properties exist in column format
                                                $vehicle_no = htmlspecialchars($log['vehicle_no'] ?? '-');
                                                $driver = htmlspecialchars($log['driver_name'] ?? '-');
                                                $action = htmlspecialchars($log['action_type'] ?? '-');
                                                $purpose = htmlspecialchars($log['purpose'] ?? ($log['action_details'] ?? ''));
                                                $badge_style = ($action === 'IN') ? 'background: rgba(0, 242, 254, 0.15); color: var(--success-color);' : 'background: rgba(255, 0, 128, 0.15); color: var(--accent-color);';
                                                
                                                if (strpos($log['vehicle_no'] ?? '', 'LOCKDOWN') !== false || strpos($log['action_details'] ?? '', 'LOCKDOWN') !== false) {
                                                    $badge_style = 'background: #ff1744; color: #fff; animation: pulse 1s infinite;';
                                                    $action = 'LOCKDOWN';
                                                }
                                                ?>
                                                <tr>
                                                    <td><code>LOG-<?php echo $log['id'] ?? $log['log_id']; ?></code></td>
                                                    <td><strong><?php echo $vehicle_no; ?></strong></td>
                                                    <td><?php echo $driver; ?></td>
                                                    <td><span class="status-badge" style="<?php echo $badge_style; ?>"><?php echo $action; ?></span></td>
                                                    <td><?php echo $purpose; ?></td>
                                                    <td><?php echo $log['date_time']; ?></td>
                                                </tr>
                                                <?php
                                            }
                                        } else {
                                            echo '<tr><td colspan="6" style="text-align: center; color: var(--text-muted)">No physical gate checkpoint logs registered yet.</td></tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" style="text-align: center; color: var(--text-muted)">security_logs table not set in database.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
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

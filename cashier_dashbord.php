<?php
// සෙෂන් සහ ඩේටාබේස් සම්බන්ධතාවය ඇතුළත් කිරීම
require_once 'config.php';

// පද්ධතියට ලොග් වී ඇත්ද සහ මුදල් අයකැමි (Cashier) ද යන්න පරීක්ෂා කිරීම
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: index.php");
    exit();
}



// ඩේටාබේස් එකෙහි සේවකයාට අදාල Cashier ID එක සොයා ගැනීම
$cashier_id = null;
if (isset($_SESSION['employee_id'])) {
    $emp_id = intval($_SESSION['employee_id']);
    $cashier_res = $conn->query("SELECT cashier_id FROM cashier WHERE employee_id = $emp_id");
    if ($cashier_res && $cashier_res->num_rows > 0) {
        $cashier_id = $cashier_res->fetch_assoc()['cashier_id'];
    }
}

// API ඉල්ලීම් හැසිරවීම - භාණ්ඩයක් සෙවීම (Product Lookup API)
if (isset($_GET['api']) && $_GET['api'] === 'get_product') {
    header('Content-Type: application/json');
    $query = trim($_GET['query'] ?? '');
    
    // ID එකෙන් හෝ නමෙන් සෙවීම
    // quantity lives on the product table directly — no inventory JOIN needed
    $sql = "SELECT * FROM product p WHERE p.product_id = ? OR p.product_name LIKE ?";
    $stmt = $conn->prepare($sql);
    $search_like = "%$query%";
    $stmt->bind_param("ss", $query, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'product' => [
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'price' => floatval($product['price']),
                'quantity' => intval($product['quantity'] ?? 0)
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'භාණ්ඩය සොයාගත නොහැකි විය!']);
    }
    $stmt->close();
    exit();
}

// API ඉල්ලීම් හැසිරවීම - බිල සෑදීම සහ තොග අඩු කිරීම (Pos Checkout API)
if (isset($_GET['api']) && $_GET['api'] === 'checkout') {
    header('Content-Type: application/json');
    
    // JSON දත්ත ලබා ගැනීම
    $input = json_decode(file_get_contents('php://input'), true);
    $cart = $input['cart_items'] ?? [];
    $total_amount = floatval($input['total_amount'] ?? 0);
    $cash_paid = floatval($input['cash_paid'] ?? 0);
    
    if (empty($cart) || $total_amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'සාප්පු සවාරි කරත්තය හිස්ය!']);
        exit();
    }
    
    // ඩේටාබේස් එකෙහි customer කෙනෙකු සිටීදැයි බැලීම, නැතිනම් පෙරනිමි Walk-in Customer ඇතුළත් කිරීම
    $customer_id = null;
    $cust_res = $conn->query("SELECT customer_id FROM customer LIMIT 1");
    if ($cust_res && $cust_res->num_rows > 0) {
        $customer_id = $cust_res->fetch_assoc()['customer_id'];
    } else {
        $conn->query("INSERT INTO customer (customer_name, address, contact, email) VALUES ('Walk-in Customer', 'N/A', '0000000000', 'guest@supergills.com')");
        $customer_id = $conn->insert_id;
    }
    
    // බිල් කේතය (Bill Code) එකක් නිර්මාණය කිරීම
    $bill_code = 'INV-' . mt_rand(100000, 999999);
    
    // බිල්පත ඇතුළු කිරීම
    // bill table uses 'bill_date' (not 'date_time') per schema
    $stmt = $conn->prepare("INSERT INTO bill (bill_code, total_amount, customer_id, cashier_id, bill_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("sdii", $bill_code, $total_amount, $customer_id, $cashier_id);
    
    if ($stmt->execute()) {
        $bill_id = $conn->insert_id;
        $stmt->close();
        
        // එක් එක් භාණ්ඩය සඳහා තොග අඩු කිරීම
        // quantity is on the 'product' table; inventory holds the status flag
        foreach ($cart as $item) {
            $p_id = intval($item['product_id']);
            $qty  = intval($item['qty']);
            
            // 1. Deduct quantity from product table
            $upd_stmt = $conn->prepare("UPDATE product SET quantity = GREATEST(quantity - ?, 0) WHERE product_id = ?");
            $upd_stmt->bind_param("ii", $qty, $p_id);
            $upd_stmt->execute();
            $upd_stmt->close();
            
            // 2. Re-sync inventory status and increment stock_out counter
            $status_res = $conn->query("SELECT quantity FROM product WHERE product_id = $p_id");
            $new_qty    = intval($status_res->fetch_assoc()['quantity']);
            $inv_status = ($new_qty <= 0) ? 'out-of-stock' : (($new_qty < 5) ? 'low-stock' : 'in-stock');
            $upd2 = $conn->prepare("UPDATE inventory SET stock_out = stock_out + ?, status = ?, last_restocked_at = NOW() WHERE product_id = ?");
            $upd2->bind_param("isi", $qty, $inv_status, $p_id);
            $upd2->execute();
            $upd2->close();
        }
        
        echo json_encode([
            'success' => true,
            'bill_code' => $bill_code,
            'cashier_name' => $_SESSION['username'],
            'date_time' => date('Y-m-d H:i:s'),
            'total_amount' => $total_amount,
            'cash_paid' => $cash_paid,
            'change_due' => ($cash_paid - $total_amount)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'බිල්පත ඇතුළත් කිරීමට නොහැකි විය: ' . $conn->error]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier POS | SuperGills Food City</title>
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
                <i class="fas fa-cash-register"></i> Cashier: <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
        </div>
    </header>

    <div class="dashboard-container">
        <aside class="sidebar">
            <a href="#" class="nav-item active">
                <i class="fas fa-calculator"></i> POS Checkout
            </a>
            <div style="margin-top: auto; border-top: 1px solid var(--glass-border); padding-top: 20px;">
                <a href="index.php?action=logout" class="nav-item" style="color: #ff4d4d;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="pos-container">
                <!-- wampasa - badu therima saha sappu sawari -->
                <div class="glass-card">
                    <h3 style="margin-bottom: 20px; color: var(--primary-color);"><i class="fas fa-cart-shopping"></i> POS Billing Terminal</h3>
                    <div class="grid-2">
                        <div class="input-group">
                            <label>Quick Select Product</label>
                            <select id="pos-product-select" onchange="selectPOSProduct(this)">
                                <option value="" disabled selected>-- Select Product --</option>
                                <?php
                                // quantity is a column on product table itself — no inventory JOIN needed
                $prods = $conn->query("SELECT * FROM product ORDER BY product_name ASC");
                                while ($row = $prods->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $row['product_id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($row['product_name']); ?>" 
                                            data-price="<?php echo $row['price']; ?>"
                                            data-stock="<?php echo intval($row['quantity']); ?>">
                                        <?php echo htmlspecialchars($row['product_name']); ?> - Rs. <?php echo number_format($row['price'], 2); ?> (Stock: <?php echo intval($row['quantity']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Product Search (ID/Name)</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="item-code" placeholder="Scan or type barcode/query...">
                                <button class="btn btn-primary" onclick="lookupBarcode()" style="padding: 10px; width: 45px;"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="input-group">
                            <label>Quantity</label>
                            <input type="number" id="item-qty" value="1" min="1">
                        </div>
                        <div class="input-group">
                            <label>Retail Price (Rs.)</label>
                            <input type="number" id="item-price" readonly placeholder="0.00">
                        </div>
                    </div>
                    <input type="hidden" id="selected-product-id" value="">
                    <input type="hidden" id="selected-product-name" value="">
                    <input type="hidden" id="selected-product-stock" value="0">
                    
                    <button class="btn btn-primary" style="width: 100%; margin-bottom: 20px;" onclick="addCartItem()"><i class="fas fa-plus"></i> Add Item to Cart</button>

                    <div class="table-container" style="max-height: 280px; overflow-y: auto;">
                        <table id="bill-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="cart-rows">
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted);" id="empty-cart-row">Cart is empty</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- දකුණුපස: සාරාංශය සහ ගෙවීම් විස්තර -->
                <div class="glass-card">
                    <h3>Summary & Payment</h3>
                    <div class="total-display">Rs. <span id="grand-total">0.00</span></div>
                    
                    <div class="input-group">
                        <label>Cash Received (Rs.)</label>
                        <input type="text" id="cash-paid" placeholder="0.00" oninput="calculateChange()">
                    </div>
                    <div class="input-group">
                        <label>Change Due (Rs.)</label>
                        <input type="text" id="balance-due" placeholder="0.00" readonly style="font-weight: bold; color: var(--success-color);">
                    </div>

                    <!-- ස්පර්ශක සංඛ්‍යා පුවරුව (Numeric Pad) -->
                    <div class="numpad">
                        <button class="num-btn" onclick="pressNum('7')">7</button>
                        <button class="num-btn" onclick="pressNum('8')">8</button>
                        <button class="num-btn" onclick="pressNum('9')">9</button>
                        <button class="num-btn" onclick="pressNum('4')">4</button>
                        <button class="num-btn" onclick="pressNum('5')">5</button>
                        <button class="num-btn" onclick="pressNum('6')">6</button>
                        <button class="num-btn" onclick="pressNum('1')">1</button>
                        <button class="num-btn" onclick="pressNum('2')">2</button>
                        <button class="num-btn" onclick="pressNum('3')">3</button>
                        <button class="num-btn" onclick="pressNum('0')">0</button>
                        <button class="num-btn" onclick="pressNum('.')">.</button>
                        <button class="num-btn" style="color: var(--accent-color);" onclick="pressNum('back')"><i class="fas fa-backspace"></i></button>
                    </div>
                    
                    <button class="btn btn-accent" style="width: 100%; margin-top: 20px; height: 60px; font-size: 1.25rem;" onclick="processPOSCheckout()"><i class="fas fa-print"></i> PAY & PRINT RECEIPT</button>
                </div>
            </div>
        </main>
    </div>

    <!-- bill eke visthara (Thermal Print Receipt Overlay Modal) -->
    <div id="receipt-modal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center;">
        <div class="glass-card" style="width: 320px; color: #000; background: #fff; padding: 25px; border-radius: 12px; font-family: 'Courier New', Courier, monospace; box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.4);">
            <div id="print-area">
                <h3 style="text-align: center; margin-bottom: 5px; color: #000; font-weight: bold;">SUPERGILLS FOOD CITY</h3>
                <p style="text-align: center; font-size: 0.85rem; margin-bottom: 2px; color: #000;">Monaragala Road, Buttala</p>
                <p style="text-align: center; font-size: 0.85rem; margin-bottom: 10px; color: #000;">Tel: 0721258751</p>
                <p style="font-size: 0.8rem; margin-bottom: 2px; color: #000;"><strong>Inv:</strong> <span id="r-inv"></span></p>
                <p style="font-size: 0.8rem; margin-bottom: 2px; color: #000;"><strong>Cashier:</strong> <span id="r-cashier"></span></p>
                <p style="font-size: 0.8rem; margin-bottom: 10px; color: #000;"><strong>Date:</strong> <span id="r-date"></span></p>
                <hr style="border-top:1px dashed #000; margin-bottom: 10px; background: none; border-bottom: none;">
                <table style="width: 100%; font-size: 0.85rem; color: #000; margin-bottom: 10px;">
                    <thead>
                        <tr style="border-bottom: 1px dashed #000;">
                            <th style="color: #000; text-align: left; padding: 2px 0; font-family: monospace;">Item</th>
                            <th style="color: #000; text-align: right; padding: 2px 0; font-family: monospace;">Qty</th>
                            <th style="color: #000; text-align: right; padding: 2px 0; font-family: monospace;">Total</th>
                        </tr>
                    </thead>
                    <tbody id="r-items"></tbody>
                </table>
                <hr style="border-top:1px dashed #000; margin-bottom: 10px; background: none; border-bottom: none;">
                <div style="display: flex; justify-content: space-between; font-size: 0.95rem; font-weight: bold; margin-bottom: 5px; color: #000;">
                    <span>Total:</span>
                    <span>Rs. <span id="r-total">0.00</span></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px; color: #000;">
                    <span>Cash Paid:</span>
                    <span>Rs. <span id="r-cash">0.00</span></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 10px; color: #000;">
                    <span>Balance:</span>
                    <span>Rs. <span id="r-balance">0.00</span></span>
                </div>
                <hr style="border-top:1px dashed #000; margin-bottom: 10px; background: none; border-bottom: none;">
                <p style="text-align: center; font-size: 0.85rem; font-weight: bold; color: #000;">THANK YOU, COME AGAIN!</p>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="btn btn-primary" onclick="window.print()" style="flex:1; padding: 8px 10px; font-size: 0.85rem; color:#fff;">Print</button>
                <button class="btn btn-accent" onclick="closeReceiptModal()" style="flex:1; padding: 8px 10px; font-size: 0.85rem;">Close</button>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <p>&copy; 2026 Supergirls Food City. All rights reserved.<br>
            Monaragala Road, Buttala <br>
            0721258751 | suppergills@gmail.com <br></p>
    </footer>

    <script src="script.js"></script>
</body>
</html>

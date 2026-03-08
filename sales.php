<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'get_sales') {
        try {
            $stmt = $pdo->query("
                SELECT s.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, u.username AS cashier
                FROM sales s
                LEFT JOIN patients p ON s.patient_id = p.patient_id
                JOIN users u ON s.user_id = u.user_id
                ORDER BY s.sale_date DESC LIMIT 100
            ");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'get_pos_data') {
        try {
            $patients = $pdo->query("SELECT patient_id, CONCAT(first_name, ' ', last_name) AS name FROM patients ORDER BY first_name ASC")->fetchAll();
            $inventory = $pdo->query("
                SELECT i.batch_id, i.batch_number, i.quantity, i.selling_price, m.name AS medicine_name 
                FROM inventory i 
                JOIN medicines m ON i.medicine_id = m.medicine_id 
                WHERE i.quantity > 0 AND i.expiry_date >= CURDATE()
                ORDER BY m.name ASC
            ")->fetchAll();
            echo json_encode(['status' => 'success', 'patients' => $patients, 'inventory' => $inventory]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'process_sale') {
        $patient_id = !empty($_POST['patient_id']) ? $_POST['patient_id'] : null;
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $cart = json_decode($_POST['cart'], true);
        $user_id = $_SESSION['user_id'];
        
        if (empty($cart)) {
            echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            
            $total_amount = 0;
            foreach ($cart as $item) {
                $total_amount += $item['subtotal'];
            }

            $stmt = $pdo->prepare("INSERT INTO sales (user_id, patient_id, total_amount, payment_method) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $patient_id, $total_amount, $payment_method]);
            $sale_id = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, batch_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stockStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE batch_id = ? AND quantity >= ?");

            foreach ($cart as $item) {
                $itemStmt->execute([$sale_id, $item['batch_id'], $item['quantity'], $item['price'], $item['subtotal']]);
                
                $stockStmt->execute([$item['quantity'], $item['batch_id'], $item['quantity']]);
                if ($stockStmt->rowCount() === 0) {
                    throw new Exception("Insufficient stock for batch ID: " . $item['batch_id']);
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

$theme = $_SESSION['theme'] ?? 'light';
$lang = $_SESSION['lang'] ?? 'en';
$dir = $lang === 'ku' ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>" data-theme="<?php echo htmlspecialchars($theme); ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales & POS</title>
    <style>
        :root[data-theme="light"] {
            --bg-main: #f4f7f6; --bg-sidebar: #2c3e50; --bg-card: #ffffff;
            --text-main: #333333; --text-sidebar: #ecf0f1; --accent: #3498db;
            --danger: #e74c3c; --success: #2ecc71; --border: #e0e0e0;
            --modal-bg: rgba(0,0,0,0.5); --pos-bg: #f8f9fa;
        }
        :root[data-theme="dark"] {
            --bg-main: #1a1a2e; --bg-sidebar: #16213e; --bg-card: #0f3460;
            --text-main: #e0e0e0; --text-sidebar: #e0e0e0; --accent: #e94560;
            --danger: #ff4757; --success: #2ed573; --border: #2a2a4a;
            --modal-bg: rgba(0,0,0,0.7); --pos-bg: #112240;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: var(--bg-main); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 250px; background: var(--bg-sidebar); color: var(--text-sidebar); display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; font-size: 1.5rem; font-weight: bold; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-links { list-style: none; flex: 1; padding-top: 20px; overflow-y: auto; }
        .nav-links li { padding: 15px 25px; cursor: pointer; }
        .nav-links li:hover, .nav-links li.active { background: rgba(255,255,255,0.1); border-left: 4px solid var(--accent); }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { background: var(--bg-card); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
        .controls button, .btn { padding: 8px 12px; margin-left: 10px; border: none; border-radius: 5px; cursor: pointer; background: var(--accent); color: white; font-weight: bold; }
        .btn-success { background: var(--success); }
        .btn-danger { background: var(--danger); padding: 5px 10px; font-size: 0.8rem; }
        .content-area { padding: 30px; }
        .action-bar { display: flex; justify-content: space-between; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: var(--bg-card); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: rgba(0,0,0,0.05); font-weight: bold; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--modal-bg); justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-card); padding: 20px; border-radius: 8px; width: 100%; max-width: 900px; max-height: 95vh; overflow-y: auto; display: flex; gap: 20px; }
        .close-btn { cursor: pointer; font-size: 1.5rem; line-height: 1; position: absolute; right: 20px; top: 20px; }
        
        .pos-left { flex: 2; background: var(--pos-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--border); }
        .pos-right { flex: 1; display: flex; flex-direction: column; gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group select, .form-group input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg-card); color: var(--text-main); font-family: inherit; }
        
        .cart-table { margin-top: 15px; }
        .cart-table th, .cart-table td { padding: 8px; font-size: 0.9rem; }
        .totals-box { background: var(--pos-bg); padding: 20px; border-radius: 8px; border: 1px solid var(--border); }
        .totals-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 1.1rem; }
        .totals-row.grand-total { font-size: 1.5rem; font-weight: bold; color: var(--accent); border-top: 2px solid var(--border); padding-top: 10px; }
        #message { margin-top: 10px; text-align: center; font-weight: bold; color: var(--danger); }
        
        .add-item-row { display: flex; gap: 10px; align-items: flex-end; }
        .add-item-row .form-group { margin-bottom: 0; }
        .add-item-row .sel-med { flex: 3; }
        .add-item-row .sel-qty { flex: 1; }

        [dir="rtl"] .nav-links li:hover, [dir="rtl"] .nav-links li.active { border-left: none; border-right: 4px solid var(--accent); }
        [dir="rtl"] th, [dir="rtl"] td { text-align: right; }
        [dir="rtl"] .controls button, [dir="rtl"] .btn { margin-left: 0; margin-right: 10px; }
        [dir="rtl"] .close-btn { right: auto; left: 20px; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header" id="brandName">PharmaSys</div>
        <ul class="nav-links">
            <li id="navDash" onclick="window.location.href='dashboard.php'">Dashboard</li>
            <li id="navMed" onclick="window.location.href='medicines.php'">Medicines</li>
            <li id="navInv" onclick="window.location.href='inventory.php'">Inventory</li>
            <li id="navPat" onclick="window.location.href='patients.php'">Patients</li>
            <li id="navPres" onclick="window.location.href='prescriptions.php'">Prescriptions</li>
            <li id="navSales" class="active">Sales</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Sales History</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
            </div>
        </header>

        <section class="content-area">
            <div class="action-bar">
                <button class="btn btn-success" id="newSaleBtn">New POS Sale</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th id="thDate">Date</th>
                        <th id="thPat">Patient</th>
                        <th id="thTotal">Total Amount</th>
                        <th id="thPay">Payment Method</th>
                        <th id="thCashier">Cashier</th>
                    </tr>
                </thead>
                <tbody id="salesBody">
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="posModal">
        <div class="modal-content" style="position: relative;">
            <span class="close-btn" id="closeModal">&times;</span>
            
            <div class="pos-left">
                <h3 id="lblAddItems" style="margin-bottom: 15px;">Add Items</h3>
                <div class="add-item-row">
                    <div class="form-group sel-med">
                        <label for="inv_select" id="lblSelMed">Select Medicine (Batch)</label>
                        <select id="inv_select">
                            <option value="">-- Select --</option>
                        </select>
                    </div>
                    <div class="form-group sel-qty">
                        <label for="inv_qty" id="lblQty">Qty</label>
                        <input type="number" id="inv_qty" min="1" value="1">
                    </div>
                    <button class="btn" id="addToCartBtn">Add</button>
                </div>

                <table class="cart-table">
                    <thead>
                        <tr>
                            <th id="ctMed">Medicine</th>
                            <th id="ctPrice">Price</th>
                            <th id="ctQty">Qty</th>
                            <th id="ctSub">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                    </tbody>
                </table>
            </div>

            <div class="pos-right">
                <div class="form-group">
                    <label for="patient_id" id="lblPatSelect">Select Patient (Walk-in if empty)</label>
                    <select id="patient_id">
                        <option value="">-- Walk-in Customer --</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="payment_method" id="lblPayMethod">Payment Method</label>
                    <select id="payment_method">
                        <option value="cash" id="optCash">Cash</option>
                        <option value="card" id="optCard">Card</option>
                        <option value="insurance" id="optIns">Insurance</option>
                    </select>
                </div>

                <div class="totals-box">
                    <div class="totals-row grand-total">
                        <span id="lblTotal">Total:</span>
                        <span id="cartTotal">$0.00</span>
                    </div>
                    <button class="btn btn-success" id="checkoutBtn" style="width: 100%; margin-top: 15px; padding: 15px; font-size: 1.1rem;">Complete Sale</button>
                    <div id="message"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const translations = {
            en: {
                brand: "PharmaSys", navDash: "Dashboard", navMed: "Medicines", navInv: "Inventory",
                navPat: "Patients", navPres: "Prescriptions", navSales: "Sales", pageTitle: "Sales History",
                newSaleBtn: "New POS Sale", thDate: "Date", thPat: "Patient", thTotal: "Total Amount",
                thPay: "Payment Method", thCashier: "Cashier", lblAddItems: "Add Items", lblSelMed: "Select Medicine (Batch)",
                lblQty: "Qty", addToCartBtn: "Add", ctMed: "Medicine", ctPrice: "Price", ctQty: "Qty", ctSub: "Subtotal",
                lblPatSelect: "Select Patient (Walk-in if empty)", lblPayMethod: "Payment Method",
                optCash: "Cash", optCard: "Card", optIns: "Insurance", lblTotal: "Total:", checkoutBtn: "Complete Sale",
                toggleLang: "KU", walkIn: "-- Walk-in Customer --", selectOpt: "-- Select --"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navInv: "کۆگا",
                navPat: "نەخۆشەکان", navPres: "ڕەچەتەکان", navSales: "فرۆشتن", pageTitle: "مێژووی فرۆشتن",
                newSaleBtn: "فرۆشتنی نوێ", thDate: "بەروار", thPat: "نەخۆش", thTotal: "بڕی گشتی",
                thPay: "شێوازی پارەدان", thCashier: "کاشێر", lblAddItems: "زیادکردنی کاڵا", lblSelMed: "دەرمان هەڵبژێرە (وەجبە)",
                lblQty: "بڕ", addToCartBtn: "زیادکردن", ctMed: "دەرمان", ctPrice: "نرخ", ctQty: "بڕ", ctSub: "کۆی کاتی",
                lblPatSelect: "نەخۆش هەڵبژێرە (بەتاڵ بۆ کڕیاری ئاسایی)", lblPayMethod: "شێوازی پارەدان",
                optCash: "کاش", optCard: "کارت", optIns: "دڵنیایی", lblTotal: "کۆی گشتی:", checkoutBtn: "تەواوکردنی فرۆشتن",
                toggleLang: "EN", walkIn: "-- کڕیاری ئاسایی --", selectOpt: "-- هەڵبژێرە --"
            }
        };

        let currentLang = document.documentElement.lang || 'en';
        let cart = [];
        let inventoryData = [];

        function applyTranslations() {
            const t = translations[currentLang];
            document.getElementById('brandName').innerText = t.brand;
            document.getElementById('navDash').innerText = t.navDash;
            document.getElementById('navMed').innerText = t.navMed;
            document.getElementById('navInv').innerText = t.navInv;
            document.getElementById('navPat').innerText = t.navPat;
            document.getElementById('navPres').innerText = t.navPres;
            document.getElementById('navSales').innerText = t.navSales;
            document.getElementById('pageTitle').innerText = t.pageTitle;
            document.getElementById('newSaleBtn').innerText = t.newSaleBtn;
            document.getElementById('thDate').innerText = t.thDate;
            document.getElementById('thPat').innerText = t.thPat;
            document.getElementById('thTotal').innerText = t.thTotal;
            document.getElementById('thPay').innerText = t.thPay;
            document.getElementById('thCashier').innerText = t.thCashier;
            document.getElementById('lblAddItems').innerText = t.lblAddItems;
            document.getElementById('lblSelMed').innerText = t.lblSelMed;
            document.getElementById('lblQty').innerText = t.lblQty;
            document.getElementById('addToCartBtn').innerText = t.addToCartBtn;
            document.getElementById('ctMed').innerText = t.ctMed;
            document.getElementById('ctPrice').innerText = t.ctPrice;
            document.getElementById('ctQty').innerText = t.ctQty;
            document.getElementById('ctSub').innerText = t.ctSub;
            document.getElementById('lblPatSelect').innerText = t.lblPatSelect;
            document.getElementById('lblPayMethod').innerText = t.lblPayMethod;
            document.getElementById('optCash').innerText = t.optCash;
            document.getElementById('optCard').innerText = t.optCard;
            document.getElementById('optIns').innerText = t.optIns;
            document.getElementById('lblTotal').innerText = t.lblTotal;
            document.getElementById('checkoutBtn').innerText = t.checkoutBtn;
            document.getElementById('langToggle').innerText = t.toggleLang;
            
            if(document.getElementById('patient_id').options.length > 0) {
                document.getElementById('patient_id').options[0].text = t.walkIn;
            }
            if(document.getElementById('inv_select').options.length > 0) {
                document.getElementById('inv_select').options[0].text = t.selectOpt;
            }

            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
            renderCart();
        }

        document.getElementById('langToggle').addEventListener('click', () => {
            currentLang = currentLang === 'en' ? 'ku' : 'en';
            applyTranslations();
        });

        document.getElementById('themeToggle').addEventListener('click', () => {
            const htmlNode = document.documentElement;
            const currentTheme = htmlNode.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            htmlNode.setAttribute('data-theme', newTheme);
            document.getElementById('themeToggle').innerText = newTheme === 'light' ? '🌙' : '☀️';
        });

        const modal = document.getElementById('posModal');

        document.getElementById('newSaleBtn').addEventListener('click', () => {
            cart = [];
            document.getElementById('patient_id').value = '';
            document.getElementById('payment_method').value = 'cash';
            document.getElementById('inv_select').value = '';
            document.getElementById('inv_qty').value = '1';
            document.getElementById('message').innerText = '';
            renderCart();
            modal.classList.add('active');
        });

        document.getElementById('closeModal').addEventListener('click', () => {
            if(cart.length > 0) {
                if(confirm('Cancel current sale?')) modal.classList.remove('active');
            } else {
                modal.classList.remove('active');
            }
        });

        function loadSales() {
            const formData = new FormData();
            formData.append('action', 'get_sales');
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('salesBody');
                tbody.innerHTML = '';
                if(data.status === 'success' && data.data) {
                    data.data.forEach(sale => {
                        const tr = document.createElement('tr');
                        const payText = translations[currentLang]['opt' + sale.payment_method.charAt(0).toUpperCase() + sale.payment_method.slice(1)];
                        tr.innerHTML = `
                            <td>${sale.sale_id}</td>
                            <td>${sale.sale_date}</td>
                            <td>${sale.patient_name || translations[currentLang].walkIn.replace(/-- /g,'').replace(/ --/g,'')}</td>
                            <td style="font-weight:bold; color:var(--accent);">$${sale.total_amount}</td>
                            <td>${payText}</td>
                            <td>${sale.cashier}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }

        function loadPOSData() {
            const formData = new FormData();
            formData.append('action', 'get_pos_data');
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    inventoryData = data.inventory;
                    
                    const patSelect = document.getElementById('patient_id');
                    patSelect.innerHTML = `<option value="">${translations[currentLang].walkIn}</option>`;
                    data.patients.forEach(pat => {
                        patSelect.innerHTML += `<option value="${pat.patient_id}">${pat.name}</option>`;
                    });

                    const invSelect = document.getElementById('inv_select');
                    invSelect.innerHTML = `<option value="">${translations[currentLang].selectOpt}</option>`;
                    data.inventory.forEach(inv => {
                        invSelect.innerHTML += `<option value="${inv.batch_id}">${inv.medicine_name} (Batch: ${inv.batch_number}) - $${inv.selling_price} - Stock: ${inv.quantity}</option>`;
                    });
                }
            });
        }

        document.getElementById('addToCartBtn').addEventListener('click', () => {
            const batchId = document.getElementById('inv_select').value;
            const qty = parseInt(document.getElementById('inv_qty').value);
            const msg = document.getElementById('message');
            
            if(!batchId || qty < 1) return;

            const item = inventoryData.find(i => i.batch_id == batchId);
            if(!item) return;

            if(qty > item.quantity) {
                msg.innerText = `Only ${item.quantity} in stock!`;
                return;
            }

            msg.innerText = '';
            
            const existing = cart.find(c => c.batch_id == batchId);
            if(existing) {
                if((existing.quantity + qty) > item.quantity) {
                    msg.innerText = `Cannot add more. Stock limit reached.`;
                    return;
                }
                existing.quantity += qty;
                existing.subtotal = existing.quantity * existing.price;
            } else {
                cart.push({
                    batch_id: batchId,
                    name: item.medicine_name,
                    price: parseFloat(item.selling_price),
                    quantity: qty,
                    subtotal: qty * parseFloat(item.selling_price)
                });
            }
            
            document.getElementById('inv_select').value = '';
            document.getElementById('inv_qty').value = '1';
            renderCart();
        });

        window.removeCartItem = function(index) {
            cart.splice(index, 1);
            renderCart();
        }

        function renderCart() {
            const tbody = document.getElementById('cartBody');
            tbody.innerHTML = '';
            let total = 0;

            cart.forEach((item, index) => {
                total += item.subtotal;
                tbody.innerHTML += `
                    <tr>
                        <td>${item.name}</td>
                        <td>$${item.price.toFixed(2)}</td>
                        <td>${item.quantity}</td>
                        <td>$${item.subtotal.toFixed(2)}</td>
                        <td><button class="btn btn-danger" onclick="removeCartItem(${index})">X</button></td>
                    </tr>
                `;
            });

            document.getElementById('cartTotal').innerText = `$${total.toFixed(2)}`;
        }

        document.getElementById('checkoutBtn').addEventListener('click', () => {
            if(cart.length === 0) {
                document.getElementById('message').innerText = 'Cart is empty!';
                return;
            }

            document.getElementById('checkoutBtn').disabled = true;
            document.getElementById('message').innerText = 'Processing...';

            const formData = new FormData();
            formData.append('action', 'process_sale');
            formData.append('patient_id', document.getElementById('patient_id').value);
            formData.append('payment_method', document.getElementById('payment_method').value);
            formData.append('cart', JSON.stringify(cart));

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                document.getElementById('checkoutBtn').disabled = false;
                if(data.status === 'success') {
                    modal.classList.remove('active');
                    loadSales();
                    loadPOSData();
                } else {
                    document.getElementById('message').innerText = data.message;
                }
            })
            .catch(() => {
                document.getElementById('checkoutBtn').disabled = false;
                document.getElementById('message').innerText = 'Network error';
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            loadSales();
            loadPOSData();
            applyTranslations();
            const initialTheme = document.documentElement.getAttribute('data-theme');
            document.getElementById('themeToggle').innerText = initialTheme === 'light' ? '🌙' : '☀️';
        });
    </script>
</body>
</html>
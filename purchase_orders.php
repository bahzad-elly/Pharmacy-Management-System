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

    if ($action === 'get_pos') {
        try {
            $stmt = $pdo->query("
                SELECT po.*, s.name AS supplier_name, u.username AS created_by
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                JOIN users u ON po.user_id = u.user_id
                ORDER BY po.order_date DESC
            ");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'get_form_data') {
        try {
            $suppliers = $pdo->query("SELECT supplier_id, name FROM suppliers ORDER BY name ASC")->fetchAll();
            $medicines = $pdo->query("SELECT medicine_id, name FROM medicines ORDER BY name ASC")->fetchAll();
            echo json_encode(['status' => 'success', 'suppliers' => $suppliers, 'medicines' => $medicines]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'save_po') {
        $supplier_id = $_POST['supplier_id'] ?? '';
        $order_date = $_POST['order_date'] ?? '';
        $expected_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;
        $items = json_decode($_POST['items'], true);
        $user_id = $_SESSION['user_id'];
        
        if (empty($supplier_id) || empty($order_date) || empty($items)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields or empty item list']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            
            $total_amount = 0;
            foreach ($items as $item) {
                $total_amount += $item['subtotal'];
            }

            $stmt = $pdo->prepare("INSERT INTO purchase_orders (supplier_id, user_id, order_date, expected_delivery_date, status, total_amount) VALUES (?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$supplier_id, $user_id, $order_date, $expected_date, $total_amount]);
            $po_id = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, medicine_id, quantity_ordered, unit_cost) VALUES (?, ?, ?, ?)");
            
            foreach ($items as $item) {
                $itemStmt->execute([$po_id, $item['medicine_id'], $item['quantity'], $item['cost']]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Database error processing order']);
        }
        exit;
    }

    if ($action === 'update_status') {
        $po_id = $_POST['po_id'] ?? '';
        $new_status = $_POST['status'] ?? '';
        try {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE po_id = ?");
            $stmt->execute([$new_status, $po_id]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
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
    <title>Purchase Orders</title>
    <style>
        :root[data-theme="light"] {
            --bg-main: #f4f7f6; --bg-sidebar: #2c3e50; --bg-card: #ffffff;
            --text-main: #333333; --text-sidebar: #ecf0f1; --accent: #3498db;
            --danger: #e74c3c; --success: #2ecc71; --warning: #f1c40f; --border: #e0e0e0;
            --modal-bg: rgba(0,0,0,0.5); --pos-bg: #f8f9fa;
        }
        :root[data-theme="dark"] {
            --bg-main: #1a1a2e; --bg-sidebar: #16213e; --bg-card: #0f3460;
            --text-main: #e0e0e0; --text-sidebar: #e0e0e0; --accent: #e94560;
            --danger: #ff4757; --success: #2ed573; --warning: #ffa502; --border: #2a2a4a;
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
        .btn-warning { background: var(--warning); color: #000; }
        .content-area { padding: 30px; }
        .action-bar { display: flex; justify-content: space-between; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: var(--bg-card); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: rgba(0,0,0,0.05); font-weight: bold; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; color: white; }
        .status-pending { background: var(--warning); color: #000; }
        .status-ordered { background: var(--accent); }
        .status-received { background: var(--success); }
        .status-cancelled { background: var(--danger); }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--modal-bg); justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-card); padding: 25px; border-radius: 8px; width: 100%; max-width: 800px; max-height: 95vh; overflow-y: auto; position: relative; }
        .close-btn { cursor: pointer; font-size: 1.5rem; line-height: 1; position: absolute; right: 20px; top: 20px; }
        .modal-title { font-size: 1.2rem; font-weight: bold; margin-bottom: 20px; }
        
        .grid-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group select, .form-group input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg-card); color: var(--text-main); font-family: inherit; }
        
        .add-item-bar { display: flex; gap: 10px; align-items: flex-end; background: var(--pos-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 15px; }
        .add-item-bar .sel-med { flex: 2; }
        .add-item-bar .sel-qty, .add-item-bar .sel-cost { flex: 1; }
        
        .cart-table { margin-bottom: 15px; }
        .cart-table th, .cart-table td { padding: 10px; border-bottom: 1px solid var(--border); }
        .total-row { text-align: right; font-size: 1.2rem; font-weight: bold; padding-top: 15px; color: var(--accent); }
        #message { margin-top: 10px; text-align: center; font-weight: bold; }

        [dir="rtl"] .nav-links li:hover, [dir="rtl"] .nav-links li.active { border-left: none; border-right: 4px solid var(--accent); }
        [dir="rtl"] th, [dir="rtl"] td { text-align: right; }
        [dir="rtl"] .controls button, [dir="rtl"] .btn { margin-left: 0; margin-right: 10px; }
        [dir="rtl"] .close-btn { right: auto; left: 20px; }
        [dir="rtl"] .total-row { text-align: left; }
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
            <li id="navSales" onclick="window.location.href='sales.php'">Sales</li>
            <li id="navPO" class="active">Purchase Orders</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Purchase Orders</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
            </div>
        </header>

        <section class="content-area">
            <div class="action-bar">
                <button class="btn btn-success" id="newPOBtn">Create Purchase Order</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th id="thDate">Order Date</th>
                        <th id="thSup">Supplier</th>
                        <th id="thTotal">Total Cost</th>
                        <th id="thStatus">Status</th>
                        <th id="thAct">Actions</th>
                    </tr>
                </thead>
                <tbody id="poBody">
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="poModal">
        <div class="modal-content">
            <span class="close-btn" id="closeModal">&times;</span>
            <div class="modal-title" id="lblModalTitle">New Purchase Order</div>
            
            <div class="grid-row">
                <div class="form-group">
                    <label for="supplier_id" id="lblSup">Supplier</label>
                    <select id="supplier_id"></select>
                </div>
                <div class="form-group">
                    <label for="order_date" id="lblDate">Order Date</label>
                    <input type="date" id="order_date">
                </div>
            </div>
            
            <div class="form-group">
                <label for="expected_date" id="lblExpDate">Expected Delivery Date</label>
                <input type="date" id="expected_date">
            </div>

            <div class="add-item-bar">
                <div class="form-group sel-med" style="margin-bottom:0;">
                    <label for="med_select" id="lblMed">Medicine</label>
                    <select id="med_select"><option value="">-- Select --</option></select>
                </div>
                <div class="form-group sel-qty" style="margin-bottom:0;">
                    <label for="item_qty" id="lblQty">Qty</label>
                    <input type="number" id="item_qty" min="1" value="1">
                </div>
                <div class="form-group sel-cost" style="margin-bottom:0;">
                    <label for="item_cost" id="lblCost">Unit Cost</label>
                    <input type="number" step="0.01" id="item_cost" min="0" value="0.00">
                </div>
                <button class="btn" id="addItemBtn">Add</button>
            </div>

            <table class="cart-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th id="ctMed">Medicine</th>
                        <th id="ctCost">Cost</th>
                        <th id="ctQty">Qty</th>
                        <th id="ctSub">Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                </tbody>
            </table>
            
            <div class="total-row">
                <span id="lblTotal">Grand Total: </span>
                <span id="grandTotal">$0.00</span>
            </div>

            <button class="btn btn-success" id="savePOBtn" style="width: 100%; margin-top: 20px; padding: 12px;">Save Order</button>
            <div id="message"></div>
        </div>
    </div>

    <script>
        const translations = {
            en: {
                brand: "PharmaSys", navDash: "Dashboard", navMed: "Medicines", navInv: "Inventory",
                navPat: "Patients", navPres: "Prescriptions", navSales: "Sales", navPO: "Purchase Orders",
                pageTitle: "Purchase Orders", newPOBtn: "Create Purchase Order", thDate: "Order Date",
                thSup: "Supplier", thTotal: "Total Cost", thStatus: "Status", thAct: "Actions",
                lblModalTitle: "New Purchase Order", lblSup: "Supplier", lblDate: "Order Date",
                lblExpDate: "Expected Delivery Date", lblMed: "Medicine", lblQty: "Qty", lblCost: "Unit Cost",
                addItemBtn: "Add", ctMed: "Medicine", ctCost: "Cost", ctQty: "Qty", ctSub: "Subtotal",
                lblTotal: "Grand Total: ", savePOBtn: "Save Order", toggleLang: "KU", selectOpt: "-- Select --",
                optPending: "Pending", optOrdered: "Ordered", optReceived: "Received", optCancelled: "Cancelled",
                btnUpdate: "Mark Received"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navInv: "کۆگا",
                navPat: "نەخۆشەکان", navPres: "ڕەچەتەکان", navSales: "فرۆشتن", navPO: "داواکارییەکانی کڕین",
                pageTitle: "داواکارییەکانی کڕین", newPOBtn: "دروستکردنی داواکاری نوێ", thDate: "بەرواری داواکاری",
                thSup: "دابینکەر", thTotal: "کۆی تێچوو", thStatus: "دۆخ", thAct: "کردارەکان",
                lblModalTitle: "داواکاری کڕینی نوێ", lblSup: "دابینکەر", lblDate: "بەرواری داواکاری",
                lblExpDate: "کاتی گەیشتنی چاوەڕوانکراو", lblMed: "دەرمان", lblQty: "بڕ", lblCost: "نرخی دانە",
                addItemBtn: "زیادکردن", ctMed: "دەرمان", ctCost: "نرخ", ctQty: "بڕ", ctSub: "کۆی کاتی",
                lblTotal: "کۆی گشتی: ", savePOBtn: "پاشەکەوتکردنی داواکاری", toggleLang: "EN", selectOpt: "-- هەڵبژێرە --",
                optPending: "هەڵپەسێردراو", optOrdered: "داواکراو", optReceived: "وەرگیراو", optCancelled: "هەڵوەشاوەتەوە",
                btnUpdate: "گۆڕین بۆ وەرگیراو"
            }
        };

        let currentLang = document.documentElement.lang || 'en';
        let itemsList = [];
        let medicinesData = [];

        function applyTranslations() {
            const t = translations[currentLang];
            document.getElementById('brandName').innerText = t.brand;
            document.getElementById('navDash').innerText = t.navDash;
            document.getElementById('navMed').innerText = t.navMed;
            document.getElementById('navInv').innerText = t.navInv;
            document.getElementById('navPat').innerText = t.navPat;
            document.getElementById('navPres').innerText = t.navPres;
            document.getElementById('navSales').innerText = t.navSales;
            document.getElementById('navPO').innerText = t.navPO;
            document.getElementById('pageTitle').innerText = t.pageTitle;
            document.getElementById('newPOBtn').innerText = t.newPOBtn;
            document.getElementById('thDate').innerText = t.thDate;
            document.getElementById('thSup').innerText = t.thSup;
            document.getElementById('thTotal').innerText = t.thTotal;
            document.getElementById('thStatus').innerText = t.thStatus;
            document.getElementById('thAct').innerText = t.thAct;
            document.getElementById('lblModalTitle').innerText = t.lblModalTitle;
            document.getElementById('lblSup').innerText = t.lblSup;
            document.getElementById('lblDate').innerText = t.lblDate;
            document.getElementById('lblExpDate').innerText = t.lblExpDate;
            document.getElementById('lblMed').innerText = t.lblMed;
            document.getElementById('lblQty').innerText = t.lblQty;
            document.getElementById('lblCost').innerText = t.lblCost;
            document.getElementById('addItemBtn').innerText = t.addItemBtn;
            document.getElementById('ctMed').innerText = t.ctMed;
            document.getElementById('ctCost').innerText = t.ctCost;
            document.getElementById('ctQty').innerText = t.ctQty;
            document.getElementById('ctSub').innerText = t.ctSub;
            document.getElementById('lblTotal').innerText = t.lblTotal;
            document.getElementById('savePOBtn').innerText = t.savePOBtn;
            document.getElementById('langToggle').innerText = t.toggleLang;
            
            if(document.getElementById('supplier_id').options.length > 0) {
                document.getElementById('supplier_id').options[0].text = t.selectOpt;
            }
            if(document.getElementById('med_select').options.length > 0) {
                document.getElementById('med_select').options[0].text = t.selectOpt;
            }

            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
            renderItems();
            loadPOs();
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

        const modal = document.getElementById('poModal');

        document.getElementById('newPOBtn').addEventListener('click', () => {
            itemsList = [];
            document.getElementById('supplier_id').value = '';
            document.getElementById('order_date').valueAsDate = new Date();
            document.getElementById('expected_date').value = '';
            document.getElementById('med_select').value = '';
            document.getElementById('item_qty').value = '1';
            document.getElementById('item_cost').value = '0.00';
            document.getElementById('message').innerText = '';
            renderItems();
            modal.classList.add('active');
        });

        document.getElementById('closeModal').addEventListener('click', () => {
            modal.classList.remove('active');
        });

        function loadPOs() {
            const formData = new FormData();
            formData.append('action', 'get_pos');
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('poBody');
                tbody.innerHTML = '';
                if(data.status === 'success' && data.data) {
                    data.data.forEach(po => {
                        const tr = document.createElement('tr');
                        const statusText = translations[currentLang]['opt' + po.status.charAt(0).toUpperCase() + po.status.slice(1)];
                        
                        let actionBtn = '';
                        if(po.status === 'pending' || po.status === 'ordered') {
                            actionBtn = `<button class="btn btn-warning" onclick="markReceived(${po.po_id})">${translations[currentLang].btnUpdate}</button>`;
                        }

                        tr.innerHTML = `
                            <td>${po.po_id}</td>
                            <td>${po.order_date}</td>
                            <td>${po.supplier_name || 'N/A'}</td>
                            <td style="font-weight:bold; color:var(--accent);">$${po.total_amount}</td>
                            <td><span class="status-badge status-${po.status}">${statusText}</span></td>
                            <td>${actionBtn}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }

        function loadFormData() {
            const formData = new FormData();
            formData.append('action', 'get_form_data');
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    medicinesData = data.medicines;
                    
                    const supSelect = document.getElementById('supplier_id');
                    supSelect.innerHTML = `<option value="">${translations[currentLang].selectOpt}</option>`;
                    data.suppliers.forEach(sup => {
                        supSelect.innerHTML += `<option value="${sup.supplier_id}">${sup.name}</option>`;
                    });

                    const medSelect = document.getElementById('med_select');
                    medSelect.innerHTML = `<option value="">${translations[currentLang].selectOpt}</option>`;
                    data.medicines.forEach(med => {
                        medSelect.innerHTML += `<option value="${med.medicine_id}">${med.name}</option>`;
                    });
                }
            });
        }

        document.getElementById('addItemBtn').addEventListener('click', () => {
            const medId = document.getElementById('med_select').value;
            const qty = parseInt(document.getElementById('item_qty').value);
            const cost = parseFloat(document.getElementById('item_cost').value);
            
            if(!medId || qty < 1 || cost < 0) return;

            const medInfo = medicinesData.find(m => m.medicine_id == medId);
            if(!medInfo) return;

            const existing = itemsList.find(i => i.medicine_id == medId);
            if(existing) {
                existing.quantity += qty;
                existing.cost = cost; 
                existing.subtotal = existing.quantity * existing.cost;
            } else {
                itemsList.push({
                    medicine_id: medId,
                    name: medInfo.name,
                    quantity: qty,
                    cost: cost,
                    subtotal: qty * cost
                });
            }
            
            document.getElementById('med_select').value = '';
            document.getElementById('item_qty').value = '1';
            document.getElementById('item_cost').value = '0.00';
            renderItems();
        });

        window.removeItem = function(index) {
            itemsList.splice(index, 1);
            renderItems();
        }

        function renderItems() {
            const tbody = document.getElementById('itemsBody');
            tbody.innerHTML = '';
            let total = 0;

            itemsList.forEach((item, index) => {
                total += item.subtotal;
                tbody.innerHTML += `
                    <tr>
                        <td>${item.name}</td>
                        <td>$${item.cost.toFixed(2)}</td>
                        <td>${item.quantity}</td>
                        <td>$${item.subtotal.toFixed(2)}</td>
                        <td><button class="btn btn-danger" onclick="removeItem(${index})">X</button></td>
                    </tr>
                `;
            });

            document.getElementById('grandTotal').innerText = `$${total.toFixed(2)}`;
        }

        document.getElementById('savePOBtn').addEventListener('click', () => {
            const supId = document.getElementById('supplier_id').value;
            const date = document.getElementById('order_date').value;
            
            if(itemsList.length === 0 || !supId || !date) {
                document.getElementById('message').style.color = 'var(--danger)';
                document.getElementById('message').innerText = 'Please select a supplier, date, and add at least one item.';
                return;
            }

            document.getElementById('savePOBtn').disabled = true;
            document.getElementById('message').innerText = 'Saving...';

            const formData = new FormData();
            formData.append('action', 'save_po');
            formData.append('supplier_id', supId);
            formData.append('order_date', date);
            formData.append('expected_delivery_date', document.getElementById('expected_date').value);
            formData.append('items', JSON.stringify(itemsList));

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                document.getElementById('savePOBtn').disabled = false;
                if(data.status === 'success') {
                    modal.classList.remove('active');
                    loadPOs();
                } else {
                    document.getElementById('message').style.color = 'var(--danger)';
                    document.getElementById('message').innerText = data.message;
                }
            })
            .catch(() => {
                document.getElementById('savePOBtn').disabled = false;
                document.getElementById('message').style.color = 'var(--danger)';
                document.getElementById('message').innerText = 'Network error';
            });
        });

        window.markReceived = function(id) {
            if(confirm('Mark this order as Received?')) {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('po_id', id);
                formData.append('status', 'received');

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') loadPOs();
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadFormData();
            applyTranslations();
            const initialTheme = document.documentElement.getAttribute('data-theme');
            document.getElementById('themeToggle').innerText = initialTheme === 'light' ? '🌙' : '☀️';
        });
    </script>
</body>
</html>
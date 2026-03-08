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

    if ($action === 'get_inventory') {
        try {
            $stmt = $pdo->query("
                SELECT i.*, m.name AS medicine_name 
                FROM inventory i 
                JOIN medicines m ON i.medicine_id = m.medicine_id 
                ORDER BY i.expiry_date ASC
            ");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'get_medicines_list') {
        try {
            $stmt = $pdo->query("SELECT medicine_id, name FROM medicines ORDER BY name ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'save_inventory') {
        $id = $_POST['batch_id'] ?? '';
        $medicine_id = $_POST['medicine_id'] ?? '';
        $batch_number = trim($_POST['batch_number'] ?? '');
        $quantity = $_POST['quantity'] ?? 0;
        $cost_price = $_POST['cost_price'] ?? 0;
        $selling_price = $_POST['selling_price'] ?? 0;
        $expiry_date = $_POST['expiry_date'] ?? '';

        if (empty($medicine_id) || empty($batch_number) || empty($expiry_date)) {
            echo json_encode(['status' => 'error', 'message' => 'Required fields are missing']);
            exit;
        }

        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO inventory (medicine_id, batch_number, quantity, cost_price, selling_price, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$medicine_id, $batch_number, $quantity, $cost_price, $selling_price, $expiry_date]);
            } else {
                $stmt = $pdo->prepare("UPDATE inventory SET medicine_id = ?, batch_number = ?, quantity = ?, cost_price = ?, selling_price = ?, expiry_date = ? WHERE batch_id = ?");
                $stmt->execute([$medicine_id, $batch_number, $quantity, $cost_price, $selling_price, $expiry_date, $id]);
            }
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'delete_inventory') {
        $id = $_POST['batch_id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM inventory WHERE batch_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete: Batch is linked to sales']);
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
    <title>Inventory Management</title>
    <style>
        :root[data-theme="light"] {
            --bg-main: #f4f7f6;
            --bg-sidebar: #2c3e50;
            --bg-card: #ffffff;
            --text-main: #333333;
            --text-sidebar: #ecf0f1;
            --accent: #3498db;
            --danger: #e74c3c;
            --warning: #f1c40f;
            --border: #e0e0e0;
            --modal-bg: rgba(0,0,0,0.5);
        }
        :root[data-theme="dark"] {
            --bg-main: #1a1a2e;
            --bg-sidebar: #16213e;
            --bg-card: #0f3460;
            --text-main: #e0e0e0;
            --text-sidebar: #e0e0e0;
            --accent: #e94560;
            --danger: #ff4757;
            --warning: #ffa502;
            --border: #2a2a4a;
            --modal-bg: rgba(0,0,0,0.7);
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
        .btn-danger { background: var(--danger); }
        .content-area { padding: 30px; }
        .action-bar { display: flex; justify-content: space-between; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: var(--bg-card); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: rgba(0,0,0,0.05); font-weight: bold; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--modal-bg); justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-card); padding: 30px; border-radius: 8px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 1.2rem; font-weight: bold; }
        .close-btn { cursor: pointer; font-size: 1.5rem; line-height: 1; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; background: transparent; color: var(--text-main); font-family: inherit; }
        .form-group select option { background: var(--bg-card); color: var(--text-main); }
        #message { margin-top: 10px; text-align: center; font-weight: bold; }
        .grid-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .low-stock { color: var(--warning); font-weight: bold; }
        .expired { color: var(--danger); font-weight: bold; }
        [dir="rtl"] .nav-links li:hover, [dir="rtl"] .nav-links li.active { border-left: none; border-right: 4px solid var(--accent); }
        [dir="rtl"] th, [dir="rtl"] td { text-align: right; }
        [dir="rtl"] .controls button, [dir="rtl"] .btn { margin-left: 0; margin-right: 10px; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header" id="brandName">PharmaSys</div>
        <ul class="nav-links">
            <li id="navDash" onclick="window.location.href='dashboard.php'">Dashboard</li>
            <li id="navMed" onclick="window.location.href='medicines.php'">Medicines</li>
            <li id="navInv" class="active">Inventory</li>
            <li id="navPat" onclick="window.location.href='patients.php'">Patients</li>
            <li id="navPres">Prescriptions</li>
            <li id="navSales">Sales</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Inventory Management</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
            </div>
        </header>

        <section class="content-area">
            <div class="action-bar">
                <button class="btn" id="addInvBtn">Add New Batch</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th id="thMed">Medicine</th>
                        <th id="thBatch">Batch Number</th>
                        <th id="thQty">Quantity</th>
                        <th id="thPrice">Selling Price</th>
                        <th id="thExp">Expiry Date</th>
                        <th id="thAct">Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryBody">
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="invModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Batch</span>
                <span class="close-btn" id="closeModal">&times;</span>
            </div>
            <form id="invForm">
                <input type="hidden" name="action" value="save_inventory">
                <input type="hidden" name="batch_id" id="batch_id">
                
                <div class="form-group">
                    <label for="medicine_id" id="lblMed">Select Medicine</label>
                    <select name="medicine_id" id="medicine_id" required>
                    </select>
                </div>
                
                <div class="grid-row">
                    <div class="form-group">
                        <label for="batch_number" id="lblBatch">Batch Number</label>
                        <input type="text" name="batch_number" id="batch_number" required>
                    </div>
                    <div class="form-group">
                        <label for="quantity" id="lblQty">Quantity</label>
                        <input type="number" name="quantity" id="quantity" min="0" required>
                    </div>
                </div>
                
                <div class="grid-row">
                    <div class="form-group">
                        <label for="cost_price" id="lblCost">Cost Price</label>
                        <input type="number" step="0.01" name="cost_price" id="cost_price" required>
                    </div>
                    <div class="form-group">
                        <label for="selling_price" id="lblSell">Selling Price</label>
                        <input type="number" step="0.01" name="selling_price" id="selling_price" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="expiry_date" id="lblExp">Expiry Date</label>
                    <input type="date" name="expiry_date" id="expiry_date" required>
                </div>
                
                <button type="submit" class="btn" id="saveBtn" style="width: 100%; margin: 0;">Save</button>
                <div id="message"></div>
            </form>
        </div>
    </div>

    <script>
        const translations = {
            en: {
                brand: "PharmaSys", navDash: "Dashboard", navMed: "Medicines", navInv: "Inventory",
                navPat: "Patients", navPres: "Prescriptions", navSales: "Sales", pageTitle: "Inventory Management",
                addBtn: "Add New Batch", thMed: "Medicine", thBatch: "Batch Number", thQty: "Quantity",
                thPrice: "Selling Price", thExp: "Expiry Date", thAct: "Actions", modalAdd: "Add Batch",
                modalEdit: "Edit Batch", lblMed: "Select Medicine", lblBatch: "Batch Number", lblQty: "Quantity",
                lblCost: "Cost Price", lblSell: "Selling Price", lblExp: "Expiry Date",
                saveBtn: "Save", btnEdit: "Edit", btnDel: "Delete", toggleLang: "KU"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navInv: "کۆگا",
                navPat: "نەخۆشەکان", navPres: "ڕەچەتەکان", navSales: "فرۆشتن", pageTitle: "بەڕێوەبردنی کۆگا",
                addBtn: "زیادکردنی وەجبەی نوێ", thMed: "دەرمان", thBatch: "ژمارەی وەجبە", thQty: "بڕ",
                thPrice: "نرخی فرۆشتن", thExp: "بەرواری بەسەرچوون", thAct: "کردارەکان", modalAdd: "زیادکردنی وەجبە",
                modalEdit: "دەستکاری وەجبە", lblMed: "دەرمان هەڵبژێرە", lblBatch: "ژمارەی وەجبە", lblQty: "بڕ",
                lblCost: "نرخی کڕین", lblSell: "نرخی فرۆشتن", lblExp: "بەرواری بەسەرچوون",
                saveBtn: "پاشەکەوتکردن", btnEdit: "دەستکاری", btnDel: "سڕینەوە", toggleLang: "EN"
            }
        };

        let currentLang = document.documentElement.lang || 'en';
        
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
            document.getElementById('addInvBtn').innerText = t.addBtn;
            document.getElementById('thMed').innerText = t.thMed;
            document.getElementById('thBatch').innerText = t.thBatch;
            document.getElementById('thQty').innerText = t.thQty;
            document.getElementById('thPrice').innerText = t.thPrice;
            document.getElementById('thExp').innerText = t.thExp;
            document.getElementById('thAct').innerText = t.thAct;
            
            if(document.getElementById('batch_id').value === "") {
                document.getElementById('modalTitle').innerText = t.modalAdd;
            } else {
                document.getElementById('modalTitle').innerText = t.modalEdit;
            }
            
            document.getElementById('lblMed').innerText = t.lblMed;
            document.getElementById('lblBatch').innerText = t.lblBatch;
            document.getElementById('lblQty').innerText = t.lblQty;
            document.getElementById('lblCost').innerText = t.lblCost;
            document.getElementById('lblSell').innerText = t.lblSell;
            document.getElementById('lblExp').innerText = t.lblExp;
            document.getElementById('saveBtn').innerText = t.saveBtn;
            document.getElementById('langToggle').innerText = t.toggleLang;
            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
            loadInventory();
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

        const modal = document.getElementById('invModal');
        const invForm = document.getElementById('invForm');

        document.getElementById('addInvBtn').addEventListener('click', () => {
            invForm.reset();
            document.getElementById('batch_id').value = '';
            document.getElementById('modalTitle').innerText = translations[currentLang].modalAdd;
            document.getElementById('message').innerText = '';
            modal.classList.add('active');
        });

        document.getElementById('closeModal').addEventListener('click', () => {
            modal.classList.remove('active');
        });

        window.onclick = function(event) {
            if (event.target == modal) modal.classList.remove('active');
        }

        function loadMedicinesList() {
            const formData = new FormData();
            formData.append('action', 'get_medicines_list');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const select = document.getElementById('medicine_id');
                select.innerHTML = '<option value="">-- Select --</option>';
                if(data.status === 'success' && data.data) {
                    data.data.forEach(med => {
                        select.innerHTML += `<option value="${med.medicine_id}">${med.name}</option>`;
                    });
                }
            });
        }

        function loadInventory() {
            const formData = new FormData();
            formData.append('action', 'get_inventory');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('inventoryBody');
                tbody.innerHTML = '';
                if(data.status === 'success' && data.data) {
                    const today = new Date();
                    data.data.forEach(inv => {
                        const expDate = new Date(inv.expiry_date);
                        let expClass = '';
                        if(expDate < today) expClass = 'expired';
                        
                        let qtyClass = '';
                        if(inv.quantity <= 10) qtyClass = 'low-stock';

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${inv.batch_id}</td>
                            <td>${inv.medicine_name}</td>
                            <td>${inv.batch_number}</td>
                            <td class="${qtyClass}">${inv.quantity}</td>
                            <td>$${inv.selling_price}</td>
                            <td class="${expClass}">${inv.expiry_date}</td>
                            <td>
                                <button class="btn" onclick="editInv(${inv.batch_id}, ${inv.medicine_id}, '${inv.batch_number}', ${inv.quantity}, ${inv.cost_price}, ${inv.selling_price}, '${inv.expiry_date}')">${translations[currentLang].btnEdit}</button>
                                <button class="btn btn-danger" onclick="deleteInv(${inv.batch_id})">${translations[currentLang].btnDel}</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }

        window.editInv = function(id, med_id, batch, qty, cost, sell, exp) {
            document.getElementById('batch_id').value = id;
            document.getElementById('medicine_id').value = med_id;
            document.getElementById('batch_number').value = batch;
            document.getElementById('quantity').value = qty;
            document.getElementById('cost_price').value = cost;
            document.getElementById('selling_price').value = sell;
            document.getElementById('expiry_date').value = exp;
            document.getElementById('modalTitle').innerText = translations[currentLang].modalEdit;
            document.getElementById('message').innerText = '';
            modal.classList.add('active');
        }

        window.deleteInv = function(id) {
            if(confirm('Are you sure?')) {
                const formData = new FormData();
                formData.append('action', 'delete_inventory');
                formData.append('batch_id', id);

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        loadInventory();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        invForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const msgDiv = document.getElementById('message');
            const formData = new FormData(this);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    modal.classList.remove('active');
                    loadInventory();
                } else {
                    msgDiv.style.color = 'var(--danger)';
                    msgDiv.innerText = data.message;
                }
            })
            .catch(() => {
                msgDiv.style.color = 'var(--danger)';
                msgDiv.innerText = 'Network Error';
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            loadMedicinesList();
            applyTranslations();
            const initialTheme = document.documentElement.getAttribute('data-theme');
            document.getElementById('themeToggle').innerText = initialTheme === 'light' ? '🌙' : '☀️';
        });
    </script>
</body>
</html>
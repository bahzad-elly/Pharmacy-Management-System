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

    if ($action === 'get_suppliers') {
        try {
            $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'save_supplier') {
        $id = $_POST['supplier_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $terms = trim($_POST['terms'] ?? '');

        if (empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'Supplier name is required']);
            exit;
        }

        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, terms) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $contact_person, $phone, $email, $terms]);
            } else {
                $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, terms = ? WHERE supplier_id = ?");
                $stmt->execute([$name, $contact_person, $phone, $email, $terms, $id]);
            }
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'delete_supplier') {
        $id = $_POST['supplier_id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete: Supplier is linked to inventory or purchase orders']);
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
    <title>Suppliers Management</title>
    <style>
        :root[data-theme="light"] {
            --bg-main: #f4f7f6; --bg-sidebar: #2c3e50; --bg-card: #ffffff;
            --text-main: #333333; --text-sidebar: #ecf0f1; --accent: #3498db;
            --danger: #e74c3c; --border: #e0e0e0; --modal-bg: rgba(0,0,0,0.5);
        }
        :root[data-theme="dark"] {
            --bg-main: #1a1a2e; --bg-sidebar: #16213e; --bg-card: #0f3460;
            --text-main: #e0e0e0; --text-sidebar: #e0e0e0; --accent: #e94560;
            --danger: #ff4757; --border: #2a2a4a; --modal-bg: rgba(0,0,0,0.7);
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
        .modal-content { background: var(--bg-card); padding: 30px; border-radius: 8px; width: 100%; max-width: 500px; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 1.2rem; font-weight: bold; }
        .close-btn { cursor: pointer; font-size: 1.5rem; line-height: 1; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; background: transparent; color: var(--text-main); font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        #message { margin-top: 10px; text-align: center; font-weight: bold; }

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
            <li id="navInv" onclick="window.location.href='inventory.php'">Inventory</li>
            <li id="navPat" onclick="window.location.href='patients.php'">Patients</li>
            <li id="navPres" onclick="window.location.href='prescriptions.php'">Prescriptions</li>
            <li id="navSales" onclick="window.location.href='sales.php'">Sales</li>
            <li id="navPO" onclick="window.location.href='purchase_orders.php'">Purchase Orders</li>
            <li id="navSup" class="active">Suppliers</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Suppliers Management</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
            </div>
        </header>

        <section class="content-area">
            <div class="action-bar">
                <button class="btn" id="addSupBtn">Add New Supplier</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th id="thName">Company Name</th>
                        <th id="thContact">Contact Person</th>
                        <th id="thPhone">Phone</th>
                        <th id="thEmail">Email</th>
                        <th id="thAct">Actions</th>
                    </tr>
                </thead>
                <tbody id="suppliersBody">
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="supModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Supplier</span>
                <span class="close-btn" id="closeModal">&times;</span>
            </div>
            <form id="supForm">
                <input type="hidden" name="action" value="save_supplier">
                <input type="hidden" name="supplier_id" id="supplier_id">
                
                <div class="form-group">
                    <label for="name" id="lblName">Company Name</label>
                    <input type="text" name="name" id="name" required>
                </div>
                <div class="form-group">
                    <label for="contact_person" id="lblContact">Contact Person</label>
                    <input type="text" name="contact_person" id="contact_person">
                </div>
                <div class="form-group">
                    <label for="phone" id="lblPhone">Phone</label>
                    <input type="text" name="phone" id="phone">
                </div>
                <div class="form-group">
                    <label for="email" id="lblEmail">Email</label>
                    <input type="email" name="email" id="email">
                </div>
                <div class="form-group">
                    <label for="terms" id="lblTerms">Terms / Notes</label>
                    <textarea name="terms" id="terms"></textarea>
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
                navPat: "Patients", navPres: "Prescriptions", navSales: "Sales", navPO: "Purchase Orders",
                navSup: "Suppliers", pageTitle: "Suppliers Management", addBtn: "Add New Supplier",
                thName: "Company Name", thContact: "Contact Person", thPhone: "Phone", thEmail: "Email",
                thAct: "Actions", modalAdd: "Add Supplier", modalEdit: "Edit Supplier",
                lblName: "Company Name", lblContact: "Contact Person", lblPhone: "Phone", lblEmail: "Email",
                lblTerms: "Terms / Notes", saveBtn: "Save", btnEdit: "Edit", btnDel: "Delete", toggleLang: "KU"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navInv: "کۆگا",
                navPat: "نەخۆشەکان", navPres: "ڕەچەتەکان", navSales: "فرۆشتن", navPO: "داواکارییەکانی کڕین",
                navSup: "دابینکەرەکان", pageTitle: "بەڕێوەبردنی دابینکەرەکان", addBtn: "زیادکردنی دابینکەری نوێ",
                thName: "ناوی کۆمپانیا", thContact: "کەسی پەیوەندیدار", thPhone: "تەلەفۆن", thEmail: "ئیمەیڵ",
                thAct: "کردارەکان", modalAdd: "زیادکردنی دابینکەر", modalEdit: "دەستکاری دابینکەر",
                lblName: "ناوی کۆمپانیا", lblContact: "کەسی پەیوەندیدار", lblPhone: "تەلەفۆن", lblEmail: "ئیمەیڵ",
                lblTerms: "مەرجەکان / تێبینی", saveBtn: "پاشەکەوتکردن", btnEdit: "دەستکاری", btnDel: "سڕینەوە", toggleLang: "EN"
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
            document.getElementById('navPO').innerText = t.navPO;
            document.getElementById('navSup').innerText = t.navSup;
            document.getElementById('pageTitle').innerText = t.pageTitle;
            document.getElementById('addSupBtn').innerText = t.addBtn;
            document.getElementById('thName').innerText = t.thName;
            document.getElementById('thContact').innerText = t.thContact;
            document.getElementById('thPhone').innerText = t.thPhone;
            document.getElementById('thEmail').innerText = t.thEmail;
            document.getElementById('thAct').innerText = t.thAct;
            
            if(document.getElementById('supplier_id').value === "") {
                document.getElementById('modalTitle').innerText = t.modalAdd;
            } else {
                document.getElementById('modalTitle').innerText = t.modalEdit;
            }
            
            document.getElementById('lblName').innerText = t.lblName;
            document.getElementById('lblContact').innerText = t.lblContact;
            document.getElementById('lblPhone').innerText = t.lblPhone;
            document.getElementById('lblEmail').innerText = t.lblEmail;
            document.getElementById('lblTerms').innerText = t.lblTerms;
            document.getElementById('saveBtn').innerText = t.saveBtn;
            document.getElementById('langToggle').innerText = t.toggleLang;
            
            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
            loadSuppliers();
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

        const modal = document.getElementById('supModal');
        const supForm = document.getElementById('supForm');

        document.getElementById('addSupBtn').addEventListener('click', () => {
            supForm.reset();
            document.getElementById('supplier_id').value = '';
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

        function loadSuppliers() {
            const formData = new FormData();
            formData.append('action', 'get_suppliers');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('suppliersBody');
                tbody.innerHTML = '';
                if(data.status === 'success' && data.data) {
                    data.data.forEach(sup => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${sup.supplier_id}</td>
                            <td>${sup.name}</td>
                            <td>${sup.contact_person || '-'}</td>
                            <td>${sup.phone || '-'}</td>
                            <td>${sup.email || '-'}</td>
                            <td>
                                <button class="btn" onclick="editSup(${sup.supplier_id}, '${sup.name.replace(/'/g, "\\'")}', '${(sup.contact_person||'').replace(/'/g, "\\'")}', '${(sup.phone||'').replace(/'/g, "\\'")}', '${(sup.email||'').replace(/'/g, "\\'")}', '${(sup.terms||'').replace(/'/g, "\\'")}')">${translations[currentLang].btnEdit}</button>
                                <button class="btn btn-danger" onclick="deleteSup(${sup.supplier_id})">${translations[currentLang].btnDel}</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }

        window.editSup = function(id, name, contact, phone, email, terms) {
            document.getElementById('supplier_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('contact_person').value = contact;
            document.getElementById('phone').value = phone;
            document.getElementById('email').value = email;
            document.getElementById('terms').value = terms;
            document.getElementById('modalTitle').innerText = translations[currentLang].modalEdit;
            document.getElementById('message').innerText = '';
            modal.classList.add('active');
        }

        window.deleteSup = function(id) {
            if(confirm('Are you sure?')) {
                const formData = new FormData();
                formData.append('action', 'delete_supplier');
                formData.append('supplier_id', id);

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        loadSuppliers();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        supForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const msgDiv = document.getElementById('message');
            const formData = new FormData(this);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    modal.classList.remove('active');
                    loadSuppliers();
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
            applyTranslations();
            const initialTheme = document.documentElement.getAttribute('data-theme');
            document.getElementById('themeToggle').innerText = initialTheme === 'light' ? '🌙' : '☀️';
        });
    </script>
</body>
</html>
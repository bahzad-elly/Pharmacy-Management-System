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

    if ($action === 'get_medicines') {
        try {
            $stmt = $pdo->query("SELECT * FROM medicines ORDER BY medicine_id DESC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'save_medicine') {
        $id = $_POST['medicine_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $generic = trim($_POST['generic_name'] ?? '');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');

        if (empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'Medicine name is required']);
            exit;
        }

        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO medicines (name, generic_name, manufacturer, barcode) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $generic, $manufacturer, $barcode]);
            } else {
                $stmt = $pdo->prepare("UPDATE medicines SET name = ?, generic_name = ?, manufacturer = ?, barcode = ? WHERE medicine_id = ?");
                $stmt->execute([$name, $generic, $manufacturer, $barcode, $id]);
            }
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error or duplicate barcode']);
        }
        exit;
    }

    if ($action === 'delete_medicine') {
        $id = $_POST['medicine_id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM medicines WHERE medicine_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete: Medicine is linked to inventory or prescriptions']);
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
    <title>Medicines Management</title>
    <style>
        :root[data-theme="light"] {
            --bg-main: #f4f7f6;
            --bg-sidebar: #2c3e50;
            --bg-card: #ffffff;
            --text-main: #333333;
            --text-sidebar: #ecf0f1;
            --accent: #3498db;
            --danger: #e74c3c;
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
            --border: #2a2a4a;
            --modal-bg: rgba(0,0,0,0.7);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: var(--bg-main); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 250px; background: var(--bg-sidebar); color: var(--text-sidebar); display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; font-size: 1.5rem; font-weight: bold; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-links { list-style: none; flex: 1; padding-top: 20px; }
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
        .form-group input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; background: transparent; color: var(--text-main); }
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
            <li id="navMed" class="active">Medicines</li>
            <li id="navPat">Patients</li>
            <li id="navPres">Prescriptions</li>
            <li id="navSales">Sales</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Medicines Management</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
            </div>
        </header>

        <section class="content-area">
            <div class="action-bar">
                <button class="btn" id="addMedBtn">Add New Medicine</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th id="thName">Name</th>
                        <th id="thGen">Generic Name</th>
                        <th id="thMan">Manufacturer</th>
                        <th id="thBar">Barcode</th>
                        <th id="thAct">Actions</th>
                    </tr>
                </thead>
                <tbody id="medicinesBody">
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="medModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Medicine</span>
                <span class="close-btn" id="closeModal">&times;</span>
            </div>
            <form id="medForm">
                <input type="hidden" name="action" value="save_medicine">
                <input type="hidden" name="medicine_id" id="medicine_id">
                
                <div class="form-group">
                    <label for="name" id="lblname">Medicine Name</label>
                    <input type="text" name="name" id="name" required>
                </div>
                <div class="form-group">
                    <label for="generic_name" id="lblgen">Generic Name</label>
                    <input type="text" name="generic_name" id="generic_name">
                </div>
                <div class="form-group">
                    <label for="manufacturer" id="lblman">Manufacturer</label>
                    <input type="text" name="manufacturer" id="manufacturer">
                </div>
                <div class="form-group">
                    <label for="barcode" id="lblbar">Barcode</label>
                    <input type="text" name="barcode" id="barcode">
                </div>
                <button type="submit" class="btn" id="saveBtn" style="width: 100%; margin: 0;">Save</button>
                <div id="message"></div>
            </form>
        </div>
    </div>

    <script>
        const translations = {
            en: {
                brand: "PharmaSys", navDash: "Dashboard", navMed: "Medicines", navPat: "Patients", 
                navPres: "Prescriptions", navSales: "Sales", pageTitle: "Medicines Management",
                addBtn: "Add New Medicine", thName: "Name", thGen: "Generic Name", thMan: "Manufacturer",
                thBar: "Barcode", thAct: "Actions", modalAdd: "Add Medicine", modalEdit: "Edit Medicine",
                lblname: "Medicine Name", lblgen: "Generic Name", lblman: "Manufacturer", lblbar: "Barcode",
                saveBtn: "Save", btnEdit: "Edit", btnDel: "Delete", toggleLang: "KU"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navPat: "نەخۆشەکان", 
                navPres: "ڕەچەتەکان", navSales: "فرۆشتن", pageTitle: "بەڕێوەبردنی دەرمانەکان",
                addBtn: "زیادکردنی دەرمانی نوێ", thName: "ناو", thGen: "ناوی زانستی", thMan: "کۆمپانیای بەرهەمهێنەر",
                thBar: "بارکۆد", thAct: "کردارەکان", modalAdd: "زیادکردنی دەرمان", modalEdit: "دەستکاری دەرمان",
                lblname: "ناوی دەرمان", lblgen: "ناوی زانستی", lblman: "کۆمپانیای بەرهەمهێنەر", lblbar: "بارکۆد",
                saveBtn: "پاشەکەوتکردن", btnEdit: "دەستکاری", btnDel: "سڕینەوە", toggleLang: "EN"
            }
        };

        let currentLang = document.documentElement.lang || 'en';
        
        function applyTranslations() {
            const t = translations[currentLang];
            document.getElementById('brandName').innerText = t.brand;
            document.getElementById('navDash').innerText = t.navDash;
            document.getElementById('navMed').innerText = t.navMed;
            document.getElementById('navPat').innerText = t.navPat;
            document.getElementById('navPres').innerText = t.navPres;
            document.getElementById('navSales').innerText = t.navSales;
            document.getElementById('pageTitle').innerText = t.pageTitle;
            document.getElementById('addMedBtn').innerText = t.addBtn;
            document.getElementById('thName').innerText = t.thName;
            document.getElementById('thGen').innerText = t.thGen;
            document.getElementById('thMan').innerText = t.thMan;
            document.getElementById('thBar').innerText = t.thBar;
            document.getElementById('thAct').innerText = t.thAct;
            
            if(document.getElementById('medicine_id').value === "") {
                document.getElementById('modalTitle').innerText = t.modalAdd;
            } else {
                document.getElementById('modalTitle').innerText = t.modalEdit;
            }
            
            document.getElementById('lblname').innerText = t.lblname;
            document.getElementById('lblgen').innerText = t.lblgen;
            document.getElementById('lblman').innerText = t.lblman;
            document.getElementById('lblbar').innerText = t.lblbar;
            document.getElementById('saveBtn').innerText = t.saveBtn;
            document.getElementById('langToggle').innerText = t.toggleLang;
            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
            loadMedicines();
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

        const modal = document.getElementById('medModal');
        const medForm = document.getElementById('medForm');

        document.getElementById('addMedBtn').addEventListener('click', () => {
            medForm.reset();
            document.getElementById('medicine_id').value = '';
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

        function loadMedicines() {
            const formData = new FormData();
            formData.append('action', 'get_medicines');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('medicinesBody');
                tbody.innerHTML = '';
                if(data.status === 'success' && data.data) {
                    data.data.forEach(med => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${med.medicine_id}</td>
                            <td>${med.name}</td>
                            <td>${med.generic_name || '-'}</td>
                            <td>${med.manufacturer || '-'}</td>
                            <td>${med.barcode || '-'}</td>
                            <td>
                                <button class="btn" onclick="editMed(${med.medicine_id}, '${med.name.replace(/'/g, "\\'")}', '${(med.generic_name||'').replace(/'/g, "\\'")}', '${(med.manufacturer||'').replace(/'/g, "\\'")}', '${(med.barcode||'').replace(/'/g, "\\'")}')">${translations[currentLang].btnEdit}</button>
                                <button class="btn btn-danger" onclick="deleteMed(${med.medicine_id})">${translations[currentLang].btnDel}</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }

        window.editMed = function(id, name, generic, manufacturer, barcode) {
            document.getElementById('medicine_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('generic_name').value = generic;
            document.getElementById('manufacturer').value = manufacturer;
            document.getElementById('barcode').value = barcode;
            document.getElementById('modalTitle').innerText = translations[currentLang].modalEdit;
            document.getElementById('message').innerText = '';
            modal.classList.add('active');
        }

        window.deleteMed = function(id) {
            if(confirm('Are you sure?')) {
                const formData = new FormData();
                formData.append('action', 'delete_medicine');
                formData.append('medicine_id', id);

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        loadMedicines();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        medForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const msgDiv = document.getElementById('message');
            const formData = new FormData(this);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    modal.classList.remove('active');
                    loadMedicines();
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
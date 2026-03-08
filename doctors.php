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

    if ($action === 'get_doctors') {
        try {
            $stmt = $pdo->query("SELECT * FROM doctors ORDER BY name ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'save_doctor') {
        $id = $_POST['doctor_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $clinic_name = trim($_POST['clinic_name'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');

        if (empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'Doctor name is required']);
            exit;
        }

        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO doctors (name, clinic_name, contact_number, specialization) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $clinic_name, $contact_number, $specialization]);
            } else {
                $stmt = $pdo->prepare("UPDATE doctors SET name = ?, clinic_name = ?, contact_number = ?, specialization = ? WHERE doctor_id = ?");
                $stmt->execute([$name, $clinic_name, $contact_number, $specialization, $id]);
            }
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'delete_doctor') {
        $id = $_POST['doctor_id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM doctors WHERE doctor_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete: Doctor is linked to prescriptions']);
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
    <title>Doctors Management</title>
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
        .form-group input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; background: transparent; color: var(--text-main); font-family: inherit; }
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
            <li id="navDoc" class="active">Doctors</li>
            <li id="navPres" onclick="window.location.href='prescriptions.php'">Prescriptions</li>
            <li id="navSales" onclick="window.location.href='sales.php'">Sales</li>
            <li id="navPO" onclick="window.location.href='purchase_orders.php'">Purchase Orders</li>
            <li id="navSup" onclick="window.location.href='suppliers.php'">Suppliers</li>
            <li id="navUsers" onclick="window.location.href='users.php'">Users & Staff</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Doctors & Clinics</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
            </div>
        </header>

        <section class="content-area">
            <div class="action-bar">
                <button class="btn" id="addDocBtn">Add New Doctor</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th id="thName">Doctor Name</th>
                        <th id="thClinic">Clinic Name</th>
                        <th id="thSpec">Specialization</th>
                        <th id="thContact">Contact</th>
                        <th id="thAct">Actions</th>
                    </tr>
                </thead>
                <tbody id="doctorsBody">
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="docModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Doctor</span>
                <span class="close-btn" id="closeModal">&times;</span>
            </div>
            <form id="docForm">
                <input type="hidden" name="action" value="save_doctor">
                <input type="hidden" name="doctor_id" id="doctor_id">
                
                <div class="form-group">
                    <label for="name" id="lblName">Doctor Name (e.g., Dr. John Doe)</label>
                    <input type="text" name="name" id="name" required>
                </div>
                <div class="form-group">
                    <label for="clinic_name" id="lblClinic">Clinic Name</label>
                    <input type="text" name="clinic_name" id="clinic_name">
                </div>
                <div class="form-group">
                    <label for="specialization" id="lblSpec">Specialization</label>
                    <input type="text" name="specialization" id="specialization">
                </div>
                <div class="form-group">
                    <label for="contact_number" id="lblContact">Contact Number</label>
                    <input type="text" name="contact_number" id="contact_number">
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
                navPat: "Patients", navDoc: "Doctors", navPres: "Prescriptions", navSales: "Sales", 
                navPO: "Purchase Orders", navSup: "Suppliers", navUsers: "Users & Staff",
                pageTitle: "Doctors & Clinics", addBtn: "Add New Doctor",
                thName: "Doctor Name", thClinic: "Clinic Name", thSpec: "Specialization", thContact: "Contact",
                thAct: "Actions", modalAdd: "Add Doctor", modalEdit: "Edit Doctor",
                lblName: "Doctor Name (e.g., Dr. John Doe)", lblClinic: "Clinic Name", lblSpec: "Specialization",
                lblContact: "Contact Number", saveBtn: "Save", btnEdit: "Edit", btnDel: "Delete", toggleLang: "KU"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navInv: "کۆگا",
                navPat: "نەخۆشەکان", navDoc: "پزیشکەکان", navPres: "ڕەچەتەکان", navSales: "فرۆشتن", 
                navPO: "داواکارییەکانی کڕین", navSup: "دابینکەرەکان", navUsers: "بەکارهێنەران و ستاف",
                pageTitle: "پزیشکەکان و نۆرینگەکان", addBtn: "زیادکردنی پزیشکی نوێ",
                thName: "ناوی پزیشک", thClinic: "ناوی نۆرینگە", thSpec: "پسپۆڕی", thContact: "پەیوەندی",
                thAct: "کردارەکان", modalAdd: "زیادکردنی پزیشک", modalEdit: "دەستکاری پزیشک",
                lblName: "ناوی پزیشک (نموونە: د. ئەحمەد عەلی)", lblClinic: "ناوی نۆرینگە", lblSpec: "پسپۆڕی",
                lblContact: "ژمارەی پەیوەندی", saveBtn: "پاشەکەوتکردن", btnEdit: "دەستکاری", btnDel: "سڕینەوە", toggleLang: "EN"
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
            document.getElementById('navDoc').innerText = t.navDoc;
            document.getElementById('navPres').innerText = t.navPres;
            document.getElementById('navSales').innerText = t.navSales;
            document.getElementById('navPO').innerText = t.navPO;
            document.getElementById('navSup').innerText = t.navSup;
            document.getElementById('navUsers').innerText = t.navUsers;
            document.getElementById('pageTitle').innerText = t.pageTitle;
            document.getElementById('addDocBtn').innerText = t.addBtn;
            document.getElementById('thName').innerText = t.thName;
            document.getElementById('thClinic').innerText = t.thClinic;
            document.getElementById('thSpec').innerText = t.thSpec;
            document.getElementById('thContact').innerText = t.thContact;
            document.getElementById('thAct').innerText = t.thAct;
            
            if(document.getElementById('doctor_id').value === "") {
                document.getElementById('modalTitle').innerText = t.modalAdd;
            } else {
                document.getElementById('modalTitle').innerText = t.modalEdit;
            }
            
            document.getElementById('lblName').innerText = t.lblName;
            document.getElementById('lblClinic').innerText = t.lblClinic;
            document.getElementById('lblSpec').innerText = t.lblSpec;
            document.getElementById('lblContact').innerText = t.lblContact;
            document.getElementById('saveBtn').innerText = t.saveBtn;
            document.getElementById('langToggle').innerText = t.toggleLang;
            
            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
            loadDoctors();
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

        const modal = document.getElementById('docModal');
        const docForm = document.getElementById('docForm');

        document.getElementById('addDocBtn').addEventListener('click', () => {
            docForm.reset();
            document.getElementById('doctor_id').value = '';
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

        function loadDoctors() {
            const formData = new FormData();
            formData.append('action', 'get_doctors');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('doctorsBody');
                tbody.innerHTML = '';
                if(data.status === 'success' && data.data) {
                    data.data.forEach(doc => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${doc.doctor_id}</td>
                            <td>${doc.name}</td>
                            <td>${doc.clinic_name || '-'}</td>
                            <td>${doc.specialization || '-'}</td>
                            <td>${doc.contact_number || '-'}</td>
                            <td>
                                <button class="btn" onclick="editDoc(${doc.doctor_id}, '${doc.name.replace(/'/g, "\\'")}', '${(doc.clinic_name||'').replace(/'/g, "\\'")}', '${(doc.specialization||'').replace(/'/g, "\\'")}', '${(doc.contact_number||'').replace(/'/g, "\\'")}')">${translations[currentLang].btnEdit}</button>
                                <button class="btn btn-danger" onclick="deleteDoc(${doc.doctor_id})">${translations[currentLang].btnDel}</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }

        window.editDoc = function(id, name, clinic, spec, contact) {
            document.getElementById('doctor_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('clinic_name').value = clinic;
            document.getElementById('specialization').value = spec;
            document.getElementById('contact_number').value = contact;
            document.getElementById('modalTitle').innerText = translations[currentLang].modalEdit;
            document.getElementById('message').innerText = '';
            modal.classList.add('active');
        }

        window.deleteDoc = function(id) {
            if(confirm('Are you sure?')) {
                const formData = new FormData();
                formData.append('action', 'delete_doctor');
                formData.append('doctor_id', id);

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        loadDoctors();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        docForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const msgDiv = document.getElementById('message');
            const formData = new FormData(this);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    modal.classList.remove('active');
                    loadDoctors();
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
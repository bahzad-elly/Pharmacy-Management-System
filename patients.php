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

    if ($action === 'get_patients') {
        try {
            $stmt = $pdo->query("SELECT * FROM patients ORDER BY patient_id DESC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'save_patient') {
        $id = $_POST['patient_id'] ?? '';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $allergies = trim($_POST['allergies'] ?? '');
        $history = trim($_POST['medical_history'] ?? '');

        if (empty($first_name) || empty($last_name)) {
            echo json_encode(['status' => 'error', 'message' => 'First and Last name are required']);
            exit;
        }

        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO patients (first_name, last_name, phone, address, allergies, medical_history) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$first_name, $last_name, $phone, $address, $allergies, $history]);
            } else {
                $stmt = $pdo->prepare("UPDATE patients SET first_name = ?, last_name = ?, phone = ?, address = ?, allergies = ?, medical_history = ? WHERE patient_id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $address, $allergies, $history, $id]);
            }
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'delete_patient') {
        $id = $_POST['patient_id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete: Patient has linked prescriptions or sales']);
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
    <title>Patients Management</title>
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
        .modal-content { background: var(--bg-card); padding: 30px; border-radius: 8px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 1.2rem; font-weight: bold; }
        .close-btn { cursor: pointer; font-size: 1.5rem; line-height: 1; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; background: transparent; color: var(--text-main); font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        #message { margin-top: 10px; text-align: center; font-weight: bold; }
        .name-row { display: flex; gap: 15px; }
        .name-row .form-group { flex: 1; }
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
            <li id="navPat" class="active">Patients</li>
            <li id="navPres">Prescriptions</li>
            <li id="navSales">Sales</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Patients Management</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
            </div>
        </header>

        <section class="content-area">
            <div class="action-bar">
                <button class="btn" id="addPatBtn">Add New Patient</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th id="thName">Full Name</th>
                        <th id="thPhone">Phone</th>
                        <th id="thAddress">Address</th>
                        <th id="thAct">Actions</th>
                    </tr>
                </thead>
                <tbody id="patientsBody">
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="patModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Patient</span>
                <span class="close-btn" id="closeModal">&times;</span>
            </div>
            <form id="patForm">
                <input type="hidden" name="action" value="save_patient">
                <input type="hidden" name="patient_id" id="patient_id">
                
                <div class="name-row">
                    <div class="form-group">
                        <label for="first_name" id="lblFirst">First Name</label>
                        <input type="text" name="first_name" id="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name" id="lblLast">Last Name</label>
                        <input type="text" name="last_name" id="last_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone" id="lblPhone">Phone</label>
                    <input type="text" name="phone" id="phone">
                </div>
                <div class="form-group">
                    <label for="address" id="lblAddress">Address</label>
                    <textarea name="address" id="address"></textarea>
                </div>
                <div class="form-group">
                    <label for="allergies" id="lblAllergies">Allergies</label>
                    <textarea name="allergies" id="allergies"></textarea>
                </div>
                <div class="form-group">
                    <label for="medical_history" id="lblHistory">Medical History</label>
                    <textarea name="medical_history" id="medical_history"></textarea>
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
                navPres: "Prescriptions", navSales: "Sales", pageTitle: "Patients Management",
                addBtn: "Add New Patient", thName: "Full Name", thPhone: "Phone", thAddress: "Address",
                thAct: "Actions", modalAdd: "Add Patient", modalEdit: "Edit Patient",
                lblFirst: "First Name", lblLast: "Last Name", lblPhone: "Phone", lblAddress: "Address",
                lblAllergies: "Allergies", lblHistory: "Medical History",
                saveBtn: "Save", btnEdit: "Edit", btnDel: "Delete", toggleLang: "KU"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navPat: "نەخۆشەکان", 
                navPres: "ڕەچەتەکان", navSales: "فرۆشتن", pageTitle: "بەڕێوەبردنی نەخۆشەکان",
                addBtn: "زیادکردنی نەخۆشی نوێ", thName: "ناوی تەواو", thPhone: "تەلەفۆن", thAddress: "ناونیشان",
                thAct: "کردارەکان", modalAdd: "زیادکردنی نەخۆش", modalEdit: "دەستکاری نەخۆش",
                lblFirst: "ناوی یەکەم", lblLast: "ناوی کۆتایی", lblPhone: "تەلەفۆن", lblAddress: "ناونیشان",
                lblAllergies: "هەستیارییەکان", lblHistory: "پێشینەی پزیشکی",
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
            document.getElementById('addPatBtn').innerText = t.addBtn;
            document.getElementById('thName').innerText = t.thName;
            document.getElementById('thPhone').innerText = t.thPhone;
            document.getElementById('thAddress').innerText = t.thAddress;
            document.getElementById('thAct').innerText = t.thAct;
            
            if(document.getElementById('patient_id').value === "") {
                document.getElementById('modalTitle').innerText = t.modalAdd;
            } else {
                document.getElementById('modalTitle').innerText = t.modalEdit;
            }
            
            document.getElementById('lblFirst').innerText = t.lblFirst;
            document.getElementById('lblLast').innerText = t.lblLast;
            document.getElementById('lblPhone').innerText = t.lblPhone;
            document.getElementById('lblAddress').innerText = t.lblAddress;
            document.getElementById('lblAllergies').innerText = t.lblAllergies;
            document.getElementById('lblHistory').innerText = t.lblHistory;
            document.getElementById('saveBtn').innerText = t.saveBtn;
            document.getElementById('langToggle').innerText = t.toggleLang;
            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
            loadPatients();
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

        const modal = document.getElementById('patModal');
        const patForm = document.getElementById('patForm');

        document.getElementById('addPatBtn').addEventListener('click', () => {
            patForm.reset();
            document.getElementById('patient_id').value = '';
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

        function loadPatients() {
            const formData = new FormData();
            formData.append('action', 'get_patients');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('patientsBody');
                tbody.innerHTML = '';
                if(data.status === 'success' && data.data) {
                    data.data.forEach(pat => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${pat.patient_id}</td>
                            <td>${pat.first_name} ${pat.last_name}</td>
                            <td>${pat.phone || '-'}</td>
                            <td>${pat.address ? pat.address.substring(0, 30) + '...' : '-'}</td>
                            <td>
                                <button class="btn" onclick="editPat(${pat.patient_id}, '${pat.first_name.replace(/'/g, "\\'")}', '${pat.last_name.replace(/'/g, "\\'")}', '${(pat.phone||'').replace(/'/g, "\\'")}', '${(pat.address||'').replace(/'/g, "\\'")}', '${(pat.allergies||'').replace(/'/g, "\\'")}', '${(pat.medical_history||'').replace(/'/g, "\\'")}')">${translations[currentLang].btnEdit}</button>
                                <button class="btn btn-danger" onclick="deletePat(${pat.patient_id})">${translations[currentLang].btnDel}</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }

        window.editPat = function(id, first, last, phone, address, allergies, history) {
            document.getElementById('patient_id').value = id;
            document.getElementById('first_name').value = first;
            document.getElementById('last_name').value = last;
            document.getElementById('phone').value = phone;
            document.getElementById('address').value = address;
            document.getElementById('allergies').value = allergies;
            document.getElementById('medical_history').value = history;
            document.getElementById('modalTitle').innerText = translations[currentLang].modalEdit;
            document.getElementById('message').innerText = '';
            modal.classList.add('active');
        }

        window.deletePat = function(id) {
            if(confirm('Are you sure?')) {
                const formData = new FormData();
                formData.append('action', 'delete_patient');
                formData.append('patient_id', id);

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        loadPatients();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        patForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const msgDiv = document.getElementById('message');
            const formData = new FormData(this);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    modal.classList.remove('active');
                    loadPatients();
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
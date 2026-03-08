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

    if ($action === 'get_prescriptions') {
        try {
            $stmt = $pdo->query("
                SELECT p.*, CONCAT(pat.first_name, ' ', pat.last_name) AS patient_name, d.name AS doctor_name 
                FROM prescriptions p 
                JOIN patients pat ON p.patient_id = pat.patient_id 
                LEFT JOIN doctors d ON p.doctor_id = d.doctor_id 
                ORDER BY p.prescription_date DESC
            ");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'get_form_data') {
        try {
            $patients = $pdo->query("SELECT patient_id, CONCAT(first_name, ' ', last_name) AS name FROM patients ORDER BY first_name ASC")->fetchAll();
            $doctors = $pdo->query("SELECT doctor_id, name FROM doctors ORDER BY name ASC")->fetchAll();
            echo json_encode(['status' => 'success', 'patients' => $patients, 'doctors' => $doctors]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'save_prescription') {
        $id = $_POST['prescription_id'] ?? '';
        $patient_id = $_POST['patient_id'] ?? '';
        $doctor_id = !empty($_POST['doctor_id']) ? $_POST['doctor_id'] : null;
        $prescription_date = $_POST['prescription_date'] ?? '';
        $status = $_POST['status'] ?? 'pending';
        $notes = trim($_POST['notes'] ?? '');
        $user_id = $_SESSION['user_id'];

        if (empty($patient_id) || empty($prescription_date)) {
            echo json_encode(['status' => 'error', 'message' => 'Patient and Date are required']);
            exit;
        }

        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO prescriptions (patient_id, doctor_id, user_id, prescription_date, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$patient_id, $doctor_id, $user_id, $prescription_date, $status, $notes]);
            } else {
                $stmt = $pdo->prepare("UPDATE prescriptions SET patient_id = ?, doctor_id = ?, prescription_date = ?, status = ?, notes = ? WHERE prescription_id = ?");
                $stmt->execute([$patient_id, $doctor_id, $prescription_date, $status, $notes, $id]);
            }
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'delete_prescription') {
        $id = $_POST['prescription_id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE prescription_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete: Prescription is linked to sales']);
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
    <title>Prescriptions Management</title>
    <style>
        :root[data-theme="light"] {
            --bg-main: #f4f7f6;
            --bg-sidebar: #2c3e50;
            --bg-card: #ffffff;
            --text-main: #333333;
            --text-sidebar: #ecf0f1;
            --accent: #3498db;
            --danger: #e74c3c;
            --success: #2ecc71;
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
            --success: #2ed573;
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
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; background: transparent; color: var(--text-main); font-family: inherit; }
        .form-group select option { background: var(--bg-card); color: var(--text-main); }
        .form-group textarea { resize: vertical; min-height: 80px; }
        #message { margin-top: 10px; text-align: center; font-weight: bold; }
        .grid-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; color: white; }
        .status-pending { background: var(--warning); }
        .status-dispensed { background: var(--success); }
        .status-cancelled { background: var(--danger); }
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
            <li id="navPres" class="active">Prescriptions</li>
            <li id="navSales">Sales</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Prescriptions Management</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
            </div>
        </header>

        <section class="content-area">
            <div class="action-bar">
                <button class="btn" id="addPresBtn">Add New Prescription</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th id="thDate">Date</th>
                        <th id="thPat">Patient</th>
                        <th id="thDoc">Doctor</th>
                        <th id="thStatus">Status</th>
                        <th id="thAct">Actions</th>
                    </tr>
                </thead>
                <tbody id="prescriptionsBody">
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="presModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Prescription</span>
                <span class="close-btn" id="closeModal">&times;</span>
            </div>
            <form id="presForm">
                <input type="hidden" name="action" value="save_prescription">
                <input type="hidden" name="prescription_id" id="prescription_id">
                
                <div class="grid-row">
                    <div class="form-group">
                        <label for="patient_id" id="lblPat">Patient</label>
                        <select name="patient_id" id="patient_id" required></select>
                    </div>
                    <div class="form-group">
                        <label for="doctor_id" id="lblDoc">Doctor (Optional)</label>
                        <select name="doctor_id" id="doctor_id"></select>
                    </div>
                </div>
                
                <div class="grid-row">
                    <div class="form-group">
                        <label for="prescription_date" id="lblDate">Date</label>
                        <input type="date" name="prescription_date" id="prescription_date" required>
                    </div>
                    <div class="form-group">
                        <label for="status" id="lblStatus">Status</label>
                        <select name="status" id="status">
                            <option value="pending" id="optPending">Pending</option>
                            <option value="dispensed" id="optDispensed">Dispensed</option>
                            <option value="cancelled" id="optCancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes" id="lblNotes">Notes</label>
                    <textarea name="notes" id="notes"></textarea>
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
                navPat: "Patients", navPres: "Prescriptions", navSales: "Sales", pageTitle: "Prescriptions Management",
                addBtn: "Add New Prescription", thDate: "Date", thPat: "Patient", thDoc: "Doctor",
                thStatus: "Status", thAct: "Actions", modalAdd: "Add Prescription", modalEdit: "Edit Prescription",
                lblPat: "Patient", lblDoc: "Doctor (Optional)", lblDate: "Date", lblStatus: "Status",
                lblNotes: "Notes", optPending: "Pending", optDispensed: "Dispensed", optCancelled: "Cancelled",
                saveBtn: "Save", btnEdit: "Edit", btnDel: "Delete", toggleLang: "KU"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navInv: "کۆگا",
                navPat: "نەخۆشەکان", navPres: "ڕەچەتەکان", navSales: "فرۆشتن", pageTitle: "بەڕێوەبردنی ڕەچەتەکان",
                addBtn: "زیادکردنی ڕەچەتەی نوێ", thDate: "بەروار", thPat: "نەخۆش", thDoc: "پزیشک",
                thStatus: "دۆخ", thAct: "کردارەکان", modalAdd: "زیادکردنی ڕەچەتە", modalEdit: "دەستکاری ڕەچەتە",
                lblPat: "نەخۆش", lblDoc: "پزیشک (ئارەزوومەندانە)", lblDate: "بەروار", lblStatus: "دۆخ",
                lblNotes: "تێبینییەکان", optPending: "هەڵپەسێردراو", optDispensed: "دراوە", optCancelled: "هەڵوەشاوەتەوە",
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
            document.getElementById('addPresBtn').innerText = t.addBtn;
            document.getElementById('thDate').innerText = t.thDate;
            document.getElementById('thPat').innerText = t.thPat;
            document.getElementById('thDoc').innerText = t.thDoc;
            document.getElementById('thStatus').innerText = t.thStatus;
            document.getElementById('thAct').innerText = t.thAct;
            
            if(document.getElementById('prescription_id').value === "") {
                document.getElementById('modalTitle').innerText = t.modalAdd;
            } else {
                document.getElementById('modalTitle').innerText = t.modalEdit;
            }
            
            document.getElementById('lblPat').innerText = t.lblPat;
            document.getElementById('lblDoc').innerText = t.lblDoc;
            document.getElementById('lblDate').innerText = t.lblDate;
            document.getElementById('lblStatus').innerText = t.lblStatus;
            document.getElementById('lblNotes').innerText = t.lblNotes;
            document.getElementById('optPending').innerText = t.optPending;
            document.getElementById('optDispensed').innerText = t.optDispensed;
            document.getElementById('optCancelled').innerText = t.optCancelled;
            document.getElementById('saveBtn').innerText = t.saveBtn;
            document.getElementById('langToggle').innerText = t.toggleLang;
            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
            loadPrescriptions();
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

        const modal = document.getElementById('presModal');
        const presForm = document.getElementById('presForm');

        document.getElementById('addPresBtn').addEventListener('click', () => {
            presForm.reset();
            document.getElementById('prescription_id').value = '';
            document.getElementById('prescription_date').valueAsDate = new Date();
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

        function loadFormData() {
            const formData = new FormData();
            formData.append('action', 'get_form_data');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const patSelect = document.getElementById('patient_id');
                const docSelect = document.getElementById('doctor_id');
                
                patSelect.innerHTML = '<option value="">-- Select --</option>';
                docSelect.innerHTML = '<option value="">-- None --</option>';
                
                if(data.status === 'success') {
                    data.patients.forEach(pat => {
                        patSelect.innerHTML += `<option value="${pat.patient_id}">${pat.name}</option>`;
                    });
                    data.doctors.forEach(doc => {
                        docSelect.innerHTML += `<option value="${doc.doctor_id}">${doc.name}</option>`;
                    });
                }
            });
        }

        function loadPrescriptions() {
            const formData = new FormData();
            formData.append('action', 'get_prescriptions');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('prescriptionsBody');
                tbody.innerHTML = '';
                if(data.status === 'success' && data.data) {
                    data.data.forEach(pres => {
                        const tr = document.createElement('tr');
                        const statusText = translations[currentLang]['opt' + pres.status.charAt(0).toUpperCase() + pres.status.slice(1)];
                        tr.innerHTML = `
                            <td>${pres.prescription_id}</td>
                            <td>${pres.prescription_date}</td>
                            <td>${pres.patient_name}</td>
                            <td>${pres.doctor_name || '-'}</td>
                            <td><span class="status-badge status-${pres.status}">${statusText}</span></td>
                            <td>
                                <button class="btn" onclick="editPres(${pres.prescription_id}, ${pres.patient_id}, ${pres.doctor_id || 'null'}, '${pres.prescription_date}', '${pres.status}', '${(pres.notes||'').replace(/'/g, "\\'")}')">${translations[currentLang].btnEdit}</button>
                                <button class="btn btn-danger" onclick="deletePres(${pres.prescription_id})">${translations[currentLang].btnDel}</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }

        window.editPres = function(id, pat_id, doc_id, date, status, notes) {
            document.getElementById('prescription_id').value = id;
            document.getElementById('patient_id').value = pat_id;
            document.getElementById('doctor_id').value = doc_id || '';
            document.getElementById('prescription_date').value = date;
            document.getElementById('status').value = status;
            document.getElementById('notes').value = notes;
            document.getElementById('modalTitle').innerText = translations[currentLang].modalEdit;
            document.getElementById('message').innerText = '';
            modal.classList.add('active');
        }

        window.deletePres = function(id) {
            if(confirm('Are you sure?')) {
                const formData = new FormData();
                formData.append('action', 'delete_prescription');
                formData.append('prescription_id', id);

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        loadPrescriptions();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        presForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const msgDiv = document.getElementById('message');
            const formData = new FormData(this);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    modal.classList.remove('active');
                    loadPrescriptions();
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
            loadFormData();
            applyTranslations();
            const initialTheme = document.documentElement.getAttribute('data-theme');
            document.getElementById('themeToggle').innerText = initialTheme === 'light' ? '🌙' : '☀️';
        });
    </script>
</body>
</html>
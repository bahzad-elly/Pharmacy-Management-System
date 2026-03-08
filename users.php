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

    if ($action === 'get_users') {
        try {
            $stmt = $pdo->query("SELECT user_id, username, full_name, role, status FROM users ORDER BY full_name ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    if ($action === 'save_user') {
        $id = $_POST['user_id'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'cashier';
        $status = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($full_name)) {
            echo json_encode(['status' => 'error', 'message' => 'Username and Full Name are required']);
            exit;
        }

        try {
            if (empty($id)) {
                if (empty($password)) {
                    echo json_encode(['status' => 'error', 'message' => 'Password is required for new users']);
                    exit;
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $full_name, $role, $status]);
            } else {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, full_name = ?, role = ?, status = ? WHERE user_id = ?");
                    $stmt->execute([$username, $hash, $full_name, $role, $status, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, status = ? WHERE user_id = ?");
                    $stmt->execute([$username, $full_name, $role, $status, $id]);
                }
            }
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error or duplicate username']);
        }
        exit;
    }

    if ($action === 'delete_user') {
        $id = $_POST['user_id'] ?? '';
        if ($id == $_SESSION['user_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete your own account']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete user: They have linked sales or records']);
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
    <title>Users Management</title>
    <style>
        :root[data-theme="light"] {
            --bg-main: #f4f7f6; --bg-sidebar: #2c3e50; --bg-card: #ffffff;
            --text-main: #333333; --text-sidebar: #ecf0f1; --accent: #3498db;
            --danger: #e74c3c; --success: #2ecc71; --warning: #f1c40f; --border: #e0e0e0;
            --modal-bg: rgba(0,0,0,0.5);
        }
        :root[data-theme="dark"] {
            --bg-main: #1a1a2e; --bg-sidebar: #16213e; --bg-card: #0f3460;
            --text-main: #e0e0e0; --text-sidebar: #e0e0e0; --accent: #e94560;
            --danger: #ff4757; --success: #2ed573; --warning: #ffa502; --border: #2a2a4a;
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
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; color: white; }
        .status-active { background: var(--success); }
        .status-inactive { background: var(--danger); }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--modal-bg); justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-card); padding: 30px; border-radius: 8px; width: 100%; max-width: 500px; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 1.2rem; font-weight: bold; }
        .close-btn { cursor: pointer; font-size: 1.5rem; line-height: 1; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; background: transparent; color: var(--text-main); font-family: inherit; }
        .form-group select option { background: var(--bg-card); color: var(--text-main); }
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
            <li id="navSup" onclick="window.location.href='suppliers.php'">Suppliers</li>
            <li id="navUsers" class="active">Users & Staff</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Users Management</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
            </div>
        </header>

        <section class="content-area">
            <div class="action-bar">
                <button class="btn" id="addUserBtn">Add New User</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th id="thFull">Full Name</th>
                        <th id="thUser">Username</th>
                        <th id="thRole">Role</th>
                        <th id="thStatus">Status</th>
                        <th id="thAct">Actions</th>
                    </tr>
                </thead>
                <tbody id="usersBody">
                </tbody>
            </table>
        </section>
    </main>

    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add User</span>
                <span class="close-btn" id="closeModal">&times;</span>
            </div>
            <form id="userForm">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="user_id" id="user_id">
                
                <div class="form-group">
                    <label for="full_name" id="lblFull">Full Name</label>
                    <input type="text" name="full_name" id="full_name" required>
                </div>
                <div class="form-group">
                    <label for="username" id="lblUser">Username</label>
                    <input type="text" name="username" id="username" required>
                </div>
                <div class="form-group">
                    <label for="password" id="lblPass">Password (Leave blank to keep current)</label>
                    <input type="password" name="password" id="password">
                </div>
                
                <div class="form-group">
                    <label for="role" id="lblRole">Role</label>
                    <select name="role" id="role">
                        <option value="manager" id="optMan">Manager</option>
                        <option value="pharmacist" id="optPharm">Pharmacist</option>
                        <option value="technician" id="optTech">Technician</option>
                        <option value="cashier" id="optCash">Cashier</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status" id="lblStatus">Status</label>
                    <select name="status" id="status">
                        <option value="active" id="optAct">Active</option>
                        <option value="inactive" id="optInact">Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn" id="saveBtn" style="width: 100%; margin: 0;">Save User</button>
                <div id="message"></div>
            </form>
        </div>
    </div>

    <script>
        const translations = {
            en: {
                brand: "PharmaSys", navDash: "Dashboard", navMed: "Medicines", navInv: "Inventory",
                navPat: "Patients", navPres: "Prescriptions", navSales: "Sales", navPO: "Purchase Orders",
                navSup: "Suppliers", navUsers: "Users & Staff", pageTitle: "Users Management", addBtn: "Add New User",
                thFull: "Full Name", thUser: "Username", thRole: "Role", thStatus: "Status",
                thAct: "Actions", modalAdd: "Add User", modalEdit: "Edit User",
                lblFull: "Full Name", lblUser: "Username", lblPass: "Password (Leave blank to keep current)",
                lblRole: "Role", optMan: "Manager", optPharm: "Pharmacist", optTech: "Technician", optCash: "Cashier",
                lblStatus: "Status", optAct: "Active", optInact: "Inactive",
                saveBtn: "Save User", btnEdit: "Edit", btnDel: "Delete", toggleLang: "KU"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navInv: "کۆگا",
                navPat: "نەخۆشەکان", navPres: "ڕەچەتەکان", navSales: "فرۆشتن", navPO: "داواکارییەکانی کڕین",
                navSup: "دابینکەرەکان", navUsers: "بەکارهێنەران و ستاف", pageTitle: "بەڕێوەبردنی بەکارهێنەران", addBtn: "زیادکردنی بەکارهێنەری نوێ",
                thFull: "ناوی تەواو", thUser: "ناوی بەکارهێنەر", thRole: "ڕۆڵ", thStatus: "دۆخ",
                thAct: "کردارەکان", modalAdd: "زیادکردنی بەکارهێنەر", modalEdit: "دەستکاری بەکارهێنەر",
                lblFull: "ناوی تەواو", lblUser: "ناوی بەکارهێنەر", lblPass: "وشەی نهێنی (بەتاڵی جێبهێڵە بۆ گۆڕانکاری نەکردن)",
                lblRole: "ڕۆڵ", optMan: "بەڕێوەبەر", optPharm: "دەرمانساز", optTech: "تەکنیکار", optCash: "کاشێر",
                lblStatus: "دۆخ", optAct: "چالاک", optInact: "ناچالاک",
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
            document.getElementById('navPO').innerText = t.navPO;
            document.getElementById('navSup').innerText = t.navSup;
            document.getElementById('navUsers').innerText = t.navUsers;
            document.getElementById('pageTitle').innerText = t.pageTitle;
            document.getElementById('addUserBtn').innerText = t.addBtn;
            document.getElementById('thFull').innerText = t.thFull;
            document.getElementById('thUser').innerText = t.thUser;
            document.getElementById('thRole').innerText = t.thRole;
            document.getElementById('thStatus').innerText = t.thStatus;
            document.getElementById('thAct').innerText = t.thAct;
            
            if(document.getElementById('user_id').value === "") {
                document.getElementById('modalTitle').innerText = t.modalAdd;
            } else {
                document.getElementById('modalTitle').innerText = t.modalEdit;
            }
            
            document.getElementById('lblFull').innerText = t.lblFull;
            document.getElementById('lblUser').innerText = t.lblUser;
            document.getElementById('lblPass').innerText = t.lblPass;
            document.getElementById('lblRole').innerText = t.lblRole;
            document.getElementById('optMan').innerText = t.optMan;
            document.getElementById('optPharm').innerText = t.optPharm;
            document.getElementById('optTech').innerText = t.optTech;
            document.getElementById('optCash').innerText = t.optCash;
            document.getElementById('lblStatus').innerText = t.lblStatus;
            document.getElementById('optAct').innerText = t.optAct;
            document.getElementById('optInact').innerText = t.optInact;
            document.getElementById('saveBtn').innerText = t.saveBtn;
            document.getElementById('langToggle').innerText = t.toggleLang;
            
            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
            loadUsers();
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

        const modal = document.getElementById('userModal');
        const userForm = document.getElementById('userForm');

        document.getElementById('addUserBtn').addEventListener('click', () => {
            userForm.reset();
            document.getElementById('user_id').value = '';
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

        function loadUsers() {
            const formData = new FormData();
            formData.append('action', 'get_users');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('usersBody');
                tbody.innerHTML = '';
                if(data.status === 'success' && data.data) {
                    data.data.forEach(user => {
                        const tr = document.createElement('tr');
                        const roleText = translations[currentLang]['opt' + user.role.charAt(0).toUpperCase() + user.role.slice(1,4)];
                        const statusText = translations[currentLang]['opt' + user.status.charAt(0).toUpperCase() + user.status.slice(1,3) + (user.status === 'inactive' ? 'act' : '')];
                        
                        tr.innerHTML = `
                            <td>${user.user_id}</td>
                            <td>${user.full_name}</td>
                            <td>${user.username}</td>
                            <td>${roleText || user.role}</td>
                            <td><span class="status-badge status-${user.status}">${statusText || user.status}</span></td>
                            <td>
                                <button class="btn" onclick="editUser(${user.user_id}, '${user.username.replace(/'/g, "\\'")}', '${user.full_name.replace(/'/g, "\\'")}', '${user.role}', '${user.status}')">${translations[currentLang].btnEdit}</button>
                                <button class="btn btn-danger" onclick="deleteUser(${user.user_id})">${translations[currentLang].btnDel}</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }

        window.editUser = function(id, username, full_name, role, status) {
            document.getElementById('user_id').value = id;
            document.getElementById('username').value = username;
            document.getElementById('full_name').value = full_name;
            document.getElementById('password').value = ''; 
            document.getElementById('role').value = role;
            document.getElementById('status').value = status;
            document.getElementById('modalTitle').innerText = translations[currentLang].modalEdit;
            document.getElementById('message').innerText = '';
            modal.classList.add('active');
        }

        window.deleteUser = function(id) {
            if(confirm('Are you sure?')) {
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('user_id', id);

                fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        loadUsers();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        userForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const msgDiv = document.getElementById('message');
            const formData = new FormData(this);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    modal.classList.remove('active');
                    loadUsers();
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
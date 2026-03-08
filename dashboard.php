<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'get_stats') {
        try {
            $stats = [];
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM medicines");
            $stats['total_medicines'] = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM patients");
            $stats['total_patients'] = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE status = 'pending'");
            $stats['pending_prescriptions'] = $stmt->fetchColumn();
            
            echo json_encode(['status' => 'success', 'data' => $stats]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'success']);
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
    <title>Pharmacy Dashboard</title>
    <style>
        :root[data-theme="light"] {
            --bg-main: #f4f7f6;
            --bg-sidebar: #2c3e50;
            --bg-card: #ffffff;
            --text-main: #333333;
            --text-sidebar: #ecf0f1;
            --accent: #3498db;
            --border: #e0e0e0;
        }
        :root[data-theme="dark"] {
            --bg-main: #1a1a2e;
            --bg-sidebar: #16213e;
            --bg-card: #0f3460;
            --text-main: #e0e0e0;
            --text-sidebar: #e0e0e0;
            --accent: #e94560;
            --border: #2a2a4a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: var(--bg-main); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; transition: all 0.3s; }
        .sidebar { width: 250px; background: var(--bg-sidebar); color: var(--text-sidebar); display: flex; flex-direction: column; transition: all 0.3s; }
        .sidebar-header { padding: 20px; font-size: 1.5rem; font-weight: bold; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-links { list-style: none; flex: 1; padding-top: 20px; }
        .nav-links li { padding: 15px 25px; cursor: pointer; transition: background 0.2s; }
        .nav-links li:hover { background: rgba(255,255,255,0.1); border-left: 4px solid var(--accent); }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { background: var(--bg-card); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
        .controls button { padding: 8px 12px; margin-left: 10px; border: none; border-radius: 5px; cursor: pointer; background: var(--accent); color: white; font-weight: bold; }
        .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; padding: 30px; }
        .card { background: var(--bg-card); padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; border: 1px solid var(--border); }
        .card h3 { font-size: 1.2rem; margin-bottom: 10px; opacity: 0.8; }
        .card .number { font-size: 2.5rem; font-weight: bold; color: var(--accent); }
        [dir="rtl"] .nav-links li:hover { border-left: none; border-right: 4px solid var(--accent); }
        [dir="rtl"] .controls button { margin-left: 0; margin-right: 10px; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header" id="brandName">PharmaSys</div>
        <ul class="nav-links">
            <li id="navDash">Dashboard</li>
            <li id="navMed">Medicines</li>
            <li id="navPat">Patients</li>
            <li id="navPres">Prescriptions</li>
            <li id="navSales">Sales</li>
        </ul>
    </nav>

    <main class="main-content">
        <header class="topbar">
            <h2 id="pageTitle">Dashboard Overview</h2>
            <div class="controls">
                <button id="langToggle">KU</button>
                <button id="themeToggle">🌙</button>
                <button id="logoutBtn">Logout</button>
            </div>
        </header>

        <section class="dashboard-cards">
            <div class="card">
                <h3 id="cardMedTitle">Total Medicines</h3>
                <div class="number" id="statMeds">-</div>
            </div>
            <div class="card">
                <h3 id="cardPatTitle">Total Patients</h3>
                <div class="number" id="statPats">-</div>
            </div>
            <div class="card">
                <h3 id="cardPresTitle">Pending Prescriptions</h3>
                <div class="number" id="statPres">-</div>
            </div>
        </section>
    </main>

    <script>
        const translations = {
            en: {
                brand: "PharmaSys", navDash: "Dashboard", navMed: "Medicines", navPat: "Patients", 
                navPres: "Prescriptions", navSales: "Sales", pageTitle: "Dashboard Overview",
                cardMed: "Total Medicines", cardPat: "Total Patients", cardPres: "Pending Prescriptions",
                logout: "Logout", toggleLang: "KU"
            },
            ku: {
                brand: "فارماسیستەم", navDash: "داشبۆرد", navMed: "دەرمانەکان", navPat: "نەخۆشەکان", 
                navPres: "ڕەچەتەکان", navSales: "فرۆشتن", pageTitle: "پوختەی داشبۆرد",
                cardMed: "کۆی دەرمانەکان", cardPat: "کۆی نەخۆشەکان", cardPres: "ڕەچەتە هەڵپەسێردراوەکان",
                logout: "چوونە دەرەوە", toggleLang: "EN"
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
            document.getElementById('cardMedTitle').innerText = t.cardMed;
            document.getElementById('cardPatTitle').innerText = t.cardPat;
            document.getElementById('cardPresTitle').innerText = t.cardPres;
            document.getElementById('logoutBtn').innerText = t.logout;
            document.getElementById('langToggle').innerText = t.toggleLang;
            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
            document.documentElement.lang = currentLang;
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

        document.getElementById('logoutBtn').addEventListener('click', () => {
            const formData = new FormData();
            formData.append('action', 'logout');
            
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') window.location.href = 'index.php';
            });
        });

        function loadDashboardStats() {
            const formData = new FormData();
            formData.append('action', 'get_stats');

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    document.getElementById('statMeds').innerText = data.data.total_medicines;
                    document.getElementById('statPats').innerText = data.data.total_patients;
                    document.getElementById('statPres').innerText = data.data.pending_prescriptions;
                }
            })
            .catch(err => console.error('Error fetching stats:', err));
        }

        document.addEventListener('DOMContentLoaded', () => {
            applyTranslations();
            loadDashboardStats();
            
            const initialTheme = document.documentElement.getAttribute('data-theme');
            document.getElementById('themeToggle').innerText = initialTheme === 'light' ? '🌙' : '☀️';
        });
    </script>
</body>
</html>
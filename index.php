<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    header('Content-Type: application/json');
    
    $host = 'localhost';
    $db = 'pharmacy_db';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Credentials required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT user_id, password_hash, role, theme_preference, language_preference FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $userData = $stmt->fetch();

        if ($userData && password_verify($password, $userData['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userData['user_id'];
            $_SESSION['role'] = $userData['role'];
            $_SESSION['theme'] = $userData['theme_preference'];
            $_SESSION['lang'] = $userData['language_preference'];
            echo json_encode(['status' => 'success', 'message' => 'Authenticated']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'System error']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Login</title>
    <style>
        :root[data-theme="light"] {
            --bg-color: #f4f4f9;
            --text-color: #333;
            --container-bg: #ffffff;
            --btn-bg: #007bff;
            --btn-text: #ffffff;
            --input-border: #cccccc;
        }
        :root[data-theme="dark"] {
            --bg-color: #1a1a2e;
            --text-color: #e0e0e0;
            --container-bg: #16213e;
            --btn-bg: #0f3460;
            --btn-text: #e0e0e0;
            --input-border: #444444;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: system-ui, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            transition: all 0.3s;
        }
        .login-container {
            background: var(--container-bg);
            padding: 2.5rem;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 350px;
        }
        .controls {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 15px;
        }
        .controls button {
            width: auto;
            padding: 5px 10px;
        }
        h2 { text-align: center; margin-bottom: 1.5rem; }
        .input-group { margin-bottom: 1.2rem; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .input-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--input-border);
            border-radius: 5px;
            box-sizing: border-box;
            background-color: transparent;
            color: var(--text-color);
        }
        button {
            width: 100%;
            padding: 12px;
            background: var(--btn-bg);
            color: var(--btn-text);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
        }
        #loginMessage { margin-top: 15px; text-align: center; font-weight: 500; min-height: 20px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="controls">
            <button id="langToggle" type="button">KU</button>
            <button id="themeToggle" type="button">🌙</button>
        </div>
        <h2 id="loginTitle">System Login</h2>
        <form id="loginForm">
            <input type="hidden" name="action" value="login">
            <div class="input-group">
                <label for="username" id="userLabel">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="input-group">
                <label for="password" id="passLabel">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" id="loginBtn">Login</button>
            <div id="loginMessage"></div>
        </form>
    </div>

    <script>
        const translations = {
            en: { title: "System Login", user: "Username", pass: "Password", btn: "Login", toggleLang: "KU", msgWait: "Processing..." },
            ku: { title: "چوونە ژوورەوەی سیستم", user: "ناوی بەکارهێنەر", pass: "وشەی نهێنی", btn: "چوونە ژوورەوە", toggleLang: "EN", msgWait: "چاوەڕێ بکە..." }
        };

        let currentLang = 'en';

        document.getElementById('langToggle').addEventListener('click', (e) => {
            currentLang = currentLang === 'en' ? 'ku' : 'en';
            document.getElementById('loginTitle').innerText = translations[currentLang].title;
            document.getElementById('userLabel').innerText = translations[currentLang].user;
            document.getElementById('passLabel').innerText = translations[currentLang].pass;
            document.getElementById('loginBtn').innerText = translations[currentLang].btn;
            e.target.innerText = translations[currentLang].toggleLang;
            document.documentElement.dir = currentLang === 'ku' ? 'rtl' : 'ltr';
        });

        document.getElementById('themeToggle').addEventListener('click', () => {
            const htmlNode = document.documentElement;
            const currentTheme = htmlNode.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            htmlNode.setAttribute('data-theme', newTheme);
            document.getElementById('themeToggle').innerText = newTheme === 'light' ? '🌙' : '☀️';
        });

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const msgDiv = document.getElementById('loginMessage');
            const formData = new FormData(this);
            
            msgDiv.style.color = 'inherit';
            msgDiv.innerText = translations[currentLang].msgWait;

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    msgDiv.style.color = '#28a745';
                    msgDiv.innerText = data.message;
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    msgDiv.style.color = '#dc3545';
                    msgDiv.innerText = data.message;
                }
            })
            .catch(error => {
                msgDiv.style.color = '#dc3545';
                msgDiv.innerText = 'Network error';
            });
        });
    </script>
</body>
</html>
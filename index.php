<?php
session_start();
require_once 'db.php';

$isAuthenticated = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hlídač - Monitoring webu</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: red; margin-bottom: 15px; }
        .success { color: green; margin-bottom: 15px; }
        nav { margin-bottom: 20px; }
        nav a { margin-right: 15px; text-decoration: none; color: #007bff; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f8f8; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .status-up { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hlídač</h1>
        
        <?php if ($isAuthenticated): ?>
            <nav>
                <span>Přihlášen jako: <strong><?php echo htmlspecialchars($_SESSION['player_name']); ?></strong></span> |
                <a href="admin.php">Správa sledování</a> |
                <a href="#" id="logoutBtn">Odhlásit se</a>
            </nav>

            <h2>Vaše sledované položky</h2>
            <div id="dashboard">
                <?php
                $targets = getTargets($db, $_SESSION['user_id']);
                if (empty($targets)): ?>
                    <p>Zatím nesledujete žádné stránky. <a href="admin.php">Přidat první sledování</a>.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Název</th>
                                <th>Poslední hodnota</th>
                                <th>Poslední kontrola</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($targets as $target): ?>
                                <tr>
                                    <td><a href="<?php echo htmlspecialchars($target['url']); ?>" target="_blank"><?php echo htmlspecialchars($target['name']); ?></a></td>
                                    <td><?php echo htmlspecialchars($target['last_value'] ?? 'Zatím nezkontrolováno'); ?></td>
                                    <td><?php echo $target['last_checked'] ? date('d.m.Y H:i', strtotime($target['last_checked'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div id="authForms">
                <div id="loginForm">
                    <h2>Přihlášení</h2>
                    <div id="loginError" class="error"></div>
                    <form id="doLogin">
                        <div class="form-group">
                            <label>E-mail</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Heslo</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit">Přihlásit</button>
                    </form>
                    <p>Nemáte účet? <a href="#" onclick="showRegister()">Registrovat se</a></p>
                </div>

                <div id="registerForm" style="display:none;">
                    <h2>Registrace</h2>
                    <div id="registerError" class="error"></div>
                    <form id="doRegister">
                        <div class="form-group">
                            <label>Jméno</label>
                            <input type="text" name="playerName" required>
                        </div>
                        <div class="form-group">
                            <label>E-mail</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Heslo</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit">Registrovat</button>
                    </form>
                    <p>Již máte účet? <a href="#" onclick="showLogin()">Přihlásit se</a></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function showRegister() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'block';
        }
        function showLogin() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('registerForm').style.display = 'none';
        }

        if (document.getElementById('doLogin')) {
            document.getElementById('doLogin').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const res = await fetch('auth.php?action=login', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) location.reload();
                else document.getElementById('loginError').innerText = data.error;
            };
        }

        if (document.getElementById('doRegister')) {
            document.getElementById('doRegister').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const res = await fetch('auth.php?action=register', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) location.reload();
                else document.getElementById('registerError').innerText = data.error;
            };
        }

        if (document.getElementById('logoutBtn')) {
            document.getElementById('logoutBtn').onclick = async (e) => {
                e.preventDefault();
                await fetch('auth.php?action=logout');
                location.reload();
            };
        }
    </script>
</body>
</html>

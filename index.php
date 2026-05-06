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
    <title>Hlídač - Dashboard</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; background: #f4f4f4; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 5px; }
        nav { margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        nav a { margin-right: 15px; text-decoration: none; color: #007bff; font-weight: bold; }
        .product-card { border: 1px solid #ddd; border-radius: 8px; margin-bottom: 30px; overflow: hidden; }
        .product-header { background: #f8f8f8; padding: 15px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
        .product-header h2 { margin: 0; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #fafafa; font-size: 0.9em; color: #666; }
        tr:last-child td { border-bottom: none; }
        .price { font-weight: bold; color: #28a745; font-size: 1.1em; }
        .store-name { font-weight: bold; }
        .found-title { color: #666; font-size: 0.9em; }
        .auth-container { max-width: 400px; margin: 100px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 10px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        .error { color: red; margin-bottom: 15px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($isAuthenticated): ?>
            <h1>Hlídač</h1>
            <nav>
                <a href="index.php" style="color:#333">Dashboard</a>
                <a href="admin_products.php">Produkty</a>
                <a href="admin_stores.php">Obchody</a>
                <a href="#" id="logoutBtn" style="float:right; color:#dc3545">Odhlásit se</a>
            </nav>

            <div id="dashboard">
                <?php
                $products = getProducts($db, $_SESSION['user_id']);
                if (empty($products)): ?>
                    <p>Zatím nesledujete žádné produkty. <a href="admin_products.php">Přidat první produkt</a>.</p>
                <?php else: 
                    foreach ($products as $p): 
                        $matches = getProductMatches($db, $p['id']);
                        // Sort matches by price (basic numeric extraction)
                        usort($matches, function($a, $b) {
                            $pa = (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $a['last_price']));
                            $pb = (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $b['last_price']));
                            return $pa <=> $pb;
                        });
                ?>
                    <div class="product-card">
                        <div class="product-header">
                            <h2><?php echo htmlspecialchars($p['name']); ?></h2>
                            <small>Sledováno v <?php echo count($matches); ?> obchodech</small>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Obchod</th>
                                    <th>Nalezený produkt</th>
                                    <th>Cena</th>
                                    <th>Poslední kontrola</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matches as $m): ?>
                                    <tr>
                                        <td><span class="store-name"><?php echo htmlspecialchars($m['store_name']); ?></span></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($m['found_url']); ?>" target="_blank" class="found-title">
                                                <?php echo htmlspecialchars($m['found_title']); ?>
                                            </a>
                                        </td>
                                        <td><span class="price"><?php echo htmlspecialchars($m['last_price']); ?></span></td>
                                        <td><small><?php echo $m['last_checked'] ? date('d.m.Y H:i', strtotime($m['last_checked'])) : '-'; ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($matches)): ?>
                                    <tr><td colspan="4">Zatím nebyly nalezeny žádné výsledky. Spusťte <code>check.php</code>.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; endif; ?>
            </div>

        <?php else: ?>
            <div class="auth-container">
                <h1>Hlídač - Přihlášení</h1>
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
                    <button type="submit">Přihlásit se</button>
                </form>
                <p style="text-align:center; margin-top:20px; font-size:0.9em;">
                    Nemáte účet? <a href="#" onclick="alert('Registrace je v této verzi možná pouze přes auth.php nebo ručně v DB.')">Kontaktujte správce</a>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script>
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

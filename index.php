<?php
session_start();
date_default_timezone_set('Europe/Prague');
require_once 'db.php';

$isAuthenticated = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
        .close { float: right; cursor: pointer; font-size: 28px; }
    </style>
</head>
<body>
    <div id="chartModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeChart()">&times;</span>
            <canvas id="priceChart"></canvas>
        </div>
    </div>
    <div class="container">
        <?php if ($isAuthenticated): ?>
            <h1>Hlídač</h1>
            <nav>
                <a href="index.php" style="color:#333">Dashboard</a>
                <a href="admin_products.php">Produkty</a>
                <a href="admin_stores.php">Obchody</a>
                <?php if (($_SESSION['role'] ?? '') === 'ADMIN'): ?>
                    <a href="admin_users.php">Uživatelé</a>
                <?php endif; ?>
                <span style="float:right;">
                    <small>Přihlášen jako: <strong><?php echo htmlspecialchars($_SESSION['player_name']); ?><?php if (($_SESSION['role'] ?? '') === 'ADMIN') echo ' (ADMIN)'; ?></strong></small> | 
                    <a href="#" id="logoutBtn" style="color:#dc3545">Odhlásit se</a>
                </span>
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
                                    <th>Obrázek</th>
                                    <th>Obchod</th>
                                    <th>Nalezený produkt</th>
                                    <th>Dostupnost</th>
                                    <th>Cena</th>
                                    <th>Poslední kontrola</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matches as $m): ?>
                                    <tr>
                                        <td>
                                            <?php if ($m['image_url']): ?>
                                                <img src="<?php echo htmlspecialchars($m['image_url']); ?>" style="width:50px; height:auto;" alt="Product image">
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="store-name"><?php echo htmlspecialchars($m['store_name']); ?></span></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($m['found_url']); ?>" target="_blank" class="found-title">
                                                <?php echo htmlspecialchars($m['found_title']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($m['availability']); ?></td>
                                        <td><span class="price"><?php echo htmlspecialchars($m['last_price']); ?></span></td>
                                        <td>
                                            <button onclick="showChart(<?php echo $m['product_id']; ?>, <?php echo $m['store_id']; ?>, '<?php echo htmlspecialchars($m['found_title']); ?>')">Graf</button>
                                            <br><small><?php echo $m['last_checked'] ? date('d.m.Y H:i', strtotime($m['last_checked'] . ' UTC')) : '-'; ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($matches)): ?>
                                    <tr><td colspan="6">Zatím nebyly nalezeny žádné výsledky. Spusťte <code>check.php</code>.</td></tr>
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
                    <button type="submit" id="loginBtn">Přihlásit se</button>
                    <button type="button" id="toggleRegBtn" style="background:#6c757d; margin-top:10px;">Registrovat se</button>
                </form>

                <form id="doRegister" style="display:none;">
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
                    <button type="submit" style="background:#28a745;">Registrovat se</button>
                    <button type="button" id="toggleLoginBtn" style="background:#6c757d; margin-top:10px;">Zpět k přihlášení</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let myChart;
        async function showChart(productId, storeId, title) {
            const res = await fetch(`api_price_history.php?product_id=${productId}&store_id=${storeId}`);
            const rawData = await res.text();
            
            // WebZdarma injects ad HTML before/after JSON, we need to extract the JSON part
            const jsonMatch = rawData.match(/\[.*\]/);
            if (!jsonMatch) {
                alert("Nepodařilo se načíst data grafu (chyba formátu).");
                return;
            }
            const data = JSON.parse(jsonMatch[0]);
            
            document.getElementById('chartModal').style.display = 'block';
            const ctx = document.getElementById('priceChart').getContext('2d');
            
            if (myChart) myChart.destroy();
            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.check_date),
                    datasets: [{ 
                        label: 'Vývoj ceny: ' + title, 
                        data: data.map(d => parseFloat(d.price)),
                        borderColor: '#28a745',
                        tension: 0.1
                    }]
                },
                options: { responsive: true }
            });
        }
        function closeChart() {
            document.getElementById('chartModal').style.display = 'none';
        }
        
        if (document.getElementById('doLogin')) {
            document.getElementById('toggleRegBtn').onclick = () => {
                document.getElementById('doLogin').style.display = 'none';
                document.getElementById('doRegister').style.display = 'block';
            };
            document.getElementById('toggleLoginBtn').onclick = () => {
                document.getElementById('doLogin').style.display = 'block';
                document.getElementById('doRegister').style.display = 'none';
            };

            document.getElementById('doLogin').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const res = await fetch('auth.php?action=login', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) location.reload();
                else document.getElementById('loginError').innerText = data.error;
            };

            document.getElementById('doRegister').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const res = await fetch('auth.php?action=register', { method: 'POST', body: formData });
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

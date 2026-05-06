<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = $_POST['name'] ?? '';
        if ($name) {
            addProduct($db, $userId, $name);
            $message = 'Produkt byl přidán.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        deleteProduct($db, $id, $userId);
        $message = 'Produkt byl smazán.';
    }
}

$products = getProducts($db, $userId);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Hlídač - Správa produktů</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #28a745; color: #fff; }
        .btn-danger { background: #dc3545; color: #fff; }
        nav { margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        nav a { margin-right: 15px; text-decoration: none; color: #007bff; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f8f8; }
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Správa produktů</h1>
        <nav>
            <a href="index.php">Dashboard</a>
            <a href="admin_products.php" style="color:#333">Produkty</a>
            <a href="admin_stores.php">Obchody</a>
        </nav>

        <?php if ($message): ?>
            <p style="background:#d4edda; color:#155724; padding:10px;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="card">
            <h2>Přidat nový produkt</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Název produktu / Klíčové slovo (např. Břink!)</label>
                    <input type="text" name="name" required>
                </div>
                <button type="submit" class="btn-primary">Přidat produkt</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Název produktu</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Smazat produkt a všechny nalezené výsledky?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn-danger">Smazat</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <tr><td colspan="2">Zatím žádné produkty.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$editStore = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $template = $_POST['search_url_template'] ?? '';
        $pSel = $_POST['price_selector'] ?? '';
        $lSel = $_POST['link_selector'] ?? '';
        $tSel = $_POST['title_selector'] ?? '';
        $aSel = $_POST['availability_selector'] ?? '';
        $iSel = $_POST['image_selector'] ?? '';
        if ($name && $template) {
            addStore($db, $userId, $name, $template, $pSel, $lSel, $tSel, $aSel, $iSel);
            $message = 'Obchod byl přidán.';
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $template = $_POST['search_url_template'] ?? '';
        $pSel = $_POST['price_selector'] ?? '';
        $lSel = $_POST['link_selector'] ?? '';
        $tSel = $_POST['title_selector'] ?? '';
        $aSel = $_POST['availability_selector'] ?? '';
        $iSel = $_POST['image_selector'] ?? '';
        
        $stmt = $db->prepare("SELECT user_id FROM stores WHERE id = ?");
        $stmt->execute([$id]);
        $store = $stmt->fetch();
        if ($store && $store['user_id'] != 0 && $id && $name && $template) {
            updateStore($db, $id, $userId, $name, $template, $pSel, $lSel, $tSel, $aSel, $iSel);
            $message = 'Obchod byl upraven.';
        } elseif ($store['user_id'] == 0) {
            $message = 'Globální obchody nelze upravovat.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        $stmt = $db->prepare("SELECT user_id FROM stores WHERE id = ?");
        $stmt->execute([$id]);
        $store = $stmt->fetch();
        if ($store && $store['user_id'] != 0) {
            deleteStore($db, $id, $userId);
            $message = 'Obchod byl smazán.';
        } else {
            $message = 'Globální obchody nelze mazat.';
        }
    }
}

if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM stores WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $editStore = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stores = getStores($db, $userId);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Hlídač - Správa obchodů</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; background: #f4f4f4; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="url"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #28a745; color: #fff; }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-warning { background: #ffc107; color: #000; text-decoration: none; padding: 8px 12px; display: inline-block; }
        nav { margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        nav a { margin-right: 15px; text-decoration: none; color: #007bff; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background: #f8f8f8; }
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        code { background: #eee; padding: 2px 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Správa obchodů</h1>
        <nav>
            <a href="index.php">Dashboard</a>
            <a href="admin_products.php">Produkty</a>
            <a href="admin_stores.php" style="color:#333">Obchody</a>
        </nav>

        <?php if ($message): ?>
            <p style="background:#d4edda; color:#155724; padding:10px;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="card">
            <h2><?php echo $editStore ? 'Upravit obchod' : 'Přidat nový obchod'; ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editStore ? 'update' : 'add'; ?>">
                <?php if ($editStore): ?><input type="hidden" name="id" value="<?php echo $editStore['id']; ?>"><?php endif; ?>
                
                <div class="form-group">
                    <label>Název obchodu (např. Planeta Her)</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($editStore['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Vyhledávací URL šablona (např. https://www.planetaher.cz/vyhledavani?s=)</label>
                    <input type="url" name="search_url_template" value="<?php echo htmlspecialchars($editStore['search_url_template'] ?? ''); ?>" required>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1">
                        <label>Selektor ceny</label>
                        <input type="text" name="price_selector" value="<?php echo htmlspecialchars($editStore['price_selector'] ?? ''); ?>" placeholder=".price" required>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Selektor odkazu</label>
                        <input type="text" name="link_selector" value="<?php echo htmlspecialchars($editStore['link_selector'] ?? ''); ?>" placeholder=".product-name a" required>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Selektor názvu</label>
                        <input type="text" name="title_selector" value="<?php echo htmlspecialchars($editStore['title_selector'] ?? ''); ?>" placeholder=".product-name" required>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Selektor dostupnosti</label>
                        <input type="text" name="availability_selector" value="<?php echo htmlspecialchars($editStore['availability_selector'] ?? ''); ?>" placeholder=".stock">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Selektor obrázku</label>
                        <input type="text" name="image_selector" value="<?php echo htmlspecialchars($editStore['image_selector'] ?? ''); ?>" placeholder=".image img">
                    </div>
                </div>
                <button type="submit" class="btn-primary"><?php echo $editStore ? 'Uložit změny' : 'Přidat obchod'; ?></button>
                <?php if ($editStore): ?><a href="admin_stores.php">Zrušit</a><?php endif; ?>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Obchod</th>
                    <th>Selektory</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stores as $s): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($s['name']); ?></strong> 
                        <?php if ($s['user_id'] == 0): ?><span style="background: #e2e3e5; padding: 2px 5px; font-size: 0.8em; border-radius: 3px;">Globální</span><?php endif; ?><br>
                        <small><code><?php echo htmlspecialchars($s['search_url_template']); ?></code></small>
                    </td>
                    <td>
                        Cena: <code><?php echo htmlspecialchars($s['price_selector']); ?></code><br>
                        Odkaz: <code><?php echo htmlspecialchars($s['link_selector']); ?></code><br>
                        Název: <code><?php echo htmlspecialchars($s['title_selector']); ?></code><br>
                        Dostupnost: <code><?php echo htmlspecialchars($s['availability_selector']); ?></code><br>
                        Obrázek: <code><?php echo htmlspecialchars($s['image_selector']); ?></code>
                    </td>
                    <td>
                        <a href="admin_stores.php?edit=<?php echo $s['id']; ?>" class="btn-warning">Upravit</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Smazat obchod a všechny jeho výsledky?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                            <button type="submit" class="btn-danger">Smazat</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

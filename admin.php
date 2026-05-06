<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$editTarget = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $url = $_POST['url'] ?? '';
        $selector = $_POST['selector'] ?? '';
        if ($name && $url && $selector) {
            addTarget($db, $userId, $name, $url, $selector);
            $message = 'Položka byla úspěšně přidána.';
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $url = $_POST['url'] ?? '';
        $selector = $_POST['selector'] ?? '';
        if ($id && $name && $url && $selector) {
            updateTarget($db, $id, $userId, $name, $url, $selector);
            $message = 'Položka byla úspěšně upravena.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        deleteTarget($db, $id, $userId);
        $message = 'Položka byla smazána.';
    }
}

// Check for edit request via GET
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM targets WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $editTarget = $stmt->fetch(PDO::FETCH_ASSOC);
}

$targets = getTargets($db, $userId);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hlídač - Správa sledování</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; background: #f4f4f4; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="url"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; box-sizing: border-box; line-height: 1.2; }
        button.btn-delete { background: #dc3545; }
        .btn-edit { background: #ffc107; color: #000; text-decoration: none; display: inline-block; padding: 10px 15px; border-radius: 4px; font-size: 14px; cursor: pointer; border: none; box-sizing: border-box; line-height: 1.2; }
        button:hover, .btn-edit:hover { opacity: 0.9; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; background: #d4edda; color: #155724; }
        nav { margin-bottom: 20px; }
        nav a { margin-right: 15px; text-decoration: none; color: #007bff; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f8f8; }
        .card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .flex-actions { display: flex; gap: 5px; align-items: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Správa sledování</h1>
        <nav>
            <a href="index.php">← Zpět na přehled</a>
        </nav>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><?php echo $editTarget ? 'Upravit položku' : 'Přidat novou položku'; ?></h2>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="<?php echo $editTarget ? 'update' : 'add'; ?>">
                <?php if ($editTarget): ?>
                    <input type="hidden" name="id" value="<?php echo $editTarget['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Název</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($editTarget['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>URL adresa</label>
                    <input type="url" name="url" value="<?php echo htmlspecialchars($editTarget['url'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>CSS Selektor</label>
                    <input type="text" name="selector" value="<?php echo htmlspecialchars($editTarget['selector'] ?? ''); ?>" required>
                </div>
                <button type="submit"><?php echo $editTarget ? 'Uložit změny' : 'Přidat sledování'; ?></button>
                <?php if ($editTarget): ?>
                    <a href="admin.php" style="margin-left:10px;">Zrušit úpravy</a>
                <?php endif; ?>
            </form>
        </div>

        <h2>Seznam sledovaných položek</h2>
        <table>
            <thead>
                <tr>
                    <th>Název</th>
                    <th>URL / Selektor</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($targets as $target): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($target['name']); ?></strong></td>
                        <td>
                            <small><?php echo htmlspecialchars($target['url']); ?></small><br>
                            <code><?php echo htmlspecialchars($target['selector']); ?></code>
                        </td>
                        <td>
                            <div class="flex-actions">
                                <a href="admin.php?edit=<?php echo $target['id']; ?>" class="btn-edit">Upravit</a>
                                <form method="POST" onsubmit="return confirm('Opravdu smazat?');" style="margin:0;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $target['id']; ?>">
                                    <button type="submit" class="btn-delete">Smazat</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($targets)): ?>
                    <tr><td colspan="3">Zatím žádné položky.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

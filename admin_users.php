<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'ADMIN') {
    header('Location: index.php');
    exit;
}

$stmt = $db->query("SELECT email, player_name, role FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Hlídač - Správa uživatelů</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        nav { margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        nav a { margin-right: 15px; text-decoration: none; color: #007bff; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f8f8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Správa uživatelů</h1>
        <nav>
            <a href="index.php">Dashboard</a>
            <a href="admin_products.php">Produkty</a>
            <a href="admin_stores.php">Obchody</a>
            <a href="admin_users.php" style="color:#333">Uživatelé</a>
        </nav>

        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Jméno</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars($u['player_name']); ?></td>
                    <td><?php echo htmlspecialchars($u['role']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php
// api_price_history.php - API to fetch price history for a product and store
require_once 'db.php';

$productId = $_GET['product_id'] ?? 0;
$storeId = $_GET['store_id'] ?? 0;

if (!$productId || !$storeId) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$stmt = $db->prepare("SELECT check_date, price FROM price_history WHERE product_id = ? AND store_id = ? ORDER BY check_date ASC");
$stmt->execute([$productId, $storeId]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);
?>

<?php
// db.php - Database connection and helper functions for Multi-Store version

$db_file = 'watchman.db';
$db = new PDO("sqlite:$db_file");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Initialize database schema
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    player_name TEXT NOT NULL,
    last_login DATETIME
)");

// Stores: Where we search
$db->exec("CREATE TABLE IF NOT EXISTS stores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    search_url_template TEXT NOT NULL,
    price_selector TEXT NOT NULL,
    link_selector TEXT NOT NULL,
    title_selector TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

// Products: What we search for
$db->exec("CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

// Product Matches: Results of searches
$db->exec("CREATE TABLE IF NOT EXISTS product_matches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    store_id INTEGER NOT NULL,
    found_title TEXT,
    found_url TEXT,
    last_price TEXT,
    last_checked DATETIME,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (store_id) REFERENCES stores(id)
)");

// User helper functions
function createUser($db, $email, $password, $playerName) {
    $stmt = $db->prepare("INSERT INTO users (email, password, player_name) VALUES (?, ?, ?)");
    $stmt->execute([$email, $password, $playerName]);
    return $db->lastInsertId();
}

function findUserByEmail($db, $email) {
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateUserLastLogin($db, $userId, $lastLogin) {
    $stmt = $db->prepare("UPDATE users SET last_login = ? WHERE id = ?");
    $stmt->execute([$lastLogin, $userId]);
}

// Store management functions
function getStores($db, $userId) {
    $stmt = $db->prepare("SELECT * FROM stores WHERE user_id = ? OR user_id = 0 ORDER BY user_id ASC, name ASC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addStore($db, $userId, $name, $template, $priceSel, $linkSel, $titleSel, $availSel, $imgSel) {
    $stmt = $db->prepare("INSERT INTO stores (user_id, name, search_url_template, price_selector, link_selector, title_selector, availability_selector, image_selector) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $name, $template, $priceSel, $linkSel, $titleSel, $availSel, $imgSel]);
}

function updateStore($db, $id, $userId, $name, $template, $priceSel, $linkSel, $titleSel, $availSel, $imgSel) {
    $stmt = $db->prepare("UPDATE stores SET name = ?, search_url_template = ?, price_selector = ?, link_selector = ?, title_selector = ?, availability_selector = ?, image_selector = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$name, $template, $priceSel, $linkSel, $titleSel, $availSel, $imgSel, $id, $userId]);
}

function deleteStore($db, $id, $userId) {
    $db->beginTransaction();
    $db->prepare("DELETE FROM product_matches WHERE store_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM stores WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    $db->commit();
}

// Product management functions
function getProducts($db, $userId) {
    $stmt = $db->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addProduct($db, $userId, $name) {
    $stmt = $db->prepare("INSERT INTO products (user_id, name) VALUES (?, ?)");
    $stmt->execute([$userId, $name]);
}

function deleteProduct($db, $id, $userId) {
    $db->beginTransaction();
    $db->prepare("DELETE FROM product_matches WHERE product_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM products WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    $db->commit();
}

function getProductMatches($db, $productId) {
    $stmt = $db->prepare("SELECT m.*, s.name as store_name FROM product_matches m JOIN stores s ON m.store_id = s.id WHERE m.product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

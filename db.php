<?php
// db.php - Database connection and helper functions

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

$db->exec("CREATE TABLE IF NOT EXISTS targets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    selector TEXT NOT NULL,
    last_value TEXT,
    last_checked DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    target_id INTEGER NOT NULL,
    value TEXT,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (target_id) REFERENCES targets(id)
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

// Target management functions
function getTargets($db, $userId) {
    $stmt = $db->prepare("SELECT * FROM targets WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addTarget($db, $userId, $name, $url, $selector) {
    $stmt = $db->prepare("INSERT INTO targets (user_id, name, url, selector) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $name, $url, $selector]);
    return $db->lastInsertId();
}

function updateTarget($db, $id, $userId, $name, $url, $selector) {
    $stmt = $db->prepare("UPDATE targets SET name = ?, url = ?, selector = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$name, $url, $selector, $id, $userId]);
}

function deleteTarget($db, $id, $userId) {
    $stmt = $db->prepare("DELETE FROM targets WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
}

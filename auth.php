<?php
// auth.php - Authentication logic
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'register') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $playerName = $_POST['playerName'] ?? '';
    
    if (!$email || !$password || !$playerName) {
        echo json_encode(['error' => 'Všechna pole jsou povinná!']);
        exit;
    }
    
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $userId = createUser($db, $email, $hashedPassword, $playerName);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['player_name'] = $playerName;
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['error' => 'Tento e-mail již existuje!']);
        } else {
            echo json_encode(['error' => 'Chyba při registraci: ' . $e->getMessage()]);
        }
    }
}

if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        echo json_encode(['error' => 'E-mail a heslo jsou povinné!']);
        exit;
    }
    
    $user = findUserByEmail($db, $email);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['player_name'] = $user['player_name'];
        $_SESSION['role'] = $user['role'];
        
        // Update last login
        updateUserLastLogin($db, (int) $user['id'], date('Y-m-d H:i:s'));
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Neplatný e-mail nebo heslo!']);
    }
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
}

if ($action === 'status') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'player_name' => $_SESSION['player_name']
            ]
        ]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
}

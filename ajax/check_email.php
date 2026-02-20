<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$email = clean_input($_GET['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['valid' => false, 'exists' => false, 'message' => 'Invalid email format']);
    exit;
}

$conn = db();
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
$exists = $stmt->num_rows > 0;
$stmt->close();

echo json_encode([
    'valid' => true,
    'exists' => $exists,
    'message' => $exists ? 'Email already registered' : 'Email is available',
]);
<?php
header('Content-Type: application/json');
include("configBackEnd.php");

$mysqli = new mysqli($servername, $username, $password, $dbnameUsr);
if ($mysqli->connect_errno) { http_response_code(500); echo json_encode(['error'=>'DB connect']); exit; }

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$token = $body['Token'] ?? '';
if (!isAdmin($mysqli, $token)) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$id = (int)($body['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'Bad request']); exit; }

// Optional: prevent deleting yourself (by token)
// (Uncomment if you add self id lookup)
// if (getIdByToken($mysqli,$token) === $id) { http_response_code(400); echo json_encode(['error'=>'Cannot delete self']); exit; }

$stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
if (!$stmt) { http_response_code(500); echo json_encode(['error'=>'Prepare']); exit; }
$stmt->bind_param('i', $id);
if (!$stmt->execute()) { http_response_code(500); echo json_encode(['error'=>'Execute']); exit; }
$stmt->close();

echo json_encode(['ok'=>true]);
exit;

function isAdmin(mysqli $db, string $token): bool {
  if ($token === '') return false;
  $sql = "SELECT 1 FROM users WHERE BINARY token = ? AND loggedin = 1 AND Perms = 'Admin' AND Fired = 0 LIMIT 1";
  $st = $db->prepare($sql); if(!$st) return false;
  $st->bind_param('s', $token); $st->execute();
  $ok = (bool)$st->get_result()->fetch_row(); $st->close();
  return $ok;
}
// function getIdByToken(mysqli $db, string $tok): ?int { ... }

<?php
header('Content-Type: application/json');
include("configBackEnd.php");

$mysqli = new mysqli($servername, $username, $password, $dbnameUsr);
if ($mysqli->connect_errno) { http_response_code(500); echo json_encode(['error'=>'DB connect']); exit; }

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$token = $body['Token'] ?? '';
if (!isAdmin($mysqli, $token)) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$id    = (int)($body['id'] ?? 0);
$field = (string)($body['field'] ?? '');
$value = $body['value'] ?? null;

if ($id <= 0 || $field === '') { http_response_code(400); echo json_encode(['error'=>'Bad request']); exit; }

$ALLOWED = ['FirstName','LastName','Perms','Fired'];
if (!in_array($field, $ALLOWED, true)) { http_response_code(400); echo json_encode(['error'=>'Field not allowed']); exit; }

if ($field === 'Perms') {
  $ok = in_array($value, ['Admin','Maintenance','Driver','Viewer'], true);
  if (!$ok) { http_response_code(400); echo json_encode(['error'=>'Invalid Perms']); exit; }
}
if ($field === 'Fired') {
  $value = (int)$value ? 1 : 0;
}

$sql = "UPDATE users SET $field = ? WHERE id = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) { http_response_code(500); echo json_encode(['error'=>'Prepare']); exit; }

if ($field === 'Fired') {
  $stmt->bind_param('ii', $value, $id);
} else {
  $val = (string)$value;
  // name length limits (tweak as needed)
  if (($field === 'FirstName' || $field === 'LastName') && (strlen($val) < 1 || strlen($val) > 64)) {
    http_response_code(400); echo json_encode(['error'=>'Name length']); exit;
  }
  $stmt->bind_param('si', $val, $id);
}

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

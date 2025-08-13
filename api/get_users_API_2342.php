<?php
header('Content-Type: application/json');
include("configBackEnd.php");

$mysqli = new mysqli($servername, $username, $password, $dbnameUsr);
if ($mysqli->connect_errno) { http_response_code(500); echo json_encode(['error'=>'DB connect']); exit; }

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$callerUser = $body['Username'] ?? '';
$callerTok  = $body['Token']    ?? '';

if (!isAdmin($mysqli, $callerTok)) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$q      = trim((string)($body['q'] ?? ''));
$perms  = trim((string)($body['perms'] ?? ''));
$status = trim((string)($body['status'] ?? ''));
$page   = max(1, (int)($body['page'] ?? 1));
$size   = min(200, max(1, (int)($body['pageSize'] ?? 25)));
$sortKey= (string)($body['sortKey'] ?? 'id');
$sortDir= strtolower((string)($body['sortDir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

$SORT_WHITELIST = ['id','UserUID','FirstName','LastName','Perms','last_login'];
if (!in_array($sortKey, $SORT_WHITELIST, true)) $sortKey = 'id';

$where = [];
$params = [];
$types  = '';

if ($q !== '') {
  $where[] = "(id = ? OR UserUID LIKE CONCAT('%',?,'%') OR FirstName LIKE CONCAT('%',?,'%') OR LastName LIKE CONCAT('%',?,'%'))";
  $params[] = (int)$q; $types .= 'i';       // id match
  $params[] = $q;       $types .= 's';      // uid
  $params[] = $q;       $types .= 's';      // first
  $params[] = $q;       $types .= 's';      // last
}
if ($perms !== '') {
  $where[] = "Perms = ?";
  $params[] = $perms; $types .= 's';
}
if ($status !== '') {
  if ($status === 'active')   { $where[] = "Fired = 0"; }
  if ($status === 'fired')    { $where[] = "Fired = 1"; }
  if ($status === 'loggedin') { $where[] = "loggedin = 1"; }
  if ($status === 'loggedout'){ $where[] = "loggedin = 0"; }
}

$sql = "SELECT id, UserUID, FirstName, LastName, Perms, loggedin, Fired, last_login
        FROM users";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY $sortKey $sortDir LIMIT ? OFFSET ?";

$params[] = $size; $types .= 'i';
$params[] = ($page - 1) * $size; $types .= 'i';

$stmt = $mysqli->prepare($sql);
if (!$stmt) { http_response_code(500); echo json_encode(['error'=>'Prepare']); exit; }
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['rows'=>$rows]);
exit;

/* ---------- helpers ---------- */
function isAdmin(mysqli $db, string $token): bool {
  if ($token === '') return false;
  $sql = "SELECT 1 FROM users WHERE BINARY token = ? AND loggedin = 1 AND Perms = 'Admin' AND Fired = 0 LIMIT 1";
  $st = $db->prepare($sql);
  if (!$st) return false;
  $st->bind_param('s', $token);
  $st->execute();
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

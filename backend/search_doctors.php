<?php
require_once __DIR__ . "/db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  http_response_code(405);
  echo json_encode(["ok"=>false,"message"=>"Method not allowed"]);
  exit;
}

$q = trim($_GET["q"] ?? "");
$spec = trim($_GET["spec"] ?? "");

$sql = "SELECT id, name, specialization, experience_years, availability
        FROM doctors
        WHERE status='ACTIVE'";
$params = [];
$types = "";

if ($q !== "") {
  $sql .= " AND name LIKE ?";
  $params[] = "%".$q."%";
  $types .= "s";
}
if ($spec !== "") {
  $sql .= " AND specialization = ?";
  $params[] = $spec;
  $types .= "s";
}

$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while($row = $res->fetch_assoc()){
  $rows[] = $row;
}

echo json_encode(["ok"=>true, "doctors"=>$rows]);
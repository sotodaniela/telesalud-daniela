<?php
header('Content-Type: application/json');

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

$user_id = intval($_GET['user_id'] ?? 0);

$all_modules = [
    'dashboard' => 1,
    'patients' => 1,
    'schedule' => 1,
    'teleconsulta' => 1,
    'clinical_history' => 1,
    'users' => 1
];

$permissions = $all_modules;
$result = $conn->query("SELECT module_key, is_enabled FROM user_module_permissions WHERE user_id = $user_id");
while ($row = $result->fetch_assoc()) {
    $permissions[$row['module_key']] = intval($row['is_enabled']);
}

echo json_encode($permissions);
$conn->close();

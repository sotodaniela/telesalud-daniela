<?php
// Fix session compatibility for old files
$oldFiles = [
    'patients.php',
    'edit_patient.php',
    'new_consultation.php',
    'join_consultation.php',
    'teleconsulta.php',
    'teleconsulta_session.php',
    'test_token.php',
    'clinical_history_list.php',
    'debug_history.php',
    'save_clinical_history.php',
    'save_recording.php',
    'update_consultation.php',
    'consultation_detail.php',
    'watch_recording.php'
];

$conn = new mysqli('telehealth-db', 'telehealth', 'telehealth123', 'telehealth');

foreach ($oldFiles as $file) {
    $path = __DIR__ . '/www/' . $file;
    if (!file_exists($path)) continue;
    
    $content = file_get_contents($path);
    
    // Replace session check
    $content = str_replace(
        "if (!isset(\$_SESSION['doctor_id'])) {",
        "if (!isset(\$_SESSION['user_id']) && !isset(\$_SESSION['doctor_id'])) {",
        $content
    );
    
    // Replace variable assignments
    $content = str_replace(
        '$doctor_id = $_SESSION[\'doctor_id\'];',
        '$doctor_id = $_SESSION[\'user_id\'] ?? $_SESSION[\'doctor_id\'] ?? null;',
        $content
    );
    
    $content = str_replace(
        '$doctor_name = $_SESSION[\'doctor_name\'];',
        '$doctor_name = $_SESSION[\'user_name\'] ?? $_SESSION[\'doctor_name\'] ?? \'\';',
        $content
    );
    
    file_put_contents($path, $content);
    echo "Fixed: $file\n";
}

echo "Done!";

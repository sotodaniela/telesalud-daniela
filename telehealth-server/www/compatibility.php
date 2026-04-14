<?php
// Compatibility layer for old session variables
// This file should be included at the top of files that need backward compatibility

if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect
    return;
}

// Set backward compatible variables if not already set
if (!isset($_SESSION['user_id']) && isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['user_id'];
}
if (!isset($_SESSION['doctor_name']) && isset($_SESSION['user_name'])) {
    $_SESSION['doctor_name'] = $_SESSION['user_name'];
}
if (!isset($_SESSION['doctor_specialty']) && isset($_SESSION['user_specialty'])) {
    $_SESSION['doctor_specialty'] = $_SESSION['user_specialty'];
}

// For doctors, user_id = doctor_id
// For admin/nurse/assistant, they can access all records
$doctor_id = ($_SESSION['user_role'] === 'doctor') ? $_SESSION['user_id'] : null;
$doctor_name = $_SESSION['user_name'] ?? '';

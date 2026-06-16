<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode([]);
    exit;
}

$theater_id = isset($_GET['theater_id']) ? (int)$_GET['theater_id'] : 0;

if ($theater_id > 0) {
    $sql = "SELECT hall_id, hall_name, total_seats FROM halls WHERE theater_id = ? ORDER BY hall_name";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $theater_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $halls = [];
    while ($row = $result->fetch_assoc()) {
        $halls[] = $row;
    }
    
    echo json_encode($halls);
} else {
    echo json_encode([]);
}
?>
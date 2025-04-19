<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'API routing is working correctly',
    'timestamp' => date('Y-m-d H:i:s')
]);

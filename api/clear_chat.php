<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/db.php';
require_once '../includes/auth.php';

if (!isLogged()) {
    echo json_encode(['status' => 'error', 'mensaje' => 'No autorizado']);
    exit;
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
$conversacion_id = $input['conversacion_id'] ?? null;

if (!$conversacion_id) {
    echo json_encode(['status' => 'error', 'mensaje' => 'ID de conversación requerido']);
    exit;
}

try {
    // Borrado lógico de los mensajes de esta conversación específica
    $stmt = $pdo->prepare("UPDATE mensajes_aura SET activo = 0 WHERE conversacion_id = ?");
    $stmt->execute([$conversacion_id]);

    echo json_encode(['status' => 'success']);
}
catch (Exception $e) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Error de BD: ' . $e->getMessage()]);
}
?>

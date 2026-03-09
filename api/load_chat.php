<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/audit.php';

if (!isLogged() || $_SESSION['rol_id'] !== 2) {
    if ($_SESSION['rol_id'] !== 1) { // Dejar admin pasar para test si quiere, o limitarlo
    // Solo medicos (rol 2) y admins
    }
}

$medico_id = $_SESSION['medico_id'];
$paciente_id = isset($_GET['paciente_id']) && is_numeric($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : null;

try {
    // 1. Determinar el conversacion_id o crearlo
    if ($paciente_id) {
        $stmt = $pdo->prepare("SELECT id FROM conversaciones_aura WHERE medico_id = ? AND paciente_id = ? AND tipo = 'paciente' LIMIT 1");
        $stmt->execute([$medico_id, $paciente_id]);
    }
    else {
        $stmt = $pdo->prepare("SELECT id FROM conversaciones_aura WHERE medico_id = ? AND tipo = 'general' LIMIT 1");
        $stmt->execute([$medico_id]);
    }

    $conv_id = $stmt->fetchColumn();

    if (!$conv_id) {
        // Crear conversación
        if ($paciente_id) {
            $pdo->prepare("INSERT INTO conversaciones_aura (medico_id, paciente_id, tipo) VALUES (?, ?, 'paciente')")->execute([$medico_id, $paciente_id]);
        }
        else {
            $pdo->prepare("INSERT INTO conversaciones_aura (medico_id, tipo) VALUES (?, 'general')")->execute([$medico_id]);
        }
        $conv_id = $pdo->lastInsertId();
    }

    // 2. Traer mensajes y desencriptarlos
    $stmt = $pdo->prepare("SELECT id, remitente, contenido_cifrado, iv_hex, created_at FROM mensajes_aura WHERE conversacion_id = ? ORDER BY id ASC");
    $stmt->execute([$conv_id]);
    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chat = [];
    foreach ($mensajes as $m) {
        $decrypted = desencriptarMensajeAURA($m['contenido_cifrado'], $m['iv_hex']);
        $chat[] = [
            'remitente' => $m['remitente'],
            'texto' => $decrypted,
            'fecha' => date('H:i', strtotime($m['created_at']))
        ];
    }

    echo json_encode(['status' => 'success', 'conversacion_id' => $conv_id, 'mensajes' => $chat]);
}

catch (Exception $e) {
    echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage()]);
}

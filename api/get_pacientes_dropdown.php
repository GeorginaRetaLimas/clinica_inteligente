<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['medico_id'])) {
    echo json_encode([]);
    exit;
}

require_once '../includes/db.php';

$medico_id = $_SESSION['medico_id'];
$op = $_GET['op'] ?? '';
$isAdmin = ($_SESSION['rol_id'] == 1);

try {
    if ($op === 'REGISTRAR_EXPEDIENTE') {
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT DISTINCT p.id, p.nombre, p.apellido_paterno FROM pacientes p INNER JOIN citas c ON p.id = c.paciente_id WHERE p.activo = 1 AND c.estado IN ('programada','pendiente') ORDER BY p.nombre ASC");
            $stmt->execute();
        }
        else {
            $stmt = $pdo->prepare("SELECT DISTINCT p.id, p.nombre, p.apellido_paterno FROM pacientes p INNER JOIN citas c ON p.id = c.paciente_id WHERE p.activo = 1 AND c.estado IN ('programada','pendiente') AND c.medico_id = ? ORDER BY p.nombre ASC");
            $stmt->execute([$medico_id]);
        }
    }
    else {
        // Citas u otros
        $stmt = $pdo->prepare("SELECT id, nombre, apellido_paterno FROM pacientes WHERE activo = 1 ORDER BY nombre ASC");
        $stmt->execute();
    }

    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($pacientes);
}
catch (PDOException $e) {
    echo json_encode([]);
}

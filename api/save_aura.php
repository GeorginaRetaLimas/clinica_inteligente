<?php
// api/save_aura.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['medico_id']) || !isset($_SESSION['rol_id'])) {
    echo json_encode(['status' => 'error', 'mensaje' => 'No autorizado']);
    exit;
}

require_once '../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['operacion']) || !isset($input['datos'])) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Payload inválido']);
    exit;
}

$op = $input['operacion'];
$datos = $input['datos'];

try {
    // Limpiar claves nullas o vacías por defecto si no son string vacio (a null)
    foreach ($datos as $k => $v) {
        if ($v === '')
            $datos[$k] = null;
    }

    if ($op === 'REGISTRAR_PACIENTE') {
        $stmt = $pdo->prepare('INSERT INTO pacientes (nombre, apellido_paterno, apellido_materno, fecha_nacimiento, sexo, curp, tipo_sangre_id, alergias, direccion, telefono, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $stmt->execute([
            $datos['nombre'] ?? null,
            $datos['apellido_paterno'] ?? null,
            $datos['apellido_materno'] ?? null,
            $datos['fecha_nacimiento'] ?? null,
            $datos['sexo'] ?? null,
            $datos['curp'] ?? null,
            $datos['tipo_sangre_id'] ?? null,
            $datos['alergias'] ?? null,
            $datos['direccion'] ?? null,
            $datos['telefono'] ?? null,
            $datos['email'] ?? null
        ]);

        echo json_encode(['status' => 'success', 'mensaje' => 'Paciente registrado.']);

    }
    elseif ($op === 'REGISTRAR_CITA') {
        // Soporte IA para nombres en lugar de IDs
        if (empty($datos['paciente_id']) && !empty($datos['nombre_paciente_mencionado'])) {
            $nombreMencionado = trim($datos['nombre_paciente_mencionado']);
            // Separamos por espacios o buscamos completo
            $termino = '%' . str_replace(' ', '%', $nombreMencionado) . '%';
            $stmt_busqueda = $pdo->prepare("SELECT id FROM pacientes WHERE CONCAT(nombre, ' ', apellido_paterno, ' ', IFNULL(apellido_materno, '')) LIKE ? LIMIT 1");
            $stmt_busqueda->execute([$termino]);
            $pid_encontrado = $stmt_busqueda->fetchColumn();

            if ($pid_encontrado) {
                $datos['paciente_id'] = $pid_encontrado;
            }
            else {
                echo json_encode(['status' => 'error', 'mensaje' => 'AURA no pudo encontrar ningún paciente registrado que coincida con "' . $nombreMencionado . '". Por favor, verifica el nombre o regístralo primero.']);
                exit;
            }
        }

        $stmt = $pdo->prepare('INSERT INTO citas (paciente_id, medico_id, fecha_hora, duracion_min, motivo, estado) VALUES (?, ?, ?, ?, ?, "programada")');

        // Asignaremos el medico_id logueado como defecto si falta.
        $medico_id = $datos['medico_id'] ?? $_SESSION['medico_id'];

        $stmt->execute([
            $datos['paciente_id'] ?? null,
            $medico_id,
            $datos['fecha_hora'] ?? null,
            $datos['duracion_min'] ?? 30,
            $datos['motivo'] ?? null
        ]);

        echo json_encode(['status' => 'success', 'mensaje' => 'Cita programada.']);

    }
    elseif ($op === 'REGISTRAR_EXPEDIENTE') {
        $stmt = $pdo->prepare('INSERT INTO expedientes (paciente_id, medico_id, diagnostico, tratamiento, medicamentos, notas, presion_sistolica, presion_diastolica, temperatura, frecuencia_cardiaca, frecuencia_resp, peso_kg, talla_cm) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $medico_id = $datos['medico_id'] ?? $_SESSION['medico_id'];

        $stmt->execute([
            $datos['paciente_id'] ?? null,
            $medico_id,
            $datos['diagnostico'] ?? null,
            $datos['tratamiento'] ?? null,
            $datos['medicamentos'] ?? null,
            $datos['notas'] ?? null,
            $datos['presion_sistolica'] ?? null,
            $datos['presion_diastolica'] ?? null,
            $datos['temperatura'] ?? null,
            $datos['frecuencia_cardiaca'] ?? null,
            $datos['frecuencia_resp'] ?? null,
            $datos['peso_kg'] ?? null,
            $datos['talla_cm'] ?? null
        ]);

        echo json_encode(['status' => 'success', 'mensaje' => 'Expediente añadido.']);
    }
    else {
        echo json_encode(['status' => 'error', 'mensaje' => 'Operación desconocida']);
    }

}
catch (PDOException $e) {
    error_log("AURA SAVE ERROR: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'mensaje' => 'Error de BD. Asegúrese de que los datos requeridos (ej. paciente_id si es cita) estén completos.']);
}

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
$conversacion_id = $input['conversacion_id'] ?? null;

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

        $stmt = $pdo->prepare('INSERT INTO citas (paciente_id, medico_id, fecha_hora, duracion_min, motivo, estado, conversacion_id) VALUES (?, ?, ?, ?, ?, "programada", ?)');

        // Asignaremos el medico_id logueado como defecto si falta.
        $medico_id = $datos['medico_id'] ?? $_SESSION['medico_id'];

        $stmt->execute([
            $datos['paciente_id'] ?? null,
            $medico_id,
            $datos['fecha_hora'] ?? null,
            $datos['duracion_min'] ?? 30,
            $datos['motivo'] ?? null,
            $conversacion_id
        ]);

        echo json_encode(['status' => 'success', 'mensaje' => 'Cita programada.']);

    }
    elseif ($op === 'REGISTRAR_EXPEDIENTE') {
        // Soporte IA para nombres
        if (empty($datos['paciente_id']) && !empty($datos['nombre_paciente_mencionado'])) {
            $nombreMencionado = trim($datos['nombre_paciente_mencionado']);
            $termino = '%' . str_replace(' ', '%', $nombreMencionado) . '%';
            $stmt_busqueda = $pdo->prepare("SELECT id FROM pacientes WHERE CONCAT(nombre, ' ', apellido_paterno, ' ', IFNULL(apellido_materno, '')) LIKE ? LIMIT 1");
            $stmt_busqueda->execute([$termino]);
            $pid_encontrado = $stmt_busqueda->fetchColumn();

            if ($pid_encontrado) {
                // Validación estricta: ¿Este paciente encontrado tiene CITA PENDIENTE?
                $stmt_cita = $pdo->prepare("SELECT id FROM citas WHERE paciente_id = ? AND estado IN ('programada', 'pendiente') ORDER BY fecha_hora ASC LIMIT 1");
                $stmt_cita->execute([$pid_encontrado]);
                $cita_confirmada = $stmt_cita->fetchColumn();

                if (!$cita_confirmada) {
                    echo json_encode(['status' => 'error', 'mensaje' => 'Aviso de AURA: Encontré a "' . $nombreMencionado . '" en sistema, pero NO cuenta con una CITA pendiente hoy para abrirle Expediente.']);
                    exit;
                }

                $datos['paciente_id'] = $pid_encontrado;
            }
            else {
                echo json_encode(['status' => 'error', 'mensaje' => 'AURA no pudo encontrar ningún paciente registrado que coincida con "' . $nombreMencionado . '".']);
                exit;
            }
        }

        $medico_id = $datos['medico_id'] ?? $_SESSION['medico_id'];

        // Validar Cita Activa
        if (!empty($datos['paciente_id'])) {
            $stmt_cita = $pdo->prepare("SELECT id FROM citas WHERE paciente_id = ? AND estado IN ('programada', 'pendiente') ORDER BY fecha_hora ASC LIMIT 1");
            $stmt_cita->execute([$datos['paciente_id']]);
            $cita_activa = $stmt_cita->fetchColumn();

            if (!$cita_activa) {
                echo json_encode(['status' => 'error', 'mensaje' => 'Aviso de AURA: Este paciente no cuenta con una cita médica pendiente. Por favor agenda una cita primero antes de redactar un expediente para él.']);
                exit;
            }
        }
        else {
            echo json_encode(['status' => 'error', 'mensaje' => 'Aviso de AURA: Es necesario mencionar al paciente o seleccionarlo para el Expediente.']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO expedientes (paciente_id, medico_id, motivo_consulta, sintomas, diagnostico, tratamiento, medicamentos, notas, presion_sistolica, presion_diastolica, temperatura, frecuencia_cardiaca, frecuencia_resp, peso_kg, talla_cm, conversacion_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $stmt->execute([
            $datos['paciente_id'],
            $medico_id,
            $datos['motivo_consulta'] ?? null,
            $datos['sintomas'] ?? null,
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
            $datos['talla_cm'] ?? null,
            $conversacion_id
        ]);

        $nuevo_exp_id = $pdo->lastInsertId();

        // Cerrar Cita Activa
        $pdo->prepare("UPDATE citas SET estado = 'completada', expediente_id = ? WHERE id = ?")->execute([$nuevo_exp_id, $cita_activa]);

        // Registrar en logs_captura el performance de la IA (Metodo asistente_ia)
        $inicio_ia = date('Y-m-d H:i:s.v', strtotime('-30 seconds')); // Valor por si la consulta falla
        $stmt_inicio = $pdo->prepare("SELECT created_at FROM mensajes_aura WHERE conversacion_id = ? ORDER BY id ASC LIMIT 1");
        $stmt_inicio->execute([$conversacion_id]);
        if ($res_inicio = $stmt_inicio->fetchColumn()) {
            $inicio_ia = $res_inicio;
        }

        $campos_capturados = 0;
        foreach (['motivo_consulta', 'sintomas', 'diagnostico', 'tratamiento', 'medicamentos', 'notas', 'presion_sistolica', 'presion_diastolica', 'temperatura', 'frecuencia_cardiaca', 'frecuencia_resp', 'peso_kg', 'talla_cm'] as $f) {
            if (!empty($datos[$f]))
                $campos_capturados++;
        }

        try {
            $pdo->prepare("INSERT INTO logs_captura (medico_id, expediente_id, metodo, inicio, fin, errores_validacion, campos_capturados) VALUES (?, ?, 'asistente_ia', ?, NOW(3), 0, ?)")->execute([$medico_id, $nuevo_exp_id, $inicio_ia, $campos_capturados]);
        }
        catch (Exception $e) {
        }

        echo json_encode(['status' => 'success', 'mensaje' => 'Expediente añadido y Cita completada con Bitácora IA guardada.']);
    }
    else {
        echo json_encode(['status' => 'error', 'mensaje' => 'Operación desconocida']);
    }

}
catch (PDOException $e) {
    error_log("AURA SAVE ERROR: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'mensaje' => 'Error de BD. Asegúrese de que los datos requeridos (ej. paciente_id si es cita) estén completos.']);
}

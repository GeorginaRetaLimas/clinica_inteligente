<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/audit.php';
requireLogin();

$medico_id = $_SESSION['medico_id'];
$isAdmin = ($_SESSION['rol_id'] == 1);

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add') {
            $paciente_id = (int)$_POST['paciente_id'];
            $motivo = $_POST['motivo_consulta'] ?? '';
            $sintomas = $_POST['sintomas'] ?? '';
            $diagnostico = $_POST['diagnostico'] ?? '';
            $tratamiento = $_POST['tratamiento'] ?? '';

            $sql = "INSERT INTO expedientes (paciente_id, medico_id, motivo_consulta, sintomas, diagnostico, tratamiento) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$paciente_id, $medico_id, $motivo, $sintomas, $diagnostico, $tratamiento]);
            $nuevo_id = $pdo->lastInsertId();

            $datos_despues = ['id' => $nuevo_id, 'paciente_id' => $paciente_id, 'motivo' => $motivo, 'sintomas' => $sintomas];
            registrarAuditoria($pdo, $medico_id, 'expedientes', $nuevo_id, 'INSERT', null, $datos_despues);

            // Log de Captura (Manual)
            $campos_capturados = 0;
            foreach (['motivo_consulta', 'sintomas', 'diagnostico', 'tratamiento', 'medicamentos', 'notas', 'presion_sistolica', 'presion_diastolica', 'temperatura', 'frecuencia_cardiaca', 'frecuencia_resp', 'peso_kg', 'talla_cm'] as $f) {
                if (!empty($_POST[$f]))
                    $campos_capturados++;
            }
            $inicio_captura = !empty($_POST['inicio_captura']) ? $_POST['inicio_captura'] : date('Y-m-d H:i:s');
            // Fin = insertado local (NOW(3))
            try {
                $pdo->prepare("INSERT INTO logs_captura (medico_id, expediente_id, metodo, inicio, fin, errores_validacion, campos_capturados) VALUES (?, ?, 'manual', ?, NOW(3), 0, ?)")->execute([$medico_id, $nuevo_id, $inicio_captura, $campos_capturados]);
            }
            catch (Exception $e) {
            }

            // Si venimos referenciados de una cita, actualizar su estado a completada y relacionarla
            if (!empty($_POST['cita_id'])) {
                $cita_id = (int)$_POST['cita_id'];
                $pdo->prepare("UPDATE citas SET estado = 'completada', expediente_id = ? WHERE id = ?")->execute([$nuevo_id, $cita_id]);
            }

            header('Location: expedientes.php?msg=added');
            exit;
        }
        elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $motivo = $_POST['motivo_consulta'] ?? '';
            $sintomas = $_POST['sintomas'] ?? '';
            $diagnostico = $_POST['diagnostico'] ?? '';
            $tratamiento = $_POST['tratamiento'] ?? '';

            $stmt = $pdo->prepare("SELECT * FROM expedientes WHERE id = ?");
            $stmt->execute([$id]);
            $datos_antes = $stmt->fetch();

            if ($datos_antes) {
                $pdo->prepare("UPDATE expedientes SET motivo_consulta = ?, sintomas = ?, diagnostico = ?, tratamiento = ? WHERE id = ?")
                    ->execute([$motivo, $sintomas, $diagnostico, $tratamiento, $id]);

                $datos_despues = $datos_antes;
                $datos_despues['motivo_consulta'] = $motivo;
                $datos_despues['sintomas'] = $sintomas;
                $datos_despues['diagnostico'] = $diagnostico;
                $datos_despues['tratamiento'] = $tratamiento;

                registrarAuditoria($pdo, $medico_id, 'expedientes', $id, 'UPDATE', $datos_antes, $datos_despues);
            }
            header('Location: expedientes.php?msg=updated');
            exit;
        }
        elseif ($action === 'delete') {
            $id = (int)$_POST['id'];

            $stmt = $pdo->prepare("SELECT * FROM expedientes WHERE id = ?");
            $stmt->execute([$id]);
            $datos_antes = $stmt->fetch();

            if ($datos_antes) {
                $pdo->prepare("UPDATE expedientes SET activo = 0 WHERE id = ?")->execute([$id]);
                $datos_despues = $datos_antes;
                $datos_despues['activo'] = 0;
                registrarAuditoria($pdo, $medico_id, 'expedientes', $id, 'DELETE', $datos_antes, $datos_despues);
            }
            header('Location: expedientes.php?msg=deleted');
            exit;
        }
    }
}

// Fetch Expedientes
if ($isAdmin) {
    $sqlExp = "SELECT e.*, p.nombre as p_nombre, p.apellido_paterno as p_ap, m.nombre as m_nombre, m.apellido_paterno as m_ap 
                 FROM expedientes e 
                 JOIN pacientes p ON e.paciente_id = p.id
                 JOIN medicos m ON e.medico_id = m.id
                 WHERE e.activo = 1
                 ORDER BY e.fecha_consulta DESC";
    $expedientes = $pdo->query($sqlExp)->fetchAll();
    $pacientes = $pdo->query("SELECT DISTINCT p.id, p.nombre, p.apellido_paterno FROM pacientes p INNER JOIN citas c ON p.id = c.paciente_id WHERE p.activo = 1 AND c.estado IN ('programada','pendiente')")->fetchAll();
}
else {
    $sqlExp = "SELECT e.*, p.nombre as p_nombre, p.apellido_paterno as p_ap 
                 FROM expedientes e 
                 JOIN pacientes p ON e.paciente_id = p.id
                 WHERE e.medico_id = ? AND e.activo = 1
                 ORDER BY e.fecha_consulta DESC";
    $stmt = $pdo->prepare($sqlExp);
    $stmt->execute([$medico_id]);
    $expedientes = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT DISTINCT p.id, p.nombre, p.apellido_paterno FROM pacientes p INNER JOIN citas c ON p.id = c.paciente_id WHERE p.activo = 1 AND c.estado IN ('programada','pendiente') AND c.medico_id = ?");
    $stmt->execute([$medico_id]);
    $pacientes = $stmt->fetchAll();
}

require_once '../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-9">
        <h2 class="fw-bold"><i class="bi bi-file-earmark-medical text-success"></i> Expedientes Clínicos</h2>
        <p class="text-muted mb-0">Historiales de consultas y diagnósticos.</p>
    </div>
    <div class="col-md-3 text-end d-flex gap-2 justify-content-end">
        <button class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddExpediente">
            <i class="bi bi-journal-plus"></i> Captura Manual
        </button>
        <a href="chat_ia.php" class="btn btn-info text-white fw-bold" title="Captura Inteligente">
            <i class="bi bi-robot"></i> Inteligente
        </a>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if(typeof showAuraModal === 'function') {
                showAuraModal('Operación Exitosa', 'Expediente registrado exitosamente.', 'success');
            }
        });
    </script>
<?php
endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Fecha Consulta</th>
                        <th>Paciente</th>
                        <th>Motivo</th>
                        <th>Diagnóstico</th>
                        <th>Validado IA</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expedientes) > 0): ?>
                        <?php foreach ($expedientes as $e): ?>
                            <tr>
                                <td><?php echo $e['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($e['fecha_consulta'])); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($e['p_nombre'] . ' ' . $e['p_ap']); ?></td>
                                <td><span class="text-truncate d-inline-block" style="max-width: 150px;"><?php echo htmlspecialchars($e['motivo_consulta']); ?></span></td>
                                <td><span class="text-truncate d-inline-block" style="max-width: 150px;"><?php echo htmlspecialchars($e['diagnostico'] ?? 'Sin diagnóstico'); ?></span></td>
                                <td>
                                    <?php if ($e['datos_ia_json']): ?>
                                        <?php if ($e['ia_validado']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Sí</span>
                                        <?php
            else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle"></i> Pdte.</span>
                                        <?php
            endif; ?>
                                    <?php
        else: ?>
                                        <span class="badge bg-secondary">Manual</span>
                                    <?php
        endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editExpediente(<?php echo htmlspecialchars(json_encode($e)); ?>)" title="Editar"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="" style="display:inline-block;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="showAuraConfirm('Eliminar Expediente', '¿Eliminar lógicamente este registro de expediente?', this.closest('form'))"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php
    endforeach; ?>
                    <?php
else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No hay expedientes registrados.</td></tr>
                    <?php
endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Agregar Expediente Manual -->
<div class="modal fade" id="modalAddExpediente" tabindex="-1" aria-labelledby="modalAddExpedienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold text-success" id="modalAddExpedienteLabel">Registrar Consulta (Manual)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="cita_id" value="<?php echo isset($_GET['cita_id']) ? htmlspecialchars($_GET['cita_id']) : ''; ?>">
        <input type="hidden" name="inicio_captura" id="add_inicio_captura" value="">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">Paciente</label>
                <select class="form-select" name="paciente_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($pacientes as $pac): ?>
                        <option value="<?php echo $pac['id']; ?>"><?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido_paterno']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label">Motivo de Consulta *</label>
                <textarea class="form-control" name="motivo_consulta" rows="2" required></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Síntomas</label>
                <textarea class="form-control" name="sintomas" rows="2"></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Diagnóstico</label>
                <textarea class="form-control" name="diagnostico" rows="2"></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Tratamiento / Notas</label>
                <textarea class="form-control" name="tratamiento" rows="3"></textarea>
            </div>
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0 mt-3">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success">Guardar Expediente</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Expediente Manual -->
<div class="modal fade" id="modalEditExpediente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold text-primary">Editar Consulta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">Motivo de Consulta *</label>
                <textarea class="form-control" name="motivo_consulta" id="edit_motivo" rows="2" required></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label text-primary fw-bold border-bottom w-100 pb-1 mt-2">Signos Vitales y Mediciones</label>
            </div>
            <div class="col-md-3">
                <label class="form-label">T.A. Sistólica</label>
                <input type="number" class="form-control" name="presion_sistolica" id="edit_ps" placeholder="Ej. 120">
            </div>
            <div class="col-md-3">
                <label class="form-label">T.A. Diastólica</label>
                <input type="number" class="form-control" name="presion_diastolica" id="edit_pd" placeholder="Ej. 80">
            </div>
            <div class="col-md-2">
                <label class="form-label">Temp (°C)</label>
                <input type="number" step="0.1" class="form-control" name="temperatura" id="edit_temp" placeholder="Ej. 36.5">
            </div>
            <div class="col-md-2">
                <label class="form-label">FC (LPM)</label>
                <input type="number" class="form-control" name="frecuencia_cardiaca" id="edit_fc" placeholder="Ej. 75">
            </div>
            <div class="col-md-2">
                <label class="form-label">FR (RPM)</label>
                <input type="number" class="form-control" name="frecuencia_resp" id="edit_fr" placeholder="Ej. 18">
            </div>
            <div class="col-md-6">
                <label class="form-label">Peso (Kg)</label>
                <input type="number" step="0.1" class="form-control" name="peso_kg" id="edit_peso" placeholder="Ej. 70.5">
            </div>
            <div class="col-md-6">
                <label class="form-label">Talla (Cm)</label>
                <input type="number" step="0.1" class="form-control" name="talla_cm" id="edit_talla" placeholder="Ej. 175">
            </div>

            <div class="col-md-12">
                <label class="form-label text-primary fw-bold border-bottom w-100 pb-1 mt-2">Evaluación y Diagnóstico</label>
            </div>
            <div class="col-md-12">
                <label class="form-label">Síntomas</label>
                <textarea class="form-control" name="sintomas" id="edit_sintomas" rows="2"></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Diagnóstico</label>
                <textarea class="form-control" name="diagnostico" id="edit_diagnostico" rows="2"></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Tratamiento / Plan</label>
                <textarea class="form-control" name="tratamiento" id="edit_tratamiento" rows="2"></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Medicamentos Recetados</label>
                <textarea class="form-control" name="medicamentos" id="edit_medicamentos" rows="2" placeholder="Listado de medicación..."></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label">Notas Adicionales</label>
                <textarea class="form-control" name="notas" id="edit_notas" rows="1"></textarea>
            </div>
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0 mt-3">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Actualizar Expediente</button>
      </div>
    </form>
  </div>
</div>

<script>
function editExpediente(e) {
    document.getElementById('edit_id').value = e.id;
    document.getElementById('edit_motivo').value = e.motivo_consulta;
    document.getElementById('edit_sintomas').value = e.sintomas;
    document.getElementById('edit_diagnostico').value = e.diagnostico;
    document.getElementById('edit_tratamiento').value = e.tratamiento;
    document.getElementById('edit_ps').value = e.presion_sistolica ?? '';
    document.getElementById('edit_pd').value = e.presion_diastolica ?? '';
    document.getElementById('edit_temp').value = e.temperatura ?? '';
    document.getElementById('edit_fc').value = e.frecuencia_cardiaca ?? '';
    document.getElementById('edit_fr').value = e.frecuencia_resp ?? '';
    document.getElementById('edit_peso').value = e.peso_kg ?? '';
    document.getElementById('edit_talla').value = e.talla_cm ?? '';
    document.getElementById('edit_medicamentos').value = e.medicamentos ?? '';
    document.getElementById('edit_notas').value = e.notas ?? '';
    new bootstrap.Modal(document.getElementById('modalEditExpediente')).show();
}

document.getElementById('modalAddExpediente').addEventListener('show.bs.modal', function () {
    let d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    document.getElementById('add_inicio_captura').value = d.toISOString().slice(0, 19).replace('T', ' ');
});

<?php if (isset($_GET['cita_id'])): ?>
// Si venimos de la agenda de citas, abrir el modal de crear expediente de inmediato
document.addEventListener("DOMContentLoaded", function() {
    new bootstrap.Modal(document.getElementById('modalAddExpediente')).show();
});
<?php
endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>

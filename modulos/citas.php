<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/audit.php';
requireLogin();

$medico_id = $_SESSION['medico_id'];
$isAdmin = ($_SESSION['rol_id'] == 1);

// Handle POST request (Create / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add') {
            $paciente_id = (int)$_POST['paciente_id'];
            $fecha = $_POST['fecha_hora'];
            $motivo = $_POST['motivo'];
            $duracion = (int)($_POST['duracion_min'] ?? 30);
            $medico_id_cita = $medico_id;

            $sql = "INSERT INTO citas (paciente_id, medico_id, fecha_hora, duracion_min, motivo) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$paciente_id, $medico_id_cita, $fecha, $duracion, $motivo]);
            $nuevo_id = $pdo->lastInsertId();

            $datos_despues = ['id' => $nuevo_id, 'paciente_id' => $paciente_id, 'fecha' => $fecha, 'motivo' => $motivo];
            registrarAuditoria($pdo, $medico_id, 'citas', $nuevo_id, 'INSERT', null, $datos_despues);

            header('Location: citas.php?msg=added');
            exit;
        }
        elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $estado = $_POST['estado'];
            $fecha = $_POST['fecha_hora'];
            $motivo = $_POST['motivo'];
            $duracion = (int)($_POST['duracion_min'] ?? 30);
            $notas_cancelacion = $_POST['notas_cancelacion'] ?? null;

            $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
            $stmt->execute([$id]);
            $datos_antes = $stmt->fetch();

            if ($datos_antes) {
                $pdo->prepare("UPDATE citas SET fecha_hora = ?, duracion_min = ?, motivo = ?, estado = ?, notas_cancelacion = ? WHERE id = ?")
                    ->execute([$fecha, $duracion, $motivo, $estado, $notas_cancelacion, $id]);
                $datos_despues = $datos_antes;
                $datos_despues['fecha_hora'] = $fecha;
                $datos_despues['duracion_min'] = $duracion;
                $datos_despues['motivo'] = $motivo;
                $datos_despues['estado'] = $estado;
                registrarAuditoria($pdo, $medico_id, 'citas', $id, 'UPDATE', $datos_antes, $datos_despues);
            }
            header('Location: citas.php?msg=updated');
            exit;
        }
        elseif ($action === 'delete') {
            $id = (int)$_POST['id'];

            $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
            $stmt->execute([$id]);
            $datos_antes = $stmt->fetch();

            if ($datos_antes) {
                $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ?")->execute([$id]);
                $datos_despues = $datos_antes;
                $datos_despues['estado'] = 'cancelada';
                registrarAuditoria($pdo, $medico_id, 'citas', $id, 'DELETE', $datos_antes, $datos_despues);
            }
            header('Location: citas.php?msg=deleted');
            exit;
        }
    }
}

// Fetch Citas
if ($isAdmin) {
    $sqlCitas = "SELECT c.*, p.nombre as p_nombre, p.apellido_paterno as p_ap, m.nombre as m_nombre, m.apellido_paterno as m_ap 
                 FROM citas c 
                 JOIN pacientes p ON c.paciente_id = p.id
                 JOIN medicos m ON c.medico_id = m.id
                 ORDER BY c.fecha_hora DESC";
    $citas = $pdo->query($sqlCitas)->fetchAll();

    // Para modal (SOLO PACIENTES ACTIVOS)
    $pacientes = $pdo->query("SELECT id, nombre, apellido_paterno FROM pacientes WHERE activo = 1")->fetchAll();
}
else {
    $sqlCitas = "SELECT c.*, p.nombre as p_nombre, p.apellido_paterno as p_ap 
                 FROM citas c 
                 JOIN pacientes p ON c.paciente_id = p.id
                 WHERE c.medico_id = ? 
                 ORDER BY c.fecha_hora DESC";
    $stmt = $pdo->prepare($sqlCitas);
    $stmt->execute([$medico_id]);
    $citas = $stmt->fetchAll();

    // Para modal (SOLO PACIENTES ACTIVOS)
    $stmt = $pdo->prepare("SELECT p.id, p.nombre, p.apellido_paterno FROM pacientes p INNER JOIN paciente_medico pm ON p.id = pm.paciente_id WHERE pm.medico_id = ? AND p.activo = 1");
    $stmt->execute([$medico_id]);
    $pacientes = $stmt->fetchAll();
}

require_once '../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-9">
        <h2 class="fw-bold"><i class="bi bi-calendar-event text-primary"></i> Agenda de Citas</h2>
        <p class="text-muted mb-0">Gestione las consultas programadas.</p>
    </div>
    <div class="col-md-3 text-end">
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddCita">
            <i class="bi bi-calendar-plus"></i> Nueva Cita
        </button>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if(typeof showAuraModal === 'function') {
                showAuraModal('Operación Exitosa', 'Cita programada exitosamente.', 'success');
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
                        <th>Fecha y Hora</th>
                        <th>Duración</th>
                        <th>Paciente</th>
                        <?php if ($isAdmin): ?><th>Médico</th><?php
endif; ?>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($citas) > 0): ?>
                        <?php foreach ($citas as $c):
        $badge = [
            'pendiente' => 'bg-warning text-dark',
            'confirmada' => 'bg-info text-white',
            'completada' => 'bg-success',
            'cancelada' => 'bg-danger',
            'no_asistio' => 'bg-secondary'
        ];
        $c_badge = $badge[$c['estado']] ?? 'bg-light text-dark';
?>
                            <tr class="<?php echo($c['estado'] === 'cancelada') ? 'opacity-50 bg-light' : ''; ?>">
                                <td><i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i', strtotime($c['fecha_hora'])); ?></td>
                                <td><?php echo $c['duracion_min']; ?> min</td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($c['p_nombre'] . ' ' . $c['p_ap']); ?></td>
                                <?php if ($isAdmin): ?>
                                    <td><?php echo htmlspecialchars($c['m_nombre'] . ' ' . $c['m_ap']); ?></td>
                                <?php
        endif; ?>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 150px;"><?php echo htmlspecialchars($c['motivo'] ?? '-'); ?></span>
                                    <?php if ($c['estado'] === 'cancelada' && !empty($c['notas_cancelacion'])): ?>
                                        <br><small class="text-danger fw-bold"><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($c['notas_cancelacion']); ?></small>
                                    <?php
        endif; ?>
                                </td>
                                <td><span class="badge rounded-pill <?php echo $c_badge; ?>"><?php echo ucfirst(str_replace('_', ' ', $c['estado'])); ?></span></td>
                                <td class="text-end">
                                    <?php if ($c['estado'] === 'pendiente' || $c['estado'] === 'confirmada'): ?>
                                        <a href="expedientes.php?cita_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-success" title="Iniciar Consulta"><i class="bi bi-journal-medical"></i></a>
                                    <?php
        endif; ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editCita(<?php echo htmlspecialchars(json_encode($c)); ?>)" title="Editar"><i class="bi bi-pencil"></i></button>
                                    <?php if ($c['estado'] !== 'cancelada'): ?>
                                    <form method="POST" action="" style="display:inline-block;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Cancelar" onclick="showAuraConfirm('Cancelar Cita', '¿Cancelar esta cita?', this.closest('form'))"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                    <?php
        endif; ?>
                                </td>
                            </tr>
                        <?php
    endforeach; ?>
                    <?php
else: ?>
                        <tr><td colspan="<?php echo $isAdmin ? 6 : 5; ?>" class="text-center py-4 text-muted">No hay citas registradas.</td></tr>
                    <?php
endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Agregar Cita -->
<div class="modal fade" id="modalAddCita" tabindex="-1" aria-labelledby="modalAddCitaLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalAddCitaLabel">Agendar Nueva Cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add">
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
            <div class="col-md-8">
                <label class="form-label">Fecha y Hora</label>
                <input type="datetime-local" class="form-control" name="fecha_hora" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Duración (min)</label>
                <input type="number" class="form-control" name="duracion_min" value="30" min="5" step="5" required>
            </div>
            <div class="col-md-12">
                <label class="form-label">Motivo (Breve)</label>
                <input type="text" class="form-control" name="motivo">
            </div>
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0 mt-3">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar Cita</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Cita -->
<div class="modal fade" id="modalEditCita" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Actualizar Cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3">
            <label>Estado</label>
            <select name="estado" id="edit_estado" class="form-select" required>
                <option value="pendiente">Pendiente</option>
                <option value="confirmada">Confirmada</option>
                <option value="cancelada">Cancelada</option>
                <option value="completada">Completada</option>
                <option value="no_asistio">No Asistió</option>
            </select>
        </div>
        <div class="mb-3">
            <label>Fecha y Hora</label>
            <input type="datetime-local" name="fecha_hora" id="edit_fecha" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Duración (min)</label>
            <input type="number" name="duracion_min" id="edit_duracion" class="form-control" min="5" step="5" required>
        </div>
        <div class="mb-3">
            <label>Motivo</label>
            <input type="text" name="motivo" id="edit_motivo" class="form-control">
        </div>
        <div class="mb-3">
            <label class="text-danger fw-bold">Razón de Cancelación (Opcional)</label>
            <input type="text" name="notas_cancelacion" id="edit_notas" class="form-control" placeholder="Ej. El paciente llamó para posponer...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary text-white">Actualizar</button>
      </div>
    </form>
  </div>
</div>

<script>
function editCita(c) {
    document.getElementById('edit_id').value = c.id;
    document.getElementById('edit_estado').value = c.estado;
    document.getElementById('edit_fecha').value = c.fecha_hora;
    document.getElementById('edit_duracion').value = c.duracion_min;
    document.getElementById('edit_motivo').value = c.motivo;
    document.getElementById('edit_notas').value = c.notas_cancelacion;
    new bootstrap.Modal(document.getElementById('modalEditCita')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>

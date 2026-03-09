<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/audit.php';
requireLogin();

$medico_id = $_SESSION['medico_id'];
$isAdmin = ($_SESSION['rol_id'] == 1);

// Handle POST request Create / Update / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // Datos comnes
        $nombre = $_POST['nombre'] ?? '';
        $ap = $_POST['apellido_paterno'] ?? '';
        $am = $_POST['apellido_materno'] ?? '';
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
        $sexo = $_POST['sexo'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $tipo_sangre_id = !empty($_POST['tipo_sangre']) ? (int)$_POST['tipo_sangre'] : null;

        if ($action === 'add') {
            $sql = "INSERT INTO pacientes (nombre, apellido_paterno, apellido_materno, fecha_nacimiento, sexo, telefono, tipo_sangre_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $ap, $am, $fecha_nacimiento, $sexo, $telefono, $tipo_sangre_id]);
            $nuevo_id = $pdo->lastInsertId();

            if (!$isAdmin) {
                $pdo->prepare("INSERT INTO paciente_medico (paciente_id, medico_id) VALUES (?, ?)")->execute([$nuevo_id, $medico_id]);
            }

            $datos_despues = ['id' => $nuevo_id, 'nombre' => $nombre, 'apellido_paterno' => $ap, 'fecha_nacimiento' => $fecha_nacimiento, 'sexo' => $sexo, 'telefono' => $telefono, 'tipo_sangre_id' => $tipo_sangre_id];
            registrarAuditoria($pdo, $medico_id, 'pacientes', $nuevo_id, 'INSERT', null, $datos_despues);

            header('Location: pacientes.php?msg=added');
            exit;
        }
        elseif ($action === 'edit') {
            $id = (int)$_POST['id'];

            // Obtener antes
            $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
            $stmt->execute([$id]);
            $datos_antes = $stmt->fetch();

            if ($datos_antes) {
                $sql = "UPDATE pacientes SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, fecha_nacimiento = ?, sexo = ?, telefono = ?, tipo_sangre_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $ap, $am, $fecha_nacimiento, $sexo, $telefono, $tipo_sangre_id, $id]);

                $datos_despues = $datos_antes;
                $datos_despues['nombre'] = $nombre;
                $datos_despues['apellido_paterno'] = $ap;
                $datos_despues['apellido_materno'] = $am;
                $datos_despues['fecha_nacimiento'] = $fecha_nacimiento;

                registrarAuditoria($pdo, $medico_id, 'pacientes', $id, 'UPDATE', $datos_antes, $datos_despues);
            }
            header('Location: pacientes.php?msg=updated');
            exit;
        }
        elseif ($action === 'delete') {
            $id = (int)$_POST['id'];

            $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
            $stmt->execute([$id]);
            $datos_antes = $stmt->fetch();

            if ($datos_antes) {
                $pdo->prepare("UPDATE pacientes SET activo = 0 WHERE id = ?")->execute([$id]);

                $datos_despues = $datos_antes;
                $datos_despues['activo'] = 0;

                // Borrado Lógico en Blockchain figurando como DELETE lógico
                registrarAuditoria($pdo, $medico_id, 'pacientes', $id, 'DELETE', $datos_antes, $datos_despues);
            }
            header('Location: pacientes.php?msg=deleted');
            exit;
        }
    }
}

// Fetch Tipos Sangre
$sqlSangre = "SELECT * FROM tipos_sangre";
$tipos_sangre = $pdo->query($sqlSangre)->fetchAll();

// Fetch Pacientes
if ($isAdmin) {
    $sqlPacientes = "SELECT p.*, t.tipo as tipo_sangre FROM pacientes p LEFT JOIN tipos_sangre t ON p.tipo_sangre_id = t.id WHERE p.activo = 1";
    $pacientes = $pdo->query($sqlPacientes)->fetchAll();
}
else {
    $sqlPacientes = "SELECT p.*, t.tipo as tipo_sangre FROM pacientes p 
                     INNER JOIN paciente_medico pm ON p.id = pm.paciente_id 
                     LEFT JOIN tipos_sangre t ON p.tipo_sangre_id = t.id 
                     WHERE pm.medico_id = ? AND p.activo = 1";
    $stmt = $pdo->prepare($sqlPacientes);
    $stmt->execute([$medico_id]);
    $pacientes = $stmt->fetchAll();
}

require_once '../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-9">
        <h2 class="fw-bold"><i class="bi bi-people text-info"></i> Directorio de Pacientes</h2>
        <p class="text-muted mb-0">Gestione la información de sus pacientes.</p>
    </div>
    <div class="col-md-3 text-end">
        <button class="btn btn-info text-white fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddPaciente">
            <i class="bi bi-plus-circle"></i> Nuevo Paciente
        </button>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Paciente agregado exitosamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php
endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Edad</th>
                        <th>Sexo</th>
                        <th>G. Sanguíneo</th>
                        <th>Teléfono</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pacientes) > 0): ?>
                        <?php foreach ($pacientes as $p):
        $edad = date_diff(date_create($p['fecha_nacimiento']), date_create('now'))->y;
?>
                            <tr>
                                <td><?php echo $p['id']; ?></td>
                                <td class="fw-semibold">
                                    <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido_paterno'] . ' ' . $p['apellido_materno']); ?>
                                </td>
                                <td><?php echo $edad; ?> años</td>
                                <td><?php echo htmlspecialchars($p['sexo']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($p['tipo_sangre'] ?? 'N/D'); ?></span></td>
                                <td><?php echo htmlspecialchars($p['telefono']); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editPaciente(<?php echo htmlspecialchars(json_encode($p)); ?>)"><i class="bi bi-pencil"></i></button>
                                    <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('¿Está seguro de eliminar lógicamente este paciente?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php
    endforeach; ?>
                    <?php
else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron pacientes.</td></tr>
                    <?php
endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Agregar Paciente -->
<div class="modal fade" id="modalAddPaciente" tabindex="-1" aria-labelledby="modalAddPacienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalAddPacienteLabel">Agregar Nuevo Paciente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Nombre(s)</label>
                <input type="text" class="form-control" name="nombre" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Apellido Paterno</label>
                <input type="text" class="form-control" name="apellido_paterno" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Apellido Materno</label>
                <input type="text" class="form-control" name="apellido_materno">
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha Nacimiento</label>
                <input type="date" class="form-control" name="fecha_nacimiento" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Sexo</label>
                <select class="form-select" name="sexo" required>
                    <option value="">Seleccione...</option>
                    <option value="M">Masculino</option>
                    <option value="F">Femenino</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo Sangre</label>
                <select class="form-select" name="tipo_sangre">
                    <option value="">Desconocido...</option>
                    <?php foreach ($tipos_sangre as $ts): ?>
                        <option value="<?php echo $ts['id']; ?>"><?php echo htmlspecialchars($ts['tipo']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Teléfono</label>
                <input type="text" class="form-control" name="telefono">
            </div>
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0 mt-3">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-info text-white">Guardar Paciente</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Paciente -->
<div class="modal fade" id="modalEditPaciente" tabindex="-1" aria-labelledby="modalEditPacienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalEditPacienteLabel">Editar Paciente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Nombre(s)</label>
                <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Apellido Paterno</label>
                <input type="text" class="form-control" name="apellido_paterno" id="edit_ap" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Apellido Materno</label>
                <input type="text" class="form-control" name="apellido_materno" id="edit_am">
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha Nacimiento</label>
                <input type="date" class="form-control" name="fecha_nacimiento" id="edit_fecha" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Sexo</label>
                <select class="form-select" name="sexo" id="edit_sexo" required>
                    <option value="M">Masculino</option>
                    <option value="F">Femenino</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo Sangre</label>
                <select class="form-select" name="tipo_sangre" id="edit_sangre">
                    <option value="">Desconocido...</option>
                    <?php foreach ($tipos_sangre as $ts): ?>
                        <option value="<?php echo $ts['id']; ?>"><?php echo htmlspecialchars($ts['tipo']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Teléfono</label>
                <input type="text" class="form-control" name="telefono" id="edit_tel">
            </div>
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0 mt-3">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary text-white">Actualizar Paciente</button>
      </div>
    </form>
  </div>
</div>

<script>
function editPaciente(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_nombre').value = p.nombre;
    document.getElementById('edit_ap').value = p.apellido_paterno;
    document.getElementById('edit_am').value = p.apellido_materno;
    document.getElementById('edit_fecha').value = p.fecha_nacimiento;
    document.getElementById('edit_sexo').value = p.sexo;
    document.getElementById('edit_sangre').value = p.tipo_sangre_id;
    document.getElementById('edit_tel').value = p.telefono;
    new bootstrap.Modal(document.getElementById('modalEditPaciente')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>

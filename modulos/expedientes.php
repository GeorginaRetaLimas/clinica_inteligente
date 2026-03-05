<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireLogin();

$medico_id = $_SESSION['medico_id'];
$isAdmin = ($_SESSION['rol_id'] == 1);

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $paciente_id = (int)$_POST['paciente_id'];
        $motivo = $_POST['motivo_consulta'] ?? '';
        $sintomas = $_POST['sintomas'] ?? '';
        $diagnostico = $_POST['diagnostico'] ?? '';
        $tratamiento = $_POST['tratamiento'] ?? '';

        $sql = "INSERT INTO expedientes (paciente_id, medico_id, motivo_consulta, sintomas, diagnostico, tratamiento) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$paciente_id, $medico_id, $motivo, $sintomas, $diagnostico, $tratamiento]);

        header('Location: expedientes.php?msg=added');
        exit;
    }
}

// Fetch Expedientes
if ($isAdmin) {
    $sqlExp = "SELECT e.*, p.nombre as p_nombre, p.apellido_paterno as p_ap, m.nombre as m_nombre, m.apellido_paterno as m_ap 
                 FROM expedientes e 
                 JOIN pacientes p ON e.paciente_id = p.id
                 JOIN medicos m ON e.medico_id = m.id
                 ORDER BY e.fecha_consulta DESC";
    $expedientes = $pdo->query($sqlExp)->fetchAll();
    $pacientes = $pdo->query("SELECT id, nombre, apellido_paterno FROM pacientes")->fetchAll();
}
else {
    $sqlExp = "SELECT e.*, p.nombre as p_nombre, p.apellido_paterno as p_ap 
                 FROM expedientes e 
                 JOIN pacientes p ON e.paciente_id = p.id
                 WHERE e.medico_id = ? 
                 ORDER BY e.fecha_consulta DESC";
    $stmt = $pdo->prepare($sqlExp);
    $stmt->execute([$medico_id]);
    $expedientes = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT p.id, p.nombre, p.apellido_paterno FROM pacientes p INNER JOIN paciente_medico pm ON p.id = pm.paciente_id WHERE pm.medico_id = ?");
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
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Expediente registrado exitosamente.
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
                                    <a href="#" class="btn btn-sm btn-outline-success"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
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

<?php require_once '../includes/footer.php'; ?>

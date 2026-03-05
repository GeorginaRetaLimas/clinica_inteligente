<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireLogin();

$medico_id = $_SESSION['medico_id'];
$isAdmin = ($_SESSION['rol_id'] == 1);

// Handle POST request (Create / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $paciente_id = (int)$_POST['paciente_id'];
        $fecha = $_POST['fecha_hora'];
        $motivo = $_POST['motivo'];

        // Si el admin crea la cita, igual se la podría asignar a otro médico, 
        // pero lo haremos simple, asume que es la cita del médico actual.
        // Si es admin, dejamos $medico_id_cita como el suyo, o podríamos pedirlo.
        $medico_id_cita = $medico_id;

        $sql = "INSERT INTO citas (paciente_id, medico_id, fecha_hora, motivo) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$paciente_id, $medico_id_cita, $fecha, $motivo]);

        header('Location: citas.php?msg=added');
        exit;
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

    // Para modal
    $pacientes = $pdo->query("SELECT id, nombre, apellido_paterno FROM pacientes")->fetchAll();
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

    // Para modal
    $stmt = $pdo->prepare("SELECT p.id, p.nombre, p.apellido_paterno FROM pacientes p INNER JOIN paciente_medico pm ON p.id = pm.paciente_id WHERE pm.medico_id = ?");
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
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Cita programada exitosamente.
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
                        <th>Fecha y Hora</th>
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
                            <tr>
                                <td><i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i', strtotime($c['fecha_hora'])); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($c['p_nombre'] . ' ' . $c['p_ap']); ?></td>
                                <?php if ($isAdmin): ?>
                                    <td><?php echo htmlspecialchars($c['m_nombre'] . ' ' . $c['m_ap']); ?></td>
                                <?php
        endif; ?>
                                <td><span class="text-truncate d-inline-block" style="max-width: 150px;"><?php echo htmlspecialchars($c['motivo'] ?? '-'); ?></span></td>
                                <td><span class="badge rounded-pill <?php echo $c_badge; ?>"><?php echo ucfirst(str_replace('_', ' ', $c['estado'])); ?></span></td>
                                <td class="text-end">
                                    <?php if ($c['estado'] === 'pendiente' || $c['estado'] === 'confirmada'): ?>
                                        <a href="#" class="btn btn-sm btn-outline-success" title="Iniciar Consulta"><i class="bi bi-journal-medical"></i> Consulta</a>
                                    <?php
        endif; ?>
                                    <a href="#" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
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
            <div class="col-md-12">
                <label class="form-label">Fecha y Hora</label>
                <input type="datetime-local" class="form-control" name="fecha_hora" required>
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

<?php require_once '../includes/footer.php'; ?>

<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

// Estadísticas resumidas
$medico_id = $_SESSION['medico_id'];

// Pacientes ligados al médico si no es admin, o todos si es admin (simplificado para admin: ver todo)
// Según auth.php y tabla roles: rol 1 es admin, 2 es medico.
$isAdmin = ($_SESSION['rol_id'] == 1);

$whereMedico = $isAdmin ? "" : "WHERE pm.medico_id = $medico_id";
$whereMedicoCita = $isAdmin ? "" : "WHERE c.medico_id = $medico_id";
$whereMedicoExp = $isAdmin ? "" : "WHERE e.medico_id = $medico_id";

// Total pacientes del medico
$sqlPacientes = "SELECT COUNT(DISTINCT p.id) as total FROM pacientes p " . ($isAdmin ? "" : "INNER JOIN paciente_medico pm ON p.id = pm.paciente_id WHERE pm.medico_id = $medico_id");
$total_pacientes = $pdo->query($sqlPacientes)->fetch()['total'];

// Citas de hoy
$sqlCitas = "SELECT COUNT(*) as total FROM citas c " . ($isAdmin ? "WHERE DATE(c.fecha_hora) = CURDATE()" : "WHERE c.medico_id = $medico_id AND DATE(c.fecha_hora) = CURDATE()");
$total_citas_hoy = $pdo->query($sqlCitas)->fetch()['total'];

// Expedientes recientes
$sqlExp = "SELECT COUNT(*) as total FROM expedientes e " . ($isAdmin ? "" : "WHERE e.medico_id = $medico_id");
$total_expedientes = $pdo->query($sqlExp)->fetch()['total'];

?>
<?php require_once 'includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Bienvenido, <?php echo htmlspecialchars($_SESSION['medico_nombre']); ?></h2>
        <p class="text-muted">Panel de control y resumen rápido.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-2">Mis Pacientes</h6>
                        <h3 class="fw-bold mb-0"><?php echo $total_pacientes; ?></h3>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-people text-info fs-3"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="modulos/pacientes.php" class="text-decoration-none text-info fw-semibold">Ver pacientes <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-2">Citas de Hoy</h6>
                        <h3 class="fw-bold mb-0"><?php echo $total_citas_hoy; ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-calendar-event text-primary fs-3"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="modulos/citas.php" class="text-decoration-none text-primary fw-semibold">Ir a la agenda <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-2">Expedientes</h6>
                        <h3 class="fw-bold mb-0"><?php echo $total_expedientes; ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                        <i class="bi bi-file-earmark-medical text-success fs-3"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="modulos/expedientes.php" class="text-decoration-none text-success fw-semibold">Ver historiales <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5">
    <div class="col-md-12 text-center">
        <div class="card bg-info text-white shadow-sm border-0 rounded-4 p-4">
            <div class="card-body">
                <h4 class="fw-bold"><i class="bi bi-robot"></i> Asistente de IA Disponible</h4>
                <p class="mb-4">Use nuestro asistente con Procesamiento de Lenguaje Natural para capturar expedientes rápidamente mendiante texto dictado.</p>
                <a href="modulos/chat_ia.php" class="btn btn-light text-info fw-bold rounded-pill px-4">Abrir Asistente</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

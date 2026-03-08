<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireLogin();

// Fetch pacientes para el selector del chat
$medico_id = $_SESSION['medico_id'];
$isAdmin = ($_SESSION['rol_id'] == 1);

if ($isAdmin) {
    $pacientes = $pdo->query("SELECT id, nombre, apellido_paterno FROM pacientes")->fetchAll();
}
else {
    $stmt = $pdo->prepare("SELECT p.id, p.nombre, p.apellido_paterno FROM pacientes p INNER JOIN paciente_medico pm ON p.id = pm.paciente_id WHERE pm.medico_id = ?");
    $stmt->execute([$medico_id]);
    $pacientes = $stmt->fetchAll();
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-10">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <!-- Header Chat -->
            <div class="card-header bg-info text-white p-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <img src="/clinica_app/public/assets/img/logo.png" alt="AURA" width="40" height="40" class="me-2 rounded-circle border border-2 border-white object-fit-cover bg-white">
                    <div>
                        <h6 class="mb-0 fw-bold">AURA</h6>
                        <small class="text-white-50"><i class="bi bi-circle-fill text-success" style="font-size: 0.5rem;"></i> En línea</small>
                    </div>
                </div>
                <div>
                    <select class="form-select form-select-sm bg-info text-white border-white" style="width: 150px; --bs-select-bg-color: #0baccc;" id="chatPacienteSelect">
                        <option value="">Paciente (Opcional)</option>
                        <?php foreach ($pacientes as $pac): ?>
                            <option value="<?php echo $pac['id']; ?>"><?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido_paterno']); ?></option>
                        <?php
endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Body Chat -->
            <div class="card-body p-4 bg-light" id="chatArea" style="height: 400px; max-height: 400px; overflow-y: auto;">
                
                <!-- Mensaje IA -->
                <div class="d-flex mb-4">
                    <div class="me-2">
                        <img src="/clinica_app/public/assets/img/logo.png" width="35" height="35" class="rounded-circle object-fit-cover bg-white border border-info" alt="AURA">
                    </div>
                    <div class="bg-white p-3 rounded-4 shadow-sm" style="border-top-left-radius: 0 !important; max-width: 80%;">
                        <p class="mb-0 text-dark">Hola Dr. <?php echo htmlspecialchars($_SESSION['medico_nombre']); ?>. Soy AURA, listo para escuchar el resumen de su consulta. <br>Puede dictarme o escribir los síntomas, diagnóstico y tratamiento.</p>
                        <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;"><?php echo date('H:i'); ?></small>
                    </div>
                </div>

            </div>

            <!-- Footer Chat (Input) -->
            <div class="card-footer bg-white p-3 border-top">
                <form id="chatForm" class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-light text-muted rounded-circle p-2" title="Dictar por voz" id="btnMic">
                        <i class="bi bi-mic-fill fs-5"></i>
                    </button>
                    <textarea class="form-control rounded-4 py-2 px-3 border-info" id="chatInput" rows="1" placeholder="Escriba el motivo de consulta, diagnóstico, etc..." style="resize: none;"></textarea>
                    <button type="submit" class="btn btn-info text-white rounded-circle p-2" title="Enviar texto">
                        <i class="bi bi-send-fill fs-5"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <small class="text-muted"><i class="bi bi-shield-lock"></i> La información enviada está protegida y encriptada bajo estándares médicos.</small>
        </div>
    </div>
</div>



<?php require_once '../includes/footer.php'; ?>

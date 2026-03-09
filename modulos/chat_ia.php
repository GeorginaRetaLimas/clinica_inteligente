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

<div class="row justify-content-center px-3 mt-3">
    <div class="col-12 px-0">
        <div class="wa-container mx-auto" style="max-width: 1400px;">
            <!-- SIDEBAR -->
            <div class="wa-sidebar">
                <div class="wa-header">
                    <img src="/clinica_app/public/assets/img/fabicon/fabicon.png" alt="AURA" width="40" height="40" class="rounded-circle object-fit-cover bg-white">
                    <h6 class="mb-0 ms-3 fw-bold">AURA Chat</h6>
                </div>
                <!-- Search bar -->
                <div class="wa-search">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control form-control-sm shadow-none" placeholder="Buscar paciente en el chat...">
                    </div>
                </div>
                <!-- Contacts List -->
                <div class="wa-contact-list">
                    <?php if (count($pacientes) > 0): ?>
                        <?php foreach ($pacientes as $pac): ?>
                            <div class="wa-contact">
                                <i class="bi bi-person-circle fs-3 text-secondary me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido_paterno']); ?></h6>
                                    <small class="text-muted text-truncate d-block" style="max-width: 200px;">Asignado a mi consultorio</small>
                                </div>
                            </div>
                        <?php
    endforeach; ?>
                    <?php
else: ?>
                        <div class="text-center p-4 text-muted">
                            <small>No hay pacientes agregados</small>
                        </div>
                    <?php
endif; ?>
                </div>
            </div>

            <!-- MAIN CHAT -->
            <div class="wa-main">
                <div class="wa-header justify-content-between">
                    <div class="d-flex align-items-center">
                        <img src="/clinica_app/public/assets/img/fabicon/fabicon.png" alt="AURA" width="40" height="40" class="rounded-circle object-fit-cover bg-white me-3 border border-1 border-secondary">
                        <div class="lh-1">
                            <h6 class="mb-1 fw-bold">AURA Asistente Médico</h6>
                            <small class="text-muted">en línea</small>
                        </div>
                    </div>
                    <div class="text-muted">
                        <i class="bi bi-search me-3 fs-5" style="cursor: pointer;"></i>
                        <i class="bi bi-three-dots-vertical fs-5" style="cursor: pointer;"></i>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="wa-chat-area" id="chatArea">
                    <div class="clearfix">
                        <div class="wa-bubble-in shadow-sm">
                            <p class="mb-0 text-dark" style="font-size: 0.95rem;">Hola Dr. <?php echo htmlspecialchars($_SESSION['medico_nombre']); ?>. Soy AURA, lista para escuchar el resumen de su consulta. <br>Por favor seleccione un paciente en el menú izquierdo (visual) y puede dictarme los síntomas, diagnóstico y tratamiento.</p>
                            <span class="wa-time"><?php echo date('H:i'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Footer / Input -->
                <div class="wa-footer">
                    <form id="chatForm" class="d-flex w-100 align-items-center gap-2 m-0 mt-0 mb-0">
                        <i class="bi bi-emoji-smile fs-4 text-muted mx-2" style="cursor:pointer;"></i>
                        <i class="bi bi-paperclip fs-4 text-muted me-2" style="cursor:pointer;"></i>
                        <textarea class="form-control border-0 shadow-none px-3 py-2 rounded-3 text-dark bg-white" id="chatInput" rows="1" placeholder="Escribe un mensaje..." style="resize: none; overflow-y: hidden;"></textarea>
                        
                        <!-- Web speech buttons logic -->
                        <button type="button" class="btn btn-link text-muted p-2 text-decoration-none shadow-none" id="btnMic" title="Dictar por voz">
                            <i class="bi bi-mic-fill fs-4"></i>
                        </button>
                        <button type="submit" class="btn btn-link text-muted p-2 text-decoration-none shadow-none d-none" id="btnSendText" title="Enviar texto">
                            <i class="bi bi-send-fill fs-4"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="text-center mt-2 mb-4">
            <small class="text-muted"><i class="bi bi-shield-lock-fill text-success"></i> Tus mensajes privados médicos están cifrados de extremo a extremo</small>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

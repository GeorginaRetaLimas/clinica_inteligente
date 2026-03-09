<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireLogin();

// Fetch pacientes para el selector del chat
$medico_id = $_SESSION['medico_id'];
$isAdmin = ($_SESSION['rol_id'] == 1);

if ($isAdmin) {
    $pacientes = $pdo->query("SELECT id, nombre, apellido_paterno FROM pacientes WHERE activo = 1")->fetchAll();
}
else {
    $stmt = $pdo->prepare("SELECT p.id, p.nombre, p.apellido_paterno FROM pacientes p INNER JOIN paciente_medico pm ON p.id = pm.paciente_id WHERE pm.medico_id = ? AND p.activo = 1");
    $stmt->execute([$medico_id]);
    $pacientes = $stmt->fetchAll();
}

require_once '../includes/header.php';
?>
<!-- Inyectar fondo de pantalla global de chat -->
<script>document.body.classList.add('chat-page');</script>

<div class="row justify-content-center px-3 mt-3 w-100 m-0">
    <div class="col-12 col-lg-11 px-0">
        <div class="wa-container mx-auto" style="height: 85vh; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <!-- SIDEBAR -->
            <div class="wa-sidebar" style="border-right: 1px solid var(--wa-border);">
                <div class="wa-header">
                    <img src="/clinica_app/public/assets/img/fabicon/fabicon.png" alt="AURA" width="40" height="40" class="rounded-circle object-fit-cover bg-white shadow-sm border border-2 border-white">
                    <h6 class="mb-0 ms-3 fw-bold text-white">Pacientes</h6>
                </div>
                <!-- Search bar -->
                <div class="wa-search border-bottom">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="waSearchInput" class="form-control form-control-sm bg-light border-0 shadow-none" placeholder="Buscar paciente en el chat...">
                    </div>
                </div>
                <!-- Contacts List -->
                <div class="wa-contact-list bg-white" id="waContactList">
                    <!-- Chat General del Médico (Fijado Primer Lugar) -->
                    <div class="wa-contact" data-paciente-id="">
                        <img src="/clinica_app/public/assets/img/fabicon/fabicon.png" alt="User" width="45" height="45" class="rounded-circle object-fit-cover bg-white border border-info shadow-sm me-3">
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold wa-contact-name">Dr. <?php echo htmlspecialchars($_SESSION['medico_nombre']); ?></h6>
                            <small class="text-muted text-truncate d-block" style="max-width: 200px;">Chat General / Asistente</small>
                        </div>
                    </div>

                    <?php if (count($pacientes) > 0): ?>
                        <?php foreach ($pacientes as $pac): ?>
                            <div class="wa-contact" data-paciente-id="<?php echo $pac['id']; ?>">
                                <img src="/clinica_app/public/assets/img/fabicon/fabicon.png" alt="User" width="45" height="45" class="rounded-circle object-fit-cover bg-white border border-info shadow-sm me-3">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold wa-contact-name"><?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido_paterno']); ?></h6>
                                    <small class="text-muted text-truncate d-block" style="max-width: 200px;">Paciente</small>
                                </div>
                            </div>
                        <?php
    endforeach; ?>
                    <?php
endif; ?>
                </div>
            </div>
            
            <!-- MAIN CHAT -->
            <div class="wa-main">
                <div class="wa-header justify-content-between position-relative" style="background-color: var(--bs-primary);">
                    <!-- header icons left -->
                    <div class="position-absolute start-0 ms-3">
                        <i class="bi bi-list text-white fs-4" style="cursor: pointer;"></i>
                    </div>

                    <div class="d-flex align-items-center text-white">
                        <img src="/clinica_app/public/assets/img/fabicon/fabicon.png" alt="AURA" width="45" height="45" class="rounded-circle object-fit-cover bg-white me-3 border border-2 border-white shadow-sm">
                        <div class="lh-1 text-center">
                            <h5 class="mb-1 fw-bold text-white">AURA</h5>
                            <small class="text-white-50"><i class="bi bi-circle-fill text-success" style="font-size:0.6rem;"></i> En línea</small>
                        </div>
                    </div>
                    
                    <!-- header icons right -->
                    <div class="position-absolute end-0 me-3">
                        <i class="bi bi-three-dots-vertical text-white fs-5" style="cursor: pointer;"></i>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="wa-chat-area" id="chatArea">
                    <div class="clearfix">
                        <div class="wa-bubble-in shadow-sm" style="border-radius: 12px; border-top-left-radius: 0;">
                            <p class="mb-0 text-dark" style="font-size: 0.95rem;">Hola Dr. <?php echo htmlspecialchars($_SESSION['medico_nombre']); ?>. Soy AURA, lista para escuchar el resumen de su consulta. <br>Puedes dictarme los síntomas, diagnóstico y tratamiento.</p>
                            <span class="wa-time"><?php echo date('H:i'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Footer / Input -->
                <div class="wa-footer" style="background-color: #ffffff; border-top: 2px solid var(--wa-border); padding: 15px 20px;">
                    <form id="chatForm" class="d-flex w-100 align-items-center gap-3 m-0 mt-0 mb-0">
                        <i class="bi bi-paperclip fs-4 text-muted" style="cursor:pointer; transition: color 0.3s;" onmouseover="this.style.color='var(--bs-primary)'" onmouseout="this.style.color='#6c757d'"></i>
                        
                        <textarea class="form-control shadow-sm px-4 py-2 text-dark" id="chatInput" rows="1" placeholder="Escribe tu mensaje médico aquí..." style="resize: none; overflow-y: hidden; border: 1px solid #e0e0e0; border-radius: 30px; font-size: 1rem; background-color: #f7f9fa;"></textarea>
                        
                        <!-- Web speech buttons logic -->
                        <button type="button" class="btn text-white rounded-circle p-2 shadow-sm d-flex justify-content-center align-items-center" style="background-color: var(--bs-primary); min-width: 45px; height: 45px; transition: transform 0.2s;" id="btnMic" title="Dictar por voz" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="bi bi-mic-fill fs-5"></i>
                        </button>
                        <button type="submit" class="btn text-white rounded-circle p-2 shadow-sm d-none justify-content-center align-items-center" style="background-color: var(--bs-primary); min-width: 45px; height: 45px; transition: transform 0.2s;" id="btnSendText" title="Enviar texto" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="bi bi-send-fill fs-5"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="text-center mt-3 mb-4 d-none d-lg-block">
            <!-- Texto inferior opcional u omitido si rompe el layout visual -->
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

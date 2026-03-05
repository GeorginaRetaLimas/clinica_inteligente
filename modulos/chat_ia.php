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

<div class="row mb-3">
    <div class="col-12 text-center">
        <h2 class="fw-bold text-info"><i class="bi bi-robot"></i> Asistente Médico Inteligente</h2>
        <p class="text-muted">Procesamiento de Lenguaje Natural para captura de historiales</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <!-- Header Chat -->
            <div class="card-header bg-info text-white p-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <img src="https://ui-avatars.com/api/?name=IA&background=ffffff&color=0dcaf0&rounded=true" alt="IA" width="40" height="40" class="me-2 rounded-circle border border-2 border-white">
                    <div>
                        <h6 class="mb-0 fw-bold">Gemini Clinical Assistant</h6>
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
                        <img src="https://ui-avatars.com/api/?name=IA&background=0dcaf0&color=fff&rounded=true" width="35" height="35" class="rounded-circle">
                    </div>
                    <div class="bg-white p-3 rounded-4 shadow-sm" style="border-top-left-radius: 0 !important; max-width: 80%;">
                        <p class="mb-0 text-dark">Hola Dr. <?php echo htmlspecialchars($_SESSION['medico_nombre']); ?>. Estoy listo para escuchar el resumen de su consulta. <br>Puede dictarme o escribir los síntomas, diagnóstico y tratamiento, y yo generaré el expediente estructurado automáticamente.</p>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('chatForm');
        const input = document.getElementById('chatInput');
        const chatArea = document.getElementById('chatArea');
        const btnMic = document.getElementById('btnMic');

        // Auto-resize textarea
        input.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight < 120 ? this.scrollHeight : 120) + 'px';
        });

        // Submit form (mock logic)
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const text = input.value.trim();
            if (!text) return;

            // Add Medico Message
            const now = new Date();
            const time = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
            
            const userMsg = `
            <div class="d-flex mb-4 justify-content-end">
                <div class="bg-info text-white p-3 rounded-4 shadow-sm" style="border-top-right-radius: 0 !important; max-width: 80%;">
                    <p class="mb-0">${text.replace(/\n/g, '<br>')}</p>
                    <small class="text-white-50 mt-1 d-block text-end" style="font-size: 0.75rem;">${time}</small>
                </div>
            </div>`;
            
            chatArea.insertAdjacentHTML('beforeend', userMsg);
            
            // Clean input
            input.value = '';
            input.style.height = 'auto';
            
            // Scroll to bottom
            chatArea.scrollTop = chatArea.scrollHeight;

            // Simulate IA typing
            setTimeout(() => {
                const typingObj = `
                <div class="d-flex mb-4" id="typingIndicator">
                    <div class="me-2">
                        <img src="https://ui-avatars.com/api/?name=IA&background=0dcaf0&color=fff&rounded=true" width="35" height="35" class="rounded-circle">
                    </div>
                    <div class="bg-white p-3 rounded-4 shadow-sm text-muted" style="border-top-left-radius: 0 !important;">
                        Procesando texto clínico... <span class="spinner-grow spinner-grow-sm text-info ml-2" role="status"></span>
                    </div>
                </div>`;
                chatArea.insertAdjacentHTML('beforeend', typingObj);
                chatArea.scrollTop = chatArea.scrollHeight;

                // Simulate IA Response (Mocked as pending implementation for backend)
                setTimeout(() => {
                    document.getElementById('typingIndicator').remove();
                    const iaMsg = `
                    <div class="d-flex mb-4">
                        <div class="me-2">
                            <img src="https://ui-avatars.com/api/?name=IA&background=0dcaf0&color=fff&rounded=true" width="35" height="35" class="rounded-circle">
                        </div>
                        <div class="bg-white p-3 rounded-4 shadow-sm" style="border-top-left-radius: 0 !important; max-width: 80%;">
                            <p class="mb-2 text-dark">He procesado su dictado. He generado un registro preliminar en la base de datos.</p>
                            <div class="bg-light p-2 rounded border mb-2">
                                <small class="text-muted d-block fw-bold">Entidades Extraídas:</small>
                                <ul class="mb-0 text-dark" style="font-size: 0.85rem; padding-left: 20px;">
                                    <li><b>Acción:</b> Registro de nuevo expediente.</li>
                                    <li><b>Estado:</b> Pendiente de validación manual.</li>
                                </ul>
                            </div>
                            <a href="expedientes.php" class="btn btn-sm btn-outline-info">Ir a validar expedientes</a>
                            <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">${new Date().getHours().toString().padStart(2,'0')}:${new Date().getMinutes().toString().padStart(2,'0')}</small>
                        </div>
                    </div>`;
                    chatArea.insertAdjacentHTML('beforeend', iaMsg);
                    chatArea.scrollTop = chatArea.scrollHeight;
                }, 1500);

            }, 500);
        });

        // Submit on Enter
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });
        
        // Microphone simple toggle styling
        let recording = false;
        btnMic.addEventListener('click', function() {
            recording = !recording;
            if (recording) {
                this.classList.remove('btn-light', 'text-muted');
                this.classList.add('bg-danger', 'text-white');
                input.placeholder = "Escuchando...";
                input.disabled = true;
            } else {
                this.classList.remove('bg-danger', 'text-white');
                this.classList.add('btn-light', 'text-muted');
                input.placeholder = "Dictado capturado. Enviando...";
                input.disabled = false;
                input.value = "La paciente refiere cefalea de 3 días de evolución, acompañada de fiebre no cuantificada. Se diagnostica faringitis aguda. Indico paracetamol 500mg cada 8 horas. Favor generar el expediente.";
                setTimeout(() => form.dispatchEvent(new Event('submit')), 800);
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>

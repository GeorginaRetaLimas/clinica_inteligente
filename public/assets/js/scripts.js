document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const chatArea = document.getElementById('chatArea');
    const btnMic = document.getElementById('btnMic');

    if (!form || !input || !chatArea) return; // Solo ejecutar si estamos en la vista de chat

    // Auto-resize textarea
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight < 120 ? this.scrollHeight : 120) + 'px';
    });

    // Submit form a la API Gemini Interna
    form.addEventListener('submit', async function (e) {
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

        // Typing Indicator
        const typingId = 'typing-' + Date.now();
        const typingObj = `
        <div class="d-flex mb-4" id="${typingId}">
            <div class="me-2">
                <img src="/clinica_app/public/assets/img/logo.png" width="35" height="35" class="rounded-circle object-fit-cover bg-white" alt="AURA">
            </div>
            <div class="bg-white p-3 rounded-4 shadow-sm text-muted" style="border-top-left-radius: 0 !important;">
                Procesando texto clínico... <span class="spinner-grow spinner-grow-sm text-info ms-2" role="status"></span>
            </div>
        </div>`;
        chatArea.insertAdjacentHTML('beforeend', typingObj);
        chatArea.scrollTop = chatArea.scrollHeight;

        try {
            // Llama al endpoint de PHP que conecta con Gemini
            const response = await fetch('/clinica_app/api/gemini_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ texto: text })
            });

            const data = await response.json();
            document.getElementById(typingId).remove();

            if (data.status === 'success') {
                const botMsgParsed = data.respuesta.replace(/\n/g, '<br>');
                const iaMsg = `
                <div class="d-flex mb-4">
                    <div class="me-2">
                        <img src="/clinica_app/public/assets/img/logo.png" width="35" height="35" class="rounded-circle object-fit-cover bg-white" alt="AURA">
                    </div>
                    <div class="bg-white p-3 rounded-4 shadow-sm" style="border-top-left-radius: 0 !important; max-width: 80%;">
                        <p class="mb-0 text-dark">${botMsgParsed}</p>
                        <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">${new Date().getHours().toString().padStart(2, '0')}:${new Date().getMinutes().toString().padStart(2, '0')}</small>
                    </div>
                </div>`;
                chatArea.insertAdjacentHTML('beforeend', iaMsg);
            } else {
                const errorMsg = `
                <div class="d-flex mb-4 justify-content-start">
                    <div class="bg-danger text-white p-3 rounded-4 shadow-sm" style="border-top-left-radius: 0 !important;">
                        Error: ${data.mensaje || 'Respuesta no válida de la API'}
                    </div>
                </div>`;
                chatArea.insertAdjacentHTML('beforeend', errorMsg);
            }
        } catch (err) {
            document.getElementById(typingId).remove();
            chatArea.insertAdjacentHTML('beforeend', `
            <div class="d-flex mb-4 justify-content-start">
                <div class="bg-danger text-white p-3 rounded-4 shadow-sm">
                    Error local de conexión al servidor.
                </div>
            </div>`);
        }

        chatArea.scrollTop = chatArea.scrollHeight;
    });

    // Enter para enviar
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });

    // Simulación de dictado por voz (Mic)
    let recording = false;
    if (btnMic) {
        btnMic.addEventListener('click', function () {
            recording = !recording;
            if (recording) {
                this.classList.remove('btn-light', 'text-muted');
                this.classList.add('bg-danger', 'text-white');
                input.placeholder = "Escuchando...";
                input.disabled = true;
            } else {
                this.classList.remove('bg-danger', 'text-white');
                this.classList.add('btn-light', 'text-muted');
                input.placeholder = "Escriba el motivo de consulta, diagnóstico, etc...";
                input.disabled = false;
                // Agrega texto dictado automáticamente por simulación
                input.value = input.value + (input.value ? ' ' : '') + "La paciente refiere cefalea de 3 días de evolución, acompañada de fiebre no cuantificada. Indico paracetamol 500mg.";
                setTimeout(() => form.dispatchEvent(new Event('submit')), 500);
            }
        });
    }
});

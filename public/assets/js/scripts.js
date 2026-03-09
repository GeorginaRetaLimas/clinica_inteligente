document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const chatArea = document.getElementById('chatArea');
    const btnMic = document.getElementById('btnMic');
    const btnSendText = document.getElementById('btnSendText');
    const btnClearChat = document.getElementById('btnClearChat');
    let current_conversacion_id = null;

    if (!form || !input || !chatArea) return; // Solo ejecutar si estamos en la vista de chat

    // Auto-resize textarea and togle Mic/Send
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight < 120 ? this.scrollHeight : 120) + 'px';

        if (this.value.trim().length > 0) {
            btnMic.classList.add('d-none');
            btnSendText.classList.remove('d-none');
        } else if (!isRecording) {
            btnMic.classList.remove('d-none');
            btnSendText.classList.add('d-none');
        }
    });

    // Enter para enviar
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const text = input.value.trim();
        if (!text || !current_conversacion_id) {
            if (!current_conversacion_id) alert("Por favor selecciona un chat del panel lateral.");
            return;
        }

        // Añadir Burbuja de Usuario (Médico)
        const now = new Date();
        const time = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');

        const userMsg = `
        <div class="clearfix">
            <div class="wa-bubble-out shadow-sm">
                <p class="mb-0 text-dark" style="font-size: 0.95rem;">${text.replace(/\n/g, '<br>')}</p>
                <span class="wa-time">${time} <i class="bi bi-check-all text-info" style="font-size: 1rem;"></i></span>
            </div>
        </div>`;
        chatArea.insertAdjacentHTML('beforeend', userMsg);

        // Limpiar Input
        input.value = '';
        input.style.height = 'auto';
        btnMic.classList.remove('d-none');
        btnSendText.classList.add('d-none');
        chatArea.scrollTop = chatArea.scrollHeight;

        // Indicador de Escritura
        const typingId = 'typing-' + Date.now();
        const typingObj = `
        <div class="clearfix" id="${typingId}">
            <div class="wa-bubble-in shadow-sm text-muted fst-italic">
                AURA está escribiendo <span class="spinner-grow spinner-grow-sm text-success ms-2" role="status"></span>
            </div>
        </div>`;
        chatArea.insertAdjacentHTML('beforeend', typingObj);
        chatArea.scrollTop = chatArea.scrollHeight;

        try {
            // Llama a la API local de Gemini
            const response = await fetch('/clinica_app/api/gemini_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ texto: text, conversacion_id: current_conversacion_id })
            });

            const data = await response.json();
            document.getElementById(typingId).remove();

            if (data.status === 'success') {
                const botMsgParsed = data.respuesta.replace(/\n/g, '<br>');
                const iaMsg = `
                <div class="clearfix">
                    <div class="wa-bubble-in shadow-sm">
                        <p class="mb-0 text-dark" style="font-size: 0.95rem;">${botMsgParsed}</p>
                        <span class="wa-time">${new Date().getHours().toString().padStart(2, '0')}:${new Date().getMinutes().toString().padStart(2, '0')}</span>
                    </div>
                </div>`;
                chatArea.insertAdjacentHTML('beforeend', iaMsg);
            } else {
                chatArea.insertAdjacentHTML('beforeend', `
                <div class="clearfix">
                    <div class="wa-bubble-in shadow-sm bg-danger text-white border-danger">
                        Error: ${data.mensaje || 'Respuesta no válida de la API'}
                    </div>
                </div>`);
            }
        } catch (err) {
            document.getElementById(typingId).remove();
            chatArea.insertAdjacentHTML('beforeend', `
            <div class="clearfix">
                <div class="wa-bubble-in shadow-sm bg-danger text-white border-danger">
                    Error local de conexión al servidor.
                </div>
            </div>`);
        }

        chatArea.scrollTop = chatArea.scrollHeight;
    });

    // Web Speech API Logic
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition;
    let isRecording = false;

    if (SpeechRecognition && btnMic) {
        recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'es-ES';

        recognition.onstart = function () {
            isRecording = true;
            btnMic.innerHTML = '<i class="bi bi-stop-circle-fill fs-4 text-danger pulse-anim"></i>';
            input.placeholder = "Escuchando audio...";
            input.disabled = true;
        };

        recognition.onresult = function (event) {
            const transcript = event.results[0][0].transcript;
            input.value = input.value + (input.value ? ' ' : '') + transcript;
        };

        recognition.onerror = function (event) {
            console.error("Error en Speech Recognition:", event.error);
            isRecording = false;
            resetMicUI();
        };

        recognition.onend = function () {
            isRecording = false;
            resetMicUI();
        };

        function resetMicUI() {
            // Check if there's text to display the send button instead of mic
            if (input.value.trim().length > 0) {
                btnMic.classList.add('d-none');
                btnSendText.classList.remove('d-none');
            } else {
                btnMic.innerHTML = '<i class="bi bi-mic-fill fs-4"></i>';
            }
            input.placeholder = "Escribe un mensaje...";
            input.disabled = false;
            input.focus();
        }

        btnMic.addEventListener('click', function () {
            if (isRecording) {
                recognition.stop();
            } else {
                recognition.start();
            }
        });
    } else if (btnMic) {
        btnMic.addEventListener('click', () => {
            alert("API de dictado por voz no soportada en este navegador (intenta en Chrome o Edge).");
        });
    }

    // Sidebar Patient interaction visual effect
    document.querySelectorAll('.wa-contact').forEach(contact => {
        contact.addEventListener('click', function () {
            document.querySelectorAll('.wa-contact').forEach(c => c.classList.remove('active', 'bg-light'));
            this.classList.add('active', 'bg-light');

            const name = this.querySelector('h6').innerText;
            const docName = document.getElementById('userDropdown') ? document.getElementById('userDropdown').innerText.trim() : 'Doctor';
            const pacienteId = this.dataset.pacienteId || '';

            chatArea.innerHTML = `<div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm text-info"></div> Cargando historial seguro...</div>`;

            fetch(`/clinica_app/api/load_chat.php?paciente_id=${pacienteId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        current_conversacion_id = data.conversacion_id;
                        chatArea.innerHTML = '';

                        if (data.mensajes.length === 0) {
                            chatArea.innerHTML = `
                            <div class="clearfix">
                                <div class="wa-bubble-in shadow-sm">
                                    <p class="mb-0 text-dark" style="font-size: 0.95rem;">Hola ${docName}. Has iniciado un chat **${pacienteId ? 'con enfoque en ' + name : 'General / Asistente'}**. ¿En qué puedo ayudarte hoy?</p>
                                    <span class="wa-time">${new Date().getHours().toString().padStart(2, '0')}:${new Date().getMinutes().toString().padStart(2, '0')}</span>
                                </div>
                            </div>`;
                        } else {
                            data.mensajes.forEach(m => {
                                const isMedico = m.remitente === 'medico';
                                const bubbleClass = isMedico ? 'wa-bubble-out' : 'wa-bubble-in';
                                const ticks = isMedico ? ' <i class="bi bi-check-all text-info" style="font-size: 1rem;"></i>' : '';

                                const mHTML = `
                                <div class="clearfix">
                                    <div class="${bubbleClass} shadow-sm">
                                        <p class="mb-0 text-dark" style="font-size: 0.95rem;">${m.texto.replace(/\\n/g, '<br>')}</p>
                                        <span class="wa-time">${m.fecha}${ticks}</span>
                                    </div>
                                </div>`;
                                chatArea.insertAdjacentHTML('beforeend', mHTML);
                            });
                            chatArea.scrollTop = chatArea.scrollHeight;
                        }
                    } else {
                        chatArea.innerHTML = `<div class="text-danger p-3 text-center">Error al cargar chat cifrado.</div>`;
                    }
                }).catch(err => {
                    chatArea.innerHTML = `<div class="text-danger p-3 text-center">Error de red.</div>`;
                });
        });
    });

    // Auto-seleccionar primer chat (General) al cargar
    setTimeout(() => {
        const firstContact = document.querySelector('.wa-contact');
        if (firstContact) firstContact.click();
    }, 300);

    // Filtro de Contactos (Buscador)
    const searchInput = document.getElementById('waSearchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.wa-contact').forEach(contact => {
                const name = contact.querySelector('.wa-contact-name').innerText.toLowerCase();
                if (name.includes(term)) {
                    contact.style.display = 'flex';
                } else {
                    contact.style.display = 'none';
                }
            });
        });
    }

    // Limpiar Chat Lógicamente
    if (btnClearChat) {
        btnClearChat.addEventListener('click', async function () {
            if (!current_conversacion_id) return;

            if (confirm("¿Estás seguro de que deseas limpiar la conversación actual? Los mensajes se ocultarán de la pantalla.")) {
                try {
                    const res = await fetch('/clinica_app/api/clear_chat.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ conversacion_id: current_conversacion_id })
                    });
                    const data = await res.json();

                    if (data.status === 'success') {
                        chatArea.innerHTML = `
                        <div class="clearfix">
                            <div class="wa-bubble-in shadow-sm text-center bg-light">
                                <small class="text-muted"><i class="bi bi-info-circle"></i> Chat limpiado.</small>
                            </div>
                        </div>`;
                    } else {
                        alert("Error al limpiar chat: " + data.mensaje);
                    }
                } catch (e) {
                    alert("Error de red intentando limpiar el chat.");
                }
            }
        });
    }
});

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
                let botMsgParsed = data.respuesta;
                let htmlCardComplemento = '';

                try {
                    // Limpiar markdown incrustado por Gemini
                    let cleanRes = data.respuesta.replace(/```json/gi, '').replace(/```/g, '').trim();
                    const auraObj = JSON.parse(cleanRes);
                    botMsgParsed = auraObj.mensaje_aura;

                    if (auraObj.operacion && auraObj.operacion !== 'CONVERSACION') {
                        // Construimos una pseudo-card para mostrarle al medico los datos
                        let badgetsHTML = '';
                        if (auraObj.operacion !== 'CONSULTAR_DATO') {
                            if (auraObj.campos_faltantes && auraObj.campos_faltantes.length > 0) {
                                auraObj.campos_faltantes.forEach(c => {
                                    badgetsHTML += `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill me-1 mb-1">${c.replace('_', ' ')} faltante</span>`;
                                });
                            } else {
                                badgetsHTML = `<span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill mb-1"><i class="bi bi-check-circle"></i> Datos Completos</span>`;
                            }
                        }

                        let dataExtractedHTML = '';
                        if (auraObj.datos) {
                            for (const [key, value] of Object.entries(auraObj.datos)) {
                                if (value !== null && typeof value !== 'object') {
                                    dataExtractedHTML += `<tr><td class="text-muted fw-semibold border-0" style="font-size:0.8rem; padding: 2px 5px;">${key.replace('_id', '').replace(/_/g, ' ').toUpperCase()}</td><td class="border-0" style="font-size:0.85rem; padding: 2px 5px;">${value}</td></tr>`;
                                } else if (value !== null && typeof value === 'object') {
                                    dataExtractedHTML += `<tr><td colspan="2" class="text-info fw-bold border-0 pt-2" style="font-size:0.75rem;">${key.toUpperCase().replace(/_/g, ' ')}</td></tr>`;
                                    for (const [subKey, subValue] of Object.entries(value)) {
                                        if (subValue !== null) {
                                            dataExtractedHTML += `<tr><td class="text-muted fw-semibold border-0" style="font-size:0.8rem; padding: 2px 5px; ps-3"><i class="bi bi-arrow-return-right"></i> ${subKey.replace('_id', '').replace(/_/g, ' ').toUpperCase()}</td><td class="border-0" style="font-size:0.85rem; padding: 2px 5px;">${subValue}</td></tr>`;
                                        }
                                    }
                                }
                            }
                        }

                        let titleOp = auraObj.operacion === 'CONSULTAR_DATO' ? 'Extracción de Datos Clínicos' : auraObj.operacion.replace('REGISTRAR_', 'Preregistro de ');

                        let buttonsHTML = '';
                        if (auraObj.operacion !== 'CONSULTAR_DATO') {
                            buttonsHTML = `
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end border-top pt-2">
                                <button class="btn btn-sm btn-outline-secondary py-0" onclick="alert('Operación cancelada')">Descartar</button>
                                <button class="btn btn-sm btn-info text-white py-0" onclick="alert('Funcionalidad de autollenado en desarrollo.')">Confirmar Acción</button>
                            </div>`;
                        }

                        htmlCardComplemento = `
                        <div class="card border-info shadow-none mt-2" style="background-color: #f0fdfc;">
                            <div class="card-body p-2">
                                <h6 class="card-title text-info mb-1 fw-bold" style="font-size: 0.85rem;"><i class="bi bi-robot"></i> ${titleOp}</h6>
                                <div class="mb-2">${badgetsHTML}</div>
                                <table class="table table-sm table-borderless mb-2">
                                    <tbody>${dataExtractedHTML}</tbody>
                                </table>
                                ${buttonsHTML}
                            </div>
                        </div>`;
                    }
                } catch (e) {
                    botMsgParsed = data.respuesta.replace(/\\n/g, '<br>');
                }

                const iaMsg = `
                <div class="clearfix">
                    <div class="wa-bubble-in shadow-sm">
                        <p class="mb-0 text-dark" style="font-size: 0.95rem;">${botMsgParsed.replace(/\\n/g, '<br>')}</p>
                        ${htmlCardComplemento}
                        <span class="wa-time mt-1 d-block text-end">${new Date().getHours().toString().padStart(2, '0')}:${new Date().getMinutes().toString().padStart(2, '0')}</span>
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

                                let textoAMostrar = m.texto;
                                if (!isMedico) {
                                    try {
                                        // Intentamos detectar si la IA nos dejó un JSON
                                        let cleanRes = m.texto.replace(/```json/gi, '').replace(/```/g, '').trim();
                                        const auraObj = JSON.parse(cleanRes);
                                        if (auraObj.mensaje_aura) {
                                            textoAMostrar = auraObj.mensaje_aura;

                                            // Opción para ver detalles en historiales pasados (Si es registro, etc)
                                            if (auraObj.operacion && auraObj.operacion !== 'CONVERSACION' && auraObj.operacion !== 'CONSULTAR_DATO') {
                                                textoAMostrar += `<br><small class="text-primary mt-2 d-block"><i class="bi bi-file-earmark-text"></i> Pre-Registro de ${auraObj.operacion.replace('REGISTRAR_', '')} generado.</small>`;
                                            }
                                        }
                                    } catch (e) {
                                        // Era texto normal (Fallback)
                                    }
                                }

                                const mHTML = `
                                <div class="clearfix">
                                    <div class="${bubbleClass} shadow-sm">
                                        <p class="mb-0 text-dark" style="font-size: 0.95rem;">${textoAMostrar.replace(/\\n/g, '<br>')}</p>
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

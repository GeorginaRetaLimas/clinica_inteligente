// Utilidades Modales Globales AURA
function showAuraModal(title, message, type = 'info') {
    const modalEl = document.getElementById('auraGlobalModal');
    if (!modalEl) { alert(title + ": " + message); return; }

    document.getElementById('auraGlobalModalTitle').innerHTML = title;
    document.getElementById('auraGlobalModalBody').innerHTML = message;

    const iconContainer = document.getElementById('auraGlobalModalIcon');
    const iconBi = document.getElementById('auraGlobalModalIconBi');

    if (iconContainer && iconBi) {
        iconContainer.className = 'aura-icon-container ' + type;
        if (type === 'success') iconBi.className = 'bi bi-check-lg';
        else if (type === 'danger') iconBi.className = 'bi bi-x-lg';
        else if (type === 'warning') iconBi.className = 'bi bi-exclamation-triangle';
        else iconBi.className = 'bi bi-info-lg';
    }

    const m = new bootstrap.Modal(modalEl);
    m.show();
}

let formToSubmit = null;
function showAuraConfirm(title, message, formElementOrCallback) {
    const modalEl = document.getElementById('auraConfirmModal');
    if (!modalEl) {
        if (confirm(title + "\\n\\n" + message)) {
            if (typeof formElementOrCallback === 'function') formElementOrCallback();
            else formElementOrCallback.submit();
        }
        return;
    }

    document.getElementById('auraConfirmModalTitle').innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> ${title}`;
    document.getElementById('auraConfirmModalBody').innerHTML = message;

    formToSubmit = formElementOrCallback;

    // Asignar el listener al botón de confirmar solo una vez (limpiando previos clonando el obj)
    const btn = document.getElementById('auraConfirmModalBtn');
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);

    newBtn.addEventListener('click', () => {
        const m = bootstrap.Modal.getInstance(modalEl);
        m.hide();
        if (typeof formToSubmit === 'function') formToSubmit();
        else if (formToSubmit) formToSubmit.submit();
    });

    const m = new bootstrap.Modal(modalEl);
    m.show();
}

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
            if (!current_conversacion_id) { showAuraModal('Atención', 'Por favor selecciona un chat del panel lateral.', 'warning'); return; }
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
                        let isRegistration = auraObj.operacion !== 'CONSULTAR_DATO';

                        if (auraObj.datos) {
                            for (const [key, value] of Object.entries(auraObj.datos)) {
                                if (['id', 'activo', 'fecha_creacion', 'fecha_actualizacion'].includes(key)) continue;

                                let isMissing = auraObj.campos_faltantes && auraObj.campos_faltantes.includes(key);
                                let label = key.replace('_id', '').replace(/_/g, ' ').toUpperCase();

                                if (isRegistration) {
                                    let inputHtml = '';

                                    // Determinar el tipo de input según el nombre de la columna
                                    if (key === 'sexo') {
                                        let opts = [
                                            { v: '', t: 'Seleccione...' },
                                            { v: 'M', t: 'Masculino' },
                                            { v: 'F', t: 'Femenino' },
                                            { v: 'Otro', t: 'Otro' }
                                        ];
                                        let optHtml = opts.map(o => `<option value="${o.v}" ${value === o.v ? 'selected' : ''}>${o.t}</option>`).join('');
                                        let errClass = isMissing ? 'border-danger' : 'border-info';
                                        let sty = (isMissing && value === null) ? 'style="background-color: #ffeaea;"' : '';
                                        inputHtml = `<select class="form-select form-select-sm ${errClass}" ${sty} name="${key}">${optHtml}</select>`;
                                    }
                                    else if (key === 'tipo_sangre_id') {
                                        // Idealmente esto vendría de BD, pero mapeamos los básicos por ahora
                                        let opts = [
                                            { v: '', t: 'Desconocido...' },
                                            { v: '1', t: 'A+' }, { v: '2', t: 'A-' },
                                            { v: '3', t: 'B+' }, { v: '4', t: 'B-' },
                                            { v: '5', t: 'AB+' }, { v: '6', t: 'AB-' },
                                            { v: '7', t: 'O+' }, { v: '8', t: 'O-' }
                                        ];
                                        let optHtml = opts.map(o => `<option value="${o.v}" ${String(value) === String(o.v) ? 'selected' : ''}>${o.t}</option>`).join('');
                                        let errClass = isMissing ? 'border-danger' : 'border-info';
                                        let sty = (isMissing && value === null) ? 'style="background-color: #ffeaea;"' : '';
                                        inputHtml = `<select class="form-select form-select-sm ${errClass}" ${sty} name="${key}">${optHtml}</select>`;
                                    }
                                    else if (key.includes('fecha')) {
                                        let errClass = isMissing ? 'border-danger' : 'border-info';
                                        let sty = (isMissing && value === null) ? 'style="background-color: #ffeaea;"' : '';
                                        let maxToday = key === 'fecha_nacimiento' ? `max="${new Date().toISOString().split('T')[0]}"` : '';
                                        inputHtml = `<input type="date" class="form-control form-control-sm ${errClass}" ${sty} name="${key}" value="${value || ''}" ${maxToday}>`;
                                    }
                                    else {
                                        let errClass = isMissing ? 'border-danger' : (value !== null ? 'border-info' : '');
                                        let sty = (isMissing && value === null) ? 'style="background-color: #ffeaea;"' : '';
                                        let ph = isMissing ? 'placeholder="Falta dato..."' : '';
                                        inputHtml = `<input type="text" class="form-control form-control-sm ${errClass}" ${sty} name="${key}" value="${value || ''}" ${ph}>`;
                                    }

                                    let reqText = isMissing ? ' <span class="text-danger">(Requerido)</span>' : (value === null ? ' <span class="text-muted fw-normal">(Opcional)</span>' : '');

                                    dataExtractedHTML += `
                                    <div class="mb-2">
                                        <label class="form-label mb-0 text-muted" style="font-size: 0.75rem; font-weight: bold;">${label}${reqText}</label>
                                        ${inputHtml}
                                    </div>`;
                                } else {
                                    if (value !== null && typeof value !== 'object') {
                                        dataExtractedHTML += `<tr><td class="text-muted fw-semibold border-0" style="font-size:0.8rem; padding: 2px 5px;">${label}</td><td class="border-0" style="font-size:0.85rem; padding: 2px 5px;">${value}</td></tr>`;
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
                        }

                        let titleOp = isRegistration ? auraObj.operacion.replace('REGISTRAR_', 'Preregistro de ') : 'Extracción de Datos Clínicos';
                        let formId = 'auraActionForm_' + Date.now();
                        let contentHTML = isRegistration ? `<form id="${formId}" data-operation="${auraObj.operacion}">${dataExtractedHTML}</form>` : `<table class="table table-sm table-borderless mb-2"><tbody>${dataExtractedHTML}</tbody></table>`;

                        let buttonsHTML = '';
                        if (isRegistration) {
                            buttonsHTML = `
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end border-top pt-2 mt-2">
                                <button class="btn btn-sm btn-outline-secondary py-0" onclick="document.getElementById('${formId}').closest('.card').remove();">Descartar</button>
                                <button class="btn btn-sm btn-info text-white py-0 pe-auto" onclick="submitAuraForm('${formId}')">Registrar</button>
                            </div>`;
                        }

                        htmlCardComplemento = `
                        <div class="card border-info shadow-none mt-2" style="background-color: #f0fdfc;">
                            <div class="card-body p-2">
                                <h6 class="card-title text-info mb-1 fw-bold" style="font-size: 0.85rem;"><i class="bi bi-robot"></i> ${titleOp}</h6>
                                <div class="mb-2">${badgetsHTML}</div>
                                ${contentHTML}
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
                <div class="clearfix mt-2 mb-2">
                    <div class="wa-bubble-in shadow-sm" style="background-color: #fdfaf5; border: 1px solid #f67a7a; border-left: 5px solid #f67a7a; max-width: 80%;">
                        <p class="mb-1 text-danger fw-bold" style="font-size: 0.85rem;"><i class="bi bi-exclamation-circle-fill"></i> Aviso del Sistema AURA</p>
                        <p class="mb-0 text-dark" style="font-size: 0.9rem;">${data.mensaje || 'Respuesta no válida de la API'}</p>
                    </div>
                </div>`);
            }
        } catch (err) {
            document.getElementById(typingId).remove();
            chatArea.insertAdjacentHTML('beforeend', `
                <div class="clearfix mt-2 mb-2">
                    <div class="wa-bubble-in shadow-sm" style="background-color: #fdfaf5; border: 1px solid #f67a7a; border-left: 5px solid #f67a7a; max-width: 80%;">
                        <p class="mb-1 text-danger fw-bold" style="font-size: 0.85rem;"><i class="bi bi-wifi-off"></i> Error de Conexión</p>
                        <p class="mb-0 text-dark" style="font-size: 0.9rem;">No se pudo conectar al servidor local para procesar el mensaje.</p>
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
            showAuraModal("Incompatibilidad", "API de dictado por voz no soportada en este navegador (intenta en Chrome o Edge).", "warning");
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

            showAuraConfirm('Limpiar Chat', '¿Estás seguro de que deseas limpiar la conversación actual? Los mensajes se ocultarán de la pantalla.', async () => {
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
                        showAuraModal("Error", "Error al limpiar chat: " + data.mensaje, "danger");
                    }
                } catch (e) {
                    showAuraModal("Error", "Error de red intentando limpiar el chat.", "danger");
                }
            });
        });
    }

    // Función para guardar formulario predictivo
    window.submitAuraForm = async function (formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        // Simple frontend validation
        let hasErrors = false;
        form.querySelectorAll('.border-danger').forEach(el => {
            if (!el.value.trim()) hasErrors = true;
        });

        if (hasErrors) {
            showAuraModal('Faltan Datos', 'Por favor, llena los campos marcados en rojo antes de registrar.', 'warning');
            return;
        }

        const formData = new FormData(form);
        const payload = {
            operacion: form.getAttribute('data-operation'),
            datos: Object.fromEntries(formData.entries())
        };

        try {
            const res = await fetch('/clinica_app/api/save_aura.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.status === 'success') {
                form.closest('.card').remove();
                showAuraModal('Registro Exitoso', 'Los datos fueron insertados correctamente en el sistema.', 'success');
            } else {
                showAuraModal('Error al Registrar', data.mensaje || 'Hubo un problema al guardar los datos.', 'danger');
            }
        } catch (e) {
            showAuraModal('Error de Conexión', 'No se pudo contactar al servidor para el registro.', 'danger');
        }
    };
});

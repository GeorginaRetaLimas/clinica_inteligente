document.addEventListener('DOMContentLoaded', function () {
    const btnMic = document.getElementById('btnMic');
    const btnSendText = document.getElementById('btnSendText');
    const input = document.getElementById('chatInput');

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
});

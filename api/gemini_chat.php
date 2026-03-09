<?php
header('Content-Type: application/json; charset=utf-8');

// Primero cargamos las variables de entorno
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/audit.php'; // Para encriptarMensajeAURA

// Validar que un médico/usuario esté solicitando esto
if (!isLogged()) {
    echo json_encode(['status' => 'error', 'mensaje' => 'No autorizado']);
    exit;
}

// Recibir JSON del frontend
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
$texto_clinico = $input['texto'] ?? '';
$conversacion_id = $input['conversacion_id'] ?? null;

if (empty($texto_clinico) || !$conversacion_id) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Texto o conversación vacío.']);
    exit;
}

// 1. Guardar mensaje del Médico en BD
$cifrado_medico = encriptarMensajeAURA($texto_clinico);
$stmt = $pdo->prepare("INSERT INTO mensajes_aura (conversacion_id, remitente, contenido_cifrado, iv_hex, tipo_mensaje) VALUES (?, 'medico', ?, ?, 'texto')");
$stmt->execute([$conversacion_id, $cifrado_medico['cifrado'], $cifrado_medico['iv_hex']]);
// actualizar ultimo acceso
$pdo->prepare("UPDATE conversaciones_aura SET ultimo_mensaje_at = NOW() WHERE id = ?")->execute([$conversacion_id]);

// Configuración de Gemini API leyendo desde el archivo .env
$apiKey = $_ENV['GEMINI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'mensaje' => 'API Key no configurada en el servidor.']);
    exit;
}

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

// Recuperar contexto del paciente si la conversación está atada a uno
$contexto_paciente = null;
$stmt_conv = $pdo->prepare("SELECT paciente_id FROM conversaciones_aura WHERE id = ? AND tipo = 'paciente'");
$stmt_conv->execute([$conversacion_id]);
$pid = $stmt_conv->fetchColumn();

if ($pid) {
    // Traer datos crudos del paciente
    $stmt_pac = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
    $stmt_pac->execute([$pid]);
    $datos_pac = $stmt_pac->fetch(PDO::FETCH_ASSOC);

    // Traer últimos 3 expedientes relevantes
    $stmt_exp = $pdo->prepare("SELECT fecha_consulta, motivo_consulta, diagnostico, tratamiento, medicamentos FROM expedientes WHERE paciente_id = ? AND activo = 1 ORDER BY fecha_consulta DESC LIMIT 3");
    $stmt_exp->execute([$pid]);
    $expedientes = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

    $contexto_paciente = [
        'perfil' => $datos_pac,
        'ultimos_expedientes' => $expedientes
    ];
}

$contexto_actual = json_encode([
    'medico_id' => $_SESSION['medico_id'],
    'paciente_activo' => $contexto_paciente,
    'fecha_hora_actual' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);

$system_prompt = file_get_contents('../prompt_sistema_aura.txt');
if (!$system_prompt)
    $system_prompt = "Eres un asistente médico inteligente. Responde siempre en JSON.";

// Inicialización del Payload para la IA nativa
$texto_final_sistema = $system_prompt . "\n\n=== CONTEXTO ACTUAL ===\n" . $contexto_actual;

$data = [
    "systemInstruction" => [
        "parts" => [
            ["text" => $texto_final_sistema]
        ]
    ],
    "contents" => [
        [
            "role" => "user",
            "parts" => [
                ["text" => $texto_clinico]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.1,
        "responseMimeType" => "application/json"
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apagado temporalmente para entornos XAMPP

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(["status" => "error", "mensaje" => "Fallo conexión: " . $error]);
}
elseif ($httpcode !== 200) {
    $resDecoded = json_decode($response, true);
    echo json_encode(["status" => "error", "mensaje" => "Error de API Gemini HTTP $httpcode", "debug" => $resDecoded]);
}
else {
    $resDecoded = json_decode($response, true);
    $geminiText = $resDecoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No se pudo generar una respuesta.';

    // 2. Guardar mensaje de la IA en BD
    $cifrado_ia = encriptarMensajeAURA($geminiText);
    $stmt = $pdo->prepare("INSERT INTO mensajes_aura (conversacion_id, remitente, contenido_cifrado, iv_hex, tipo_mensaje) VALUES (?, 'aura', ?, ?, 'texto')");
    $stmt->execute([$conversacion_id, $cifrado_ia['cifrado'], $cifrado_ia['iv_hex']]);

    echo json_encode([
        "status" => "success",
        "respuesta" => $geminiText
    ]);
}
?>

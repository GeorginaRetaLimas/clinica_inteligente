<?php
header('Content-Type: application/json; charset=utf-8');

// Primero cargamos las variables de entorno (al estar integrado en auth/db se podría, pero es mejor incluir db primero)
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Validar que un médico/usuario esté solicitando esto
if (!isLogged()) {
    echo json_encode(['status' => 'error', 'mensaje' => 'No autorizado']);
    exit;
}

// Recibir JSON del frontend
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
$texto_clinico = $input['texto'] ?? '';

if (empty($texto_clinico)) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Texto vacío']);
    exit;
}

// Configuración de Gemini API leyendo desde el archivo .env
$apiKey = $_ENV['GEMINI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'mensaje' => 'API Key no configurada en el servidor.']);
    exit;
}

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey;

// Instrucción de sistema para Gemini
$prompt = "Eres un Asistente Clínico Inteligente apoyando a un médico. Evalúa o estructura la siguiente nota clínica o responde de manera concisa y profesional. La nota es:\n\n" . $texto_clinico;

$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apagado temporalmente para entornos XAMPP

$response = curl_execute($ch);
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

    echo json_encode([
        "status" => "success",
        "respuesta" => $geminiText
    ]);
}
?>

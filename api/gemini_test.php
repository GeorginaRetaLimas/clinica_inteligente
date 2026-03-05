<?php
// Script base para probar conexión con Gemini API
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/db.php'; // Esto carga automáticamente las variables de entorno de .env

$apiKey = $_ENV['GEMINI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['status' => 'error', 'mensaje' => 'API Key no configurada en el servidor (.env).']);
    exit;
}

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey;

// Prompt o texto básico a procesar
$texto_clinico = "¿Cuáles son los signos vitales estándar de un adulto sano?";

// Payload básico solicitado por la API
$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $texto_clinico]
            ]
        ]
    ]
];

// Inicializar cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apagado temporalmente

// Ejecutar conexión y obtener respuesta
$response = curl_execute($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Validamos conexión
if ($error) {
    echo json_encode(["status" => "error", "mensaje" => "Fallo conexión cURL: " . $error]);
}
else {
    // Retornamos JSON con estado HTTP y data en crudo para confirmación de conexión exitosa
    echo json_encode([
        "status" => $httpcode == 200 ? "success" : "api_error",
        "http_code" => $httpcode,
        "raw_response" => json_decode($response)
    ], JSON_PRETTY_PRINT);
}
?>

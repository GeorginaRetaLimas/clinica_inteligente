<?php
/**
 * includes/audit.php
 * 
 * Contiene la lógica para la Firma de Cadena de Bloques (Blockchain)
 * en la tabla de auditoría, garantizando la inmutabilidad de los logs.
 * 
 * También contiene las directivas de seguridad para cifrado (AES-256-CBC) de los mensajes.
 */

/**
 * Registra un evento en la tabla 'auditoria' encadenándolo al bloque anterior (Blockchain)
 */
function registrarAuditoria($pdo, $medico_id, $tabla, $registro_id, $accion, $datos_antes = null, $datos_despues = null)
{
    // 1. Obtener el hash del "bloque" anterior
    $stmt = $pdo->query("SELECT hash_actual FROM auditoria ORDER BY id DESC LIMIT 1");
    $lastAudit = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si no hay registros previos, usamos el bloque génesis
    $hash_anterior = $lastAudit ? $lastAudit['hash_actual'] : hash('sha256', 'genesis_block_aura_2026');

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $datos_antes_json = $datos_antes !== null ? json_encode($datos_antes) : null;
    $datos_despues_json = $datos_despues !== null ? json_encode($datos_despues) : null;

    // 2. Crear la cadena actual concatenando los datos críticos para firmar el nuevo bloque
    $cadena_actual = $hash_anterior . $medico_id . $tabla . $registro_id . $accion . $datos_antes_json . $datos_despues_json . $ip . $ua;

    // 3. Generar el nuevo Hash SHA-256 (El sello inmutable)
    $hash_actual = hash('sha256', $cadena_actual);

    // 4. Inserción en la base de datos
    $sql = "INSERT INTO auditoria (medico_id, tabla, registro_id, accion, datos_antes, datos_despues, ip_origen, user_agent, hash_anterior, hash_actual) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $medico_id,
        $tabla,
        $registro_id,
        $accion,
        $datos_antes_json,
        $datos_despues_json,
        $ip,
        $ua,
        $hash_anterior,
        $hash_actual
    ]);
}

/**
 * Encripta un mensaje de chat para guardarlo en la Base de Datos
 * Usa AES-256-CBC
 * @param string $texto Texto en claro
 * @return array Retorna un arreglo con ['cifrado'] y tu ['iv_hex']
 */
function encriptarMensajeAURA($texto)
{
    // Tomamos la clave de entorno guardada en .env
    $key = $_ENV['CHAT_ENCRYPT_KEY'] ?? '';

    // Vector de Inicialización seguro y único
    $iv = random_bytes(16);

    // Cifrar contenido
    $cifrado = openssl_encrypt($texto, 'AES-256-CBC', $key, 0, $iv);

    return [
        'cifrado' => $cifrado,
        'iv_hex' => bin2hex($iv)
    ];
}

/**
 * Desencripta un mensaje de chat desde la Base de Datos
 * @param string $cifrado El contenido cifrado
 * @param string $iv_hex El decodificador Hexagonal guardado para ese registro
 * @return string Original desencriptado
 */
function desencriptarMensajeAURA($cifrado, $iv_hex)
{
    if (!$cifrado)
        return "";
    $key = $_ENV['CHAT_ENCRYPT_KEY'] ?? '';
    $iv = hex2bin($iv_hex);
    return openssl_decrypt($cifrado, 'AES-256-CBC', $key, 0, $iv);
}

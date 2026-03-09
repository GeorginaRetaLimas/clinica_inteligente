<?php
require_once 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE mensajes_aura ADD COLUMN activo TINYINT(1) DEFAULT 1 AFTER leido;");
    echo "Columna agregada exitosamente.";
}
catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "La columna ya existe.";
    }
    else {
        echo "Error: " . $e->getMessage();
    }
}
?>

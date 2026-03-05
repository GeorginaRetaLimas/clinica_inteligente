<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->prepare("SELECT id FROM medicos WHERE email = 'admin'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO medicos (rol_id, nombre, apellido_paterno, email, password_hash) VALUES (1, 'Administrador', 'Sistema', 'admin', ?)");
        $insert->execute([$hash]);
        echo "Usuario admin creado correctamente. <a href='login.php'>Ir al login</a>";
    }
    else {
        echo "Usuario admin ya existe. <a href='login.php'>Ir al login</a>";
    }
}
catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

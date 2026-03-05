<?php
session_start();

function isLogged()
{
    return isset($_SESSION['medico_id']);
}

function requireLogin()
{
    if (!isLogged()) {
        header('Location: /clinica_app/login.php');
        exit;
    }
}

// Relajamos validación de Admin, un médico ya puede ver la mayoría
function requireAdmin()
{
    requireLogin();
// if ($_SESSION['user_rol'] !== 'admin') {
//     die('Acceso denegado. Se requiere rol de administrador.');
// }
}

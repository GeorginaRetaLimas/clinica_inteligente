<?php
require_once 'includes/db.php';
session_start();

if (isset($_SESSION['medico_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (!empty($email) && !empty($pass)) {
        $stmt = $pdo->prepare("SELECT id, rol_id, nombre, apellido_paterno, password_hash, activo FROM medicos WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $medico = $stmt->fetch();

        if ($medico && password_verify($pass, $medico['password_hash'])) {
            if ($medico['activo']) {
                $_SESSION['medico_id'] = $medico['id'];
                $_SESSION['medico_nombre'] = $medico['nombre'] . ' ' . $medico['apellido_paterno'];
                $_SESSION['rol_id'] = $medico['rol_id'];

                header('Location: index.php');
                exit;
            }
            else {
                $error = 'Su cuenta está inactiva. Contacte al administrador.';
            }
        }
        else {
            $error = 'Credenciales incorrectas.';
        }
    }
    else {
        $error = 'Por favor, ingrese correo y contraseña.';
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<!-- Inyectar CSS dedicado del login y limpiar márgenes del container global -->
<link href="/clinica_app/public/assets/css/login.css" rel="stylesheet">
<script>document.body.classList.add('login-page');</script>

<div class="login-wrapper">
    <div class="login-glass-panel text-center">
        
        <img src="/clinica_app/public/assets/img/logo.png" alt="AURA Logo" class="login-logo mx-auto d-block" />
        
        <h2 class="login-title">Bienvenido a AURA</h2>
        <p class="login-subtitle">Por favor, inicie sesión en su cuenta.</p>
        
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 w-100 mx-auto" style="max-width: 400px;"><?php echo htmlspecialchars($error); ?></div>
        <?php
endif; ?>

        <form method="post" action="login.php" class="mx-auto" style="max-width: 400px; text-align: left;">
            <div class="mb-3">
                <label for="email" class="form-label fw-bold" style="color:#6c757d;">Correo Electrónico o Usuario</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control login-input" id="email" name="email" placeholder="Usuario o correo electrónico" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label fw-bold" style="color:#6c757d;">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control login-input" id="password" name="password" placeholder="Su contraseña" required>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-login text-uppercase">Ingresar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

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

<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <img src="/clinica_app/public/assets/img/logo_horizontal.png" alt="AURA" class="img-fluid" style="max-height: 80px;">
                    <p class="text-muted mt-2">Inicio de Sesión</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
                <?php
endif; ?>

                <form method="post" action="login.php">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control rounded-3" id="email" name="email" placeholder="Correo o usuario" required>
                        <label for="email"><i class="bi bi-person text-muted"></i> Correo Electrónico</label>
                    </div>
                    
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control rounded-3" id="password" name="password" placeholder="Contraseña" required>
                        <label for="password"><i class="bi bi-lock text-muted"></i> Contraseña</label>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-info btn-lg text-white text-uppercase fw-semibold rounded-3 pt-2 pb-2">Ingresar</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <small class="text-muted">Si no tiene cuenta, solicítela al administrador.</small>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

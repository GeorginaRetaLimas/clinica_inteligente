<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/audit.php';
requireLogin();

// SEGURIDAD: Solo Administradores pueden acceder a este módulo
if ($_SESSION['rol_id'] != 1) {
    echo "<div style='padding:50px; text-align:center;'><h2>Acceso Denegado</h2><p>Solo los administradores pueden gestionar el personal médico.</p><a href='../index.php'>Volver al inicio</a></div>";
    exit;
}

$admin_id = $_SESSION['medico_id'];

// Manejo del POST (CRUD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    $nombre = $_POST['nombre'] ?? '';
    $ap = $_POST['apellido_paterno'] ?? '';
    $am = $_POST['apellido_materno'] ?? '';
    $cedula = $_POST['cedula_profesional'] ?? null;
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? null;
    $rol_id = (int)($_POST['rol_id'] ?? 2);

    if ($action === 'add') {
        $password = $_POST['password'] ?? '123456';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO medicos (rol_id, nombre, apellido_paterno, apellido_materno, cedula_profesional, email, password_hash, telefono) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rol_id, $nombre, $ap, $am, $cedula, $email, $hash, $telefono]);
        $nuevo_id = $pdo->lastInsertId();

        $datos_despues = ['id' => $nuevo_id, 'rol_id' => $rol_id, 'nombre' => $nombre, 'apellido' => $ap, 'email' => $email];
        registrarAuditoria($pdo, $admin_id, 'medicos', $nuevo_id, 'INSERT', null, $datos_despues);

        header('Location: medicos.php?msg=added');
        exit;
    }
    elseif ($action === 'edit') {
        $id = (int)$_POST['id'];

        $stmt = $pdo->prepare("SELECT * FROM medicos WHERE id = ?");
        $stmt->execute([$id]);
        $datos_antes = $stmt->fetch();

        if ($datos_antes) {
            $sql = "UPDATE medicos SET rol_id = ?, nombre = ?, apellido_paterno = ?, apellido_materno = ?, cedula_profesional = ?, email = ?, telefono = ? WHERE id = ?";
            $params = [$rol_id, $nombre, $ap, $am, $cedula, $email, $telefono, $id];

            // Si envió password, actualizarlo
            if (!empty($_POST['password'])) {
                $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $sql = "UPDATE medicos SET rol_id = ?, nombre = ?, apellido_paterno = ?, apellido_materno = ?, cedula_profesional = ?, email = ?, telefono = ?, password_hash = ? WHERE id = ?";
                $params = [$rol_id, $nombre, $ap, $am, $cedula, $email, $telefono, $hash, $id];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $datos_despues = $datos_antes;
            $datos_despues['nombre'] = $nombre;
            $datos_despues['rol_id'] = $rol_id;

            registrarAuditoria($pdo, $admin_id, 'medicos', $id, 'UPDATE', $datos_antes, $datos_despues);
        }
        header('Location: medicos.php?msg=updated');
        exit;
    }
    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];

        // No permitir borrarse a sí mismo si es el único admin, o si es la sesión actual para evitar cierres erráticos
        if ($id == $admin_id) {
            header('Location: medicos.php?msg=error_self');
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM medicos WHERE id = ?");
        $stmt->execute([$id]);
        $datos_antes = $stmt->fetch();

        if ($datos_antes) {
            $pdo->prepare("UPDATE medicos SET activo = 0 WHERE id = ?")->execute([$id]);
            $datos_despues = $datos_antes;
            $datos_despues['activo'] = 0;
            registrarAuditoria($pdo, $admin_id, 'medicos', $id, 'DELETE', $datos_antes, $datos_despues);
        }
        header('Location: medicos.php?msg=deleted');
        exit;
    }
}

// El Administrador debe ver a todos los médicos/administradores para tener control
$medicos = $pdo->query("SELECT m.*, r.nombre as nombre_rol FROM medicos m JOIN roles r ON m.rol_id = r.id")->fetchAll();
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();

require_once '../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-9">
        <h2 class="fw-bold"><i class="bi bi-person-badge text-info"></i> Gestión de Personal</h2>
        <p class="text-muted mb-0">Directorio de Médicos y Administradores del Sistema.</p>
    </div>
    <div class="col-md-3 text-end">
        <button class="btn btn-info text-white fw-bold" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="bi bi-plus-circle"></i> Nuevo Empleado
        </button>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if(typeof showAuraModal === 'function') {
                <?php
    $modalMsg = '';
    $modalType = 'info';
    if ($_GET['msg'] === 'added')
        $modalMsg = "Personal agregado exitosamente.";
    if ($_GET['msg'] === 'updated')
        $modalMsg = "Datos actualizados.";
    if ($_GET['msg'] === 'deleted')
        $modalMsg = "Usuario dado de baja (borrado lógico).";
    if ($_GET['msg'] === 'error_self') {
        $modalMsg = "No puedes eliminar tu propia cuenta mientras estás logueado.";
        $modalType = "danger";
    }
    echo "showAuraModal('Información del Sistema', '" . addslashes($modalMsg) . "', '" . $modalType . "');";
?>
            }
        });
    </script>
<?php
endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Rol</th>
                        <th>Nombre</th>
                        <th>Cédula Prof.</th>
                        <th>Email / Usuario</th>
                        <th>Teléfono</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicos as $m): ?>
                        <tr class="<?php echo($m['activo'] == 0) ? 'opacity-50 bg-light' : ''; ?>">
                            <td><?php echo $m['id']; ?></td>
                            <td><span class="badge bg-<?php echo($m['rol_id'] == 1) ? 'danger' : 'primary'; ?>"><?php echo strtoupper($m['nombre_rol']); ?></span></td>
                            <td class="fw-semibold">
                                <?php echo htmlspecialchars($m['nombre'] . ' ' . $m['apellido_paterno'] . ' ' . $m['apellido_materno']); ?>
                                <?php if ($m['activo'] == 0): ?><br><span class="badge bg-danger">Inactivo / Baja</span><?php
    endif; ?>
                            </td>
                            <td><span class="text-secondary font-monospace"><?php echo htmlspecialchars($m['cedula_profesional'] ?? 'N/A'); ?></span></td>
                            <td><?php echo htmlspecialchars($m['email']); ?></td>
                            <td><?php echo htmlspecialchars($m['telefono'] ?? '-'); ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick="editMedico(<?php echo htmlspecialchars(json_encode($m)); ?>)"><i class="bi bi-pencil"></i></button>
                                <?php if ($m['id'] != $admin_id && $m['activo'] == 1): ?>
                                <form method="POST" action="" style="display:inline-block;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="showAuraConfirm('Dar de Baja', '¿Dar de baja a este usuario? Ya no podrá acceder al sistema.', this.closest('form'))"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php
    endif; ?>
                            </td>
                        </tr>
                    <?php
endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Agregar -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Agregar Empleado</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-12">
                <label>Rol</label>
                <select name="rol_id" class="form-select" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo($r['id'] == 2) ? 'selected' : ''; ?>><?php echo strtoupper($r['nombre']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label>Nombre</label><input type="text" name="nombre" class="form-control" required></div>
            <div class="col-md-4"><label>Apellido Paterno</label><input type="text" name="apellido_paterno" class="form-control" required></div>
            <div class="col-md-4"><label>Apellido Materno</label><input type="text" name="apellido_materno" class="form-control"></div>
            <div class="col-md-6"><label>Cédula Profesional</label><input type="text" name="cedula_profesional" class="form-control" maxlength="20"></div>
            <div class="col-md-6"><label>Teléfono</label><input type="text" name="telefono" class="form-control" maxlength="15"></div>
            <div class="col-md-6"><label>Email (Login)</label><input type="text" name="email" class="form-control" required></div>
            <div class="col-md-6"><label>Contraseña</label><input type="password" name="password" class="form-control" required></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-info text-white">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Editar Empleado</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="row g-3">
            <div class="col-md-12">
                <label>Rol</label>
                <select name="rol_id" id="edit_rol" class="form-select" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo strtoupper($r['nombre']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label>Nombre</label><input type="text" name="nombre" id="edit_nombre" class="form-control" required></div>
            <div class="col-md-4"><label>Apellido Paterno</label><input type="text" name="apellido_paterno" id="edit_ap" class="form-control" required></div>
            <div class="col-md-4"><label>Apellido Materno</label><input type="text" name="apellido_materno" id="edit_am" class="form-control"></div>
            <div class="col-md-6"><label>Cédula Profesional</label><input type="text" name="cedula_profesional" id="edit_cedula" class="form-control" maxlength="20"></div>
            <div class="col-md-6"><label>Teléfono</label><input type="text" name="telefono" id="edit_telefono" class="form-control" maxlength="15"></div>
            <div class="col-md-6"><label>Email</label><input type="text" name="email" id="edit_email" class="form-control" required></div>
            <div class="col-md-6"><label>Nueva Contraseña (Opcional)</label><input type="password" name="password" class="form-control" placeholder="Dejar en blanco para conservar"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary text-white">Actualizar</button>
      </div>
    </form>
  </div>
</div>

<script>
function editMedico(m) {
    document.getElementById('edit_id').value = m.id;
    document.getElementById('edit_rol').value = m.rol_id;
    document.getElementById('edit_nombre').value = m.nombre;
    document.getElementById('edit_ap').value = m.apellido_paterno;
    document.getElementById('edit_am').value = m.apellido_materno;
    document.getElementById('edit_cedula').value = m.cedula_profesional;
    document.getElementById('edit_telefono').value = m.telefono;
    document.getElementById('edit_email').value = m.email;
    new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>

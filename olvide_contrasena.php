<?php

/**
 * Modulo: solicitud de recuperacion.
 * Responsabilidad: generar y enviar codigo temporal para recuperar contrasena.
 */

require_once __DIR__ . '/config/conexion_DB.php';
require_once __DIR__ . '/config/funciones_mail.php';

$conexion = obtener_conexion_db();
$errores = [];
$correo = '';

if (usuario_actual()) {
    redirigir('/index.php');
}

if (es_post()) {
    $correo = limpiar_entrada((string) ($_POST['correo'] ?? ''));

    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresa un correo valido.';
    }

    if ($errores === []) {
        $usuario = obtener_usuario_por_correo($conexion, $correo);

        /* Si el correo existe, enviamos el codigo. Si no existe, usamos un
           mensaje generico para no revelar cuentas registradas. */
        if ($usuario && (int) $usuario['activo'] === 1) {
            $nombreCompleto = trim((string) $usuario['nombre'] . ' ' . (string) $usuario['apellido']);
            $codigo = crear_codigo_usuario($conexion, (int) $usuario['id_usuario'], 'recuperacion_contrasena', 2);

            $_SESSION['recuperacion_contrasena_pendiente'] = [
                'id_usuario' => (int) $usuario['id_usuario'],
                'mail' => (string) $usuario['mail'],
                'nombre' => $nombreCompleto,
                'vence_en' => time() + 120,
            ];

            if (!enviar_mail_recuperacion_contrasena((string) $usuario['mail'], $nombreCompleto, $codigo)) {
                $errores[] = 'No pudimos enviar el codigo. Intenta nuevamente.';
                unset($_SESSION['recuperacion_contrasena_pendiente']);
            } else {
            guardar_flash('mensaje_exito', 'Te enviamos un codigo para restablecer tu contrasena. Vence en 2 minutos.');
            redirigir('/restablecer_contrasena.php');
            }
        }

        if ($errores === []) {
            guardar_flash('mensaje_exito', 'Si el correo existe, te enviamos un codigo para restablecer tu contrasena.');
            redirigir('/login.php');
        }
    }
}

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario">
    <h1 class="tarjeta-formulario__titulo">Recuperar contraseña</h1>
    <p class="tarjeta-formulario__texto">Ingresá tu correo y te enviaremos un código para crear una nueva contraseña.</p>

    <?php foreach ($errores as $error): ?>
        <div class="mensaje-vacio mensaje-vacio--error u-mb-18"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <form method="post">
        <div class="grupo-campo">
            <label for="correo">Correo electrónico</label>
            <input class="campo-texto" id="correo" type="email" name="correo" required value="<?php echo sanear_texto($correo); ?>">
        </div>

        <button class="boton-principal tarjeta-accion" type="submit">Enviar código</button>
    </form>

    <p class="texto-acceso"><a class="enlace-suave" href="/login.php">Volver al inicio de sesión</a></p>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>

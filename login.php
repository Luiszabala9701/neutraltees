<?php

/**
 * Modulo: inicio de sesion.
 * Responsabilidad: autenticar usuarios/admins y reenviar verificacion si falta.
 */

require_once __DIR__ . '/config/conexion_DB.php';
require_once __DIR__ . '/config/funciones_mail.php';
$conexion = obtener_conexion_db();

if (usuario_actual()) {
    if (es_admin()) {
        redirigir('/admin/index.php');
    }

    redirigir('/index.php');
}

$errores = [];

if (es_post()) {
    $correo = trim(strip_tags((string) ($_POST['correo'] ?? '')));
    $contrasena = (string) ($_POST['contrasena'] ?? '');

    $usuario = obtener_usuario_por_correo($conexion, $correo);

    if (!$usuario || (int) $usuario['activo'] !== 1) {
        $errores[] = 'Login incorrecto.';
    } elseif (!password_verify($contrasena, (string) $usuario['password'])) {
        $errores[] = 'Login incorrecto.';
    } elseif ((int) ($usuario['email_verificado'] ?? 0) !== 1) {
        $nombreCompleto = trim((string) $usuario['nombre'] . ' ' . (string) $usuario['apellido']);
        $codigoVerificacion = crear_codigo_usuario($conexion, (int) $usuario['id_usuario'], 'verificacion_email', 2);

        $_SESSION['verificacion_email_pendiente'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'mail' => (string) $usuario['mail'],
            'nombre' => $nombreCompleto,
            'vence_en' => time() + 120,
        ];

        if (!enviar_mail_verificacion((string) $usuario['mail'], $nombreCompleto, $codigoVerificacion)) {
            $errores[] = 'No pudimos enviar el codigo de verificacion. Intenta nuevamente.';
            unset($_SESSION['verificacion_email_pendiente']);
        } else {
        guardar_flash('mensaje_exito', 'Te enviamos un nuevo codigo de verificacion. Vence en 2 minutos.');
        redirigir('/verificar_email.php');
        }
    } else {
        $_SESSION['usuario_actual'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nombre' => $usuario['nombre'],
            'apellido' => $usuario['apellido'],
            'mail' => $usuario['mail'],
            'is_admin' => (int) $usuario['is_admin'] === 1,
            'email_verificado' => true,
        ];
        registrar_actividad_sesion();

        guardar_flash('mensaje_exito', 'Inicio de sesión exitoso.');

        if ((int) $usuario['is_admin'] === 1) {
            redirigir('/admin/index.php');
        }

        redirigir('/index.php');
    }
}

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario">
    <h1 class="tarjeta-formulario__titulo">Ingresar</h1>
    <p class="tarjeta-formulario__texto">Accedé a tu cuenta para comprar y revisar pedidos.</p>

    <?php foreach ($errores as $error): ?>
        <div class="mensaje-vacio mensaje-vacio--error u-mb-18"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <form method="post">
        <div class="grupo-campo">
            <label for="correo">Correo electrónico</label>
            <input class="campo-texto" id="correo" type="email" name="correo" required>
        </div>

        <div class="grupo-campo grupo-contrasena">
            <label for="contrasena">Contraseña</label>
            <div class="grupo-contrasena__control">
                <input class="campo-texto" id="contrasena" type="password" name="contrasena" required autocomplete="current-password">
                <button class="boton-ojito" type="button" data-boton-contrasena="#contrasena" title="Mostrar contraseña" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
                    </svg>
                </button>
            </div>
        </div>

        <button class="boton-principal tarjeta-accion" type="submit">Ingresar</button>
    </form>

    <p class="texto-acceso"><a class="enlace-suave" href="/olvide_contrasena.php">¿Olvidaste tu contraseña?</a></p>
    <p class="texto-acceso">¿No tenés cuenta? <a class="enlace-suave" href="/registro.php">Creá una ahora.</a></p>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>

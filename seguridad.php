<?php

/**
 * Modulo: seguridad del perfil.
 * Responsabilidad: cambiar contrasena con clave actual o iniciar recuperacion.
 */

require_once __DIR__ . '/config/conexion_DB.php';
$conexion = obtener_conexion_db();
$usuario = usuario_actual();

if (!$usuario) {
    guardar_flash('mensaje_error', 'Debés iniciar sesión para cambiar tu contraseña.');
    redirigir('/login.php');
}

$errores = [];

if (es_post()) {
    $accion = limpiar_entrada((string) ($_POST['accion'] ?? 'actualizar_contrasena'));

    if ($accion === 'enviar_codigo_recuperacion') {
        require_once __DIR__ . '/config/funciones_mail.php';

        $nombreCompleto = trim((string) $usuario['nombre'] . ' ' . (string) $usuario['apellido']);
        $codigo = crear_codigo_usuario($conexion, (int) $usuario['id_usuario'], 'recuperacion_contrasena', 2);
        $_SESSION['recuperacion_contrasena_pendiente'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'mail' => (string) $usuario['mail'],
            'nombre' => $nombreCompleto,
            'vence_en' => time() + 120,
        ];

        if (enviar_mail_recuperacion_contrasena((string) $usuario['mail'], $nombreCompleto, $codigo)) {
            guardar_flash('mensaje_exito', 'Te enviamos un codigo para cambiar tu contrasena. Vence en 2 minutos.');
            redirigir('/restablecer_contrasena.php');
        }

        $errores[] = 'No pudimos enviar el codigo. Intenta nuevamente.';
    }

    if ($accion === 'actualizar_contrasena') {
        $contrasenaActual = (string) ($_POST['contrasena_actual'] ?? '');
        $nuevaContrasena = (string) ($_POST['nueva_contrasena'] ?? '');
        $confirmarContrasena = (string) ($_POST['confirmar_contrasena'] ?? '');

        $sentencia = $conexion->prepare('SELECT password FROM usuario WHERE id_usuario = :id_usuario LIMIT 1');
        $sentencia->execute([':id_usuario' => (int) $usuario['id_usuario']]);
        $hashActual = (string) ($sentencia->fetchColumn() ?: '');

        if (!password_verify($contrasenaActual, $hashActual)) {
            $errores[] = 'La contraseña actual no es correcta.';
        }

        if ($hashActual !== '' && password_verify($nuevaContrasena, $hashActual)) {
            $errores[] = 'La nueva contraseña debe ser diferente a la actual.';
        }

        if ($nuevaContrasena !== $confirmarContrasena) {
            $errores[] = 'Las contraseñas nuevas no coinciden.';
        }

        foreach (validar_contrasena_segura($nuevaContrasena, 'La nueva contraseña') as $errorContrasena) {
            $errores[] = $errorContrasena;
        }

        if ($errores === []) {
            $sentenciaActualizar = $conexion->prepare('UPDATE usuario SET password = :password, fecha_actualizacion = NOW() WHERE id_usuario = :id_usuario');
            $sentenciaActualizar->execute([
                ':password' => password_hash($nuevaContrasena, PASSWORD_DEFAULT),
                ':id_usuario' => (int) $usuario['id_usuario'],
            ]);

            guardar_flash('mensaje_exito', 'Tu contraseña fue actualizada.');
            redirigir('/perfil.php');
        }
    }
}

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario">
    <h1 class="tarjeta-formulario__titulo">Seguridad</h1>
    <p class="tarjeta-formulario__texto">Actualizá tu contraseña de acceso.</p>

    <?php foreach ($errores as $error): ?>
        <div class="mensaje-vacio mensaje-vacio--error u-mb-18"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <form method="post">
        <div class="grupo-campo grupo-contrasena">
            <label for="contrasena_actual">Contraseña actual</label>
            <div class="grupo-contrasena__control">
                <input class="campo-texto" id="contrasena_actual" type="password" name="contrasena_actual" required>
                <button class="boton-ojito" type="button" data-boton-contrasena="#contrasena_actual" title="Mostrar contraseña" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="grupo-campo grupo-contrasena">
            <label for="nueva_contrasena">Nueva contraseña</label>
            <div class="grupo-contrasena__control">
                <input class="campo-texto" id="nueva_contrasena" type="password" name="nueva_contrasena" required data-campo-contrasena data-objetivo-ayuda="#ayuda-nueva">
                <button class="boton-ojito" type="button" data-boton-contrasena="#nueva_contrasena" title="Mostrar contraseña" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
                    </svg>
                </button>
            </div>
            <div id="ayuda-nueva" class="ayuda-contrasena" data-panel-ayuda-contrasena hidden>
                <ul class="lista-validacion lista-validacion--dinamica" data-lista-validacion-contrasena>
                    <li data-regla-contrasena="longitud">Mínimo 8 caracteres y máximo 16.</li>
                    <li data-regla-contrasena="numero">Al menos 1 número.</li>
                    <li data-regla-contrasena="letra">Al menos 1 letra.</li>
                    <li data-regla-contrasena="especial">Al menos 1 carácter especial.</li>
                </ul>
            </div>
        </div>

        <div class="grupo-campo grupo-contrasena">
            <label for="confirmar_contrasena">Confirmar contraseña</label>
            <div class="grupo-contrasena__control">
                <input class="campo-texto" id="confirmar_contrasena" type="password" name="confirmar_contrasena" required data-campo-contrasena data-objetivo-ayuda="#ayuda-confirmar" data-objetivo-comparar="#nueva_contrasena">
                <button class="boton-ojito" type="button" data-boton-contrasena="#confirmar_contrasena" title="Mostrar contraseña" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
                    </svg>
                </button>
            </div>
            <div id="ayuda-confirmar" class="ayuda-contrasena" data-panel-ayuda-contrasena hidden>
                <ul class="lista-validacion lista-validacion--dinamica" data-lista-validacion-contrasena>
                    <li data-regla-contrasena="longitud">Mínimo 8 caracteres y máximo 16.</li>
                    <li data-regla-contrasena="numero">Al menos 1 número.</li>
                    <li data-regla-contrasena="letra">Al menos 1 letra.</li>
                    <li data-regla-contrasena="especial">Al menos 1 carácter especial.</li>
                    <li data-regla-contrasena="coincidencia">Coincidir contraseña.</li>
                </ul>
            </div>
        </div>

        <input type="hidden" name="accion" value="actualizar_contrasena">
        <button class="boton-principal tarjeta-accion" type="submit">Actualizar contraseña</button>
    </form>

    <form method="post" class="texto-acceso">
        <input type="hidden" name="accion" value="enviar_codigo_recuperacion">
        <span>¿No recordás tu contraseña actual?</span>
        <button class="enlace-suave enlace-boton" type="submit">recuperar por mail</button>
    </form>

    <div class="seguridad-zona-peligro">
        <h2 class="seguridad-zona-peligro__titulo">Eliminar cuenta</h2>
        <p class="seguridad-zona-peligro__texto">Esta acción da de baja tu cuenta de forma permanente. Perdés el acceso con tu correo actual y se cierra la sesión al confirmar.</p>
        <form
            method="post"
            action="/eliminar_cuenta.php"
            data-confirmar="Seguro que queres eliminar tu cuenta?"
            data-confirmar-aceptar="Si"
            data-confirmar-cancelar="No"
        >
            <button class="boton-terciario boton-terciario--rojo seguridad-zona-peligro__boton" type="submit">Eliminar cuenta</button>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>


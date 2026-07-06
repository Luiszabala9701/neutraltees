<?php

/**
 * Modulo: restablecimiento de contrasena.
 * Responsabilidad: validar codigo y permitir crear una contrasena nueva.
 */

require_once __DIR__ . '/config/conexion_DB.php';
require_once __DIR__ . '/config/funciones_mail.php';

$conexion = obtener_conexion_db();
$errores = [];
$pendiente = $_SESSION['recuperacion_contrasena_pendiente'] ?? null;
$usuarioEnSesion = usuario_actual();

if (!$pendiente || empty($pendiente['id_usuario'])) {
    guardar_flash('mensaje_error', 'Primero solicita un codigo para restablecer tu contrasena.');
    redirigir('/olvide_contrasena.php');
}

$idUsuario = (int) $pendiente['id_usuario'];
$mailUsuario = (string) ($pendiente['mail'] ?? '');
$nombreUsuario = (string) ($pendiente['nombre'] ?? 'Usuario');

if (es_post()) {
    $accion = limpiar_entrada((string) ($_POST['accion'] ?? 'validar_codigo'));

    if ($accion === 'reenviar_codigo') {
        $codigoNuevo = crear_codigo_usuario($conexion, $idUsuario, 'recuperacion_contrasena', 2);
        $_SESSION['recuperacion_contrasena_pendiente']['vence_en'] = time() + 120;
        unset($_SESSION['recuperacion_contrasena_pendiente']['codigo_validado'], $_SESSION['recuperacion_contrasena_pendiente']['id_token_validado']);

        if (enviar_mail_recuperacion_contrasena($mailUsuario, $nombreUsuario, $codigoNuevo)) {
            guardar_flash('mensaje_exito', 'Te enviamos un nuevo codigo. Vence en 2 minutos.');
        } else {
            $errores[] = 'No pudimos enviar el codigo. Intenta nuevamente.';
        }
    }

    if ($accion === 'validar_codigo') {
        $codigo = limpiar_entrada((string) ($_POST['codigo'] ?? ''));
        $datosCodigo = verificar_codigo_usuario($conexion, $idUsuario, 'recuperacion_contrasena', $codigo);

        if (!$datosCodigo) {
            $codigoNuevo = crear_codigo_usuario($conexion, $idUsuario, 'recuperacion_contrasena', 2);
            $_SESSION['recuperacion_contrasena_pendiente']['vence_en'] = time() + 120;
            unset($_SESSION['recuperacion_contrasena_pendiente']['codigo_validado'], $_SESSION['recuperacion_contrasena_pendiente']['id_token_validado']);

            if (enviar_mail_recuperacion_contrasena($mailUsuario, $nombreUsuario, $codigoNuevo)) {
                $errores[] = 'El codigo es incorrecto o ya vencio. Te enviamos uno nuevo a tu correo.';
            } else {
                $errores[] = 'El codigo es incorrecto o ya vencio. No pudimos reenviarlo, intenta nuevamente.';
            }
        } else {
            $_SESSION['recuperacion_contrasena_pendiente']['codigo_validado'] = true;
            $_SESSION['recuperacion_contrasena_pendiente']['id_token_validado'] = (int) $datosCodigo['id_token'];
            guardar_flash('mensaje_exito', 'Codigo confirmado. Ahora crea tu nueva contrasena.');
            redirigir('/restablecer_contrasena.php');
        }
    }

    if ($accion === 'actualizar_contrasena') {
        $codigoValidado = (bool) ($_SESSION['recuperacion_contrasena_pendiente']['codigo_validado'] ?? false);
        $idTokenValidado = (int) ($_SESSION['recuperacion_contrasena_pendiente']['id_token_validado'] ?? 0);
        $nuevaContrasena = (string) ($_POST['nueva_contrasena'] ?? '');
        $confirmarContrasena = (string) ($_POST['confirmar_contrasena'] ?? '');

        if (!$codigoValidado || $idTokenValidado <= 0) {
            $errores[] = 'Primero confirma el codigo que recibiste por correo.';
        }

        if ($nuevaContrasena !== $confirmarContrasena) {
            $errores[] = 'Las contraseñas no coinciden.';
        }

        foreach (validar_contrasena_segura($nuevaContrasena, 'La nueva contraseña') as $errorContrasena) {
            $errores[] = $errorContrasena;
        }

        if ($errores === []) {
            $conexion->beginTransaction();

            try {
                $sentencia = $conexion->prepare(
                    'UPDATE usuario
                     SET password = :password,
                         email_verificado = 1,
                         fecha_email_verificado = COALESCE(fecha_email_verificado, NOW()),
                         fecha_actualizacion = NOW()
                     WHERE id_usuario = :id_usuario'
                );
                $sentencia->execute([
                    ':password' => password_hash($nuevaContrasena, PASSWORD_DEFAULT),
                    ':id_usuario' => $idUsuario,
                ]);

                marcar_token_usado($conexion, $idTokenValidado);

                $conexion->commit();
                unset($_SESSION['recuperacion_contrasena_pendiente']);
                enviar_mail_contrasena_actualizada($mailUsuario, $nombreUsuario);

                guardar_flash('mensaje_exito', 'Tu contraseña fue actualizada.');

                if ($usuarioEnSesion && (bool) ($_SESSION['recuperacion_desde_admin'] ?? false)) {
                    unset($_SESSION['recuperacion_desde_admin']);
                    redirigir('/admin/seguridad.php');
                }

                if ($usuarioEnSesion) {
                    redirigir('/perfil.php');
                }

                redirigir('/login.php');
            } catch (Throwable $ex) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                registrar_error_sistema('Error al restablecer contraseña', $ex->getMessage());
                $errores[] = 'No se pudo actualizar la contraseña. Intentá nuevamente.';
            }
        }
    }
}

$codigoValidado = (bool) ($_SESSION['recuperacion_contrasena_pendiente']['codigo_validado'] ?? false);
$venceEn = (int) ($_SESSION['recuperacion_contrasena_pendiente']['vence_en'] ?? (time() + 120));
$segundosRestantes = max(0, $venceEn - time());

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario">
    <?php if ($codigoValidado): ?>
        <h1 class="tarjeta-formulario__titulo">Nueva contraseña</h1>
        <p class="tarjeta-formulario__texto">El codigo ya fue confirmado. Ahora crea una contraseña nueva para tu cuenta.</p>
    <?php else: ?>
        <h1 class="tarjeta-formulario__titulo">Confirmar código</h1>
        <p class="tarjeta-formulario__texto">Ingresa el codigo de 6 numeros que enviamos a <?php echo sanear_texto($mailUsuario); ?>.</p>
        <p class="contador-codigo" data-contador-codigo="<?php echo (string) $segundosRestantes; ?>">
            El codigo vence en <strong data-contador-codigo-texto>02:00</strong>.
        </p>
    <?php endif; ?>

    <?php if ($mensajeExito = obtener_flash('mensaje_exito')): ?>
        <div class="mensaje-vacio mensaje-vacio--exito u-mb-18"><?php echo sanear_texto($mensajeExito); ?></div>
    <?php endif; ?>

    <?php foreach ($errores as $error): ?>
        <div class="mensaje-vacio mensaje-vacio--error u-mb-18"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <?php if ($codigoValidado): ?>
        <form method="post">
            <input type="hidden" name="accion" value="actualizar_contrasena">

            <div class="grupo-campo grupo-contrasena">
                <label for="nueva_contrasena">Nueva contraseña</label>
                <div class="grupo-contrasena__control">
                    <input class="campo-texto" id="nueva_contrasena" type="password" name="nueva_contrasena" minlength="8" maxlength="16" required data-campo-contrasena data-objetivo-ayuda="#ayuda-nueva">
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
                    <input class="campo-texto" id="confirmar_contrasena" type="password" name="confirmar_contrasena" minlength="8" maxlength="16" required data-campo-contrasena data-objetivo-ayuda="#ayuda-confirmar" data-objetivo-comparar="#nueva_contrasena">
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

            <button class="boton-principal tarjeta-accion" type="submit">Actualizar contraseña</button>
        </form>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="accion" value="validar_codigo">

            <div class="grupo-campo">
                <label for="codigo">Codigo recibido</label>
                <input class="campo-texto" id="codigo" type="text" name="codigo" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required>
            </div>

            <button class="boton-principal tarjeta-accion" type="submit">Confirmar codigo</button>
        </form>

        <form method="post" class="u-mt-16">
            <input type="hidden" name="accion" value="reenviar_codigo">
            <button class="boton-secundario tarjeta-accion" type="submit">Reenviar codigo</button>
        </form>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>

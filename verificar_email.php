<?php

/**
 * Modulo: verificacion de correo.
 * Responsabilidad: validar el codigo enviado por mail y activar la cuenta.
 */

require_once __DIR__ . '/config/conexion_DB.php';
require_once __DIR__ . '/config/funciones_mail.php';

$conexion = obtener_conexion_db();
$errores = [];
$pendiente = $_SESSION['verificacion_email_pendiente'] ?? null;

if (!$pendiente || empty($pendiente['id_usuario'])) {
    guardar_flash('mensaje_error', 'No hay una verificacion de correo pendiente.');
    redirigir('/login.php');
}

$idUsuario = (int) $pendiente['id_usuario'];
$mailUsuario = (string) ($pendiente['mail'] ?? '');
$nombreUsuario = (string) ($pendiente['nombre'] ?? 'Usuario');

if (es_post()) {
    $accion = limpiar_entrada((string) ($_POST['accion'] ?? 'verificar_codigo'));

    if ($accion === 'reenviar_codigo') {
        $codigoNuevo = crear_codigo_usuario($conexion, $idUsuario, 'verificacion_email', 2);
        $_SESSION['verificacion_email_pendiente']['vence_en'] = time() + 120;

        if (enviar_mail_verificacion($mailUsuario, $nombreUsuario, $codigoNuevo)) {
            guardar_flash('mensaje_exito', 'Te enviamos un nuevo codigo. Vence en 2 minutos.');
        } else {
            $errores[] = 'No pudimos enviar el codigo. Intenta nuevamente.';
        }
    } else {
        $codigo = limpiar_entrada((string) ($_POST['codigo'] ?? ''));
        $datosCodigo = verificar_codigo_usuario($conexion, $idUsuario, 'verificacion_email', $codigo);

        if (!$datosCodigo) {
            $codigoNuevo = crear_codigo_usuario($conexion, $idUsuario, 'verificacion_email', 2);
            $_SESSION['verificacion_email_pendiente']['vence_en'] = time() + 120;

            if (enviar_mail_verificacion($mailUsuario, $nombreUsuario, $codigoNuevo)) {
                $errores[] = 'El codigo es incorrecto o ya vencio. Te enviamos uno nuevo a tu correo.';
            } else {
                $errores[] = 'El codigo es incorrecto o ya vencio. No pudimos reenviarlo, intenta nuevamente.';
            }
        }

        if ($errores === []) {
            $conexion->beginTransaction();

            try {
                $sentenciaUsuario = $conexion->prepare(
                    'UPDATE usuario
                     SET email_verificado = 1,
                         fecha_email_verificado = NOW(),
                         fecha_actualizacion = NOW()
                     WHERE id_usuario = :id_usuario'
                );
                $sentenciaUsuario->execute([':id_usuario' => $idUsuario]);

                marcar_token_usado($conexion, (int) $datosCodigo['id_token']);

                $conexion->commit();

                $sentenciaSesion = $conexion->prepare('SELECT * FROM usuario WHERE id_usuario = :id_usuario LIMIT 1');
                $sentenciaSesion->execute([':id_usuario' => $idUsuario]);
                $usuarioVerificado = $sentenciaSesion->fetch(PDO::FETCH_ASSOC);

                unset($_SESSION['verificacion_email_pendiente']);

                if ($usuarioVerificado) {
                    iniciar_sesion_usuario($conexion, $usuarioVerificado);
                }

                guardar_flash('mensaje_exito', 'Correo verificado correctamente. Iniciaste sesion.');

                if ($usuarioVerificado && (int) $usuarioVerificado['is_admin'] === 1) {
                    redirigir('/admin/index.php');
                }

                redirigir('/index.php');
            } catch (Throwable $ex) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                registrar_error_sistema('Error al verificar email', $ex->getMessage());
                $errores[] = 'No se pudo verificar el correo. Intenta nuevamente.';
            }
        }
    }
}

$venceEn = (int) ($_SESSION['verificacion_email_pendiente']['vence_en'] ?? (time() + 120));
$segundosRestantes = max(0, $venceEn - time());

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario">
    <h1 class="tarjeta-formulario__titulo">Verificar correo</h1>
    <p class="tarjeta-formulario__texto">Ingresa el codigo de 6 numeros que enviamos a <?php echo sanear_texto($mailUsuario); ?>.</p>
    <p class="contador-codigo" data-contador-codigo="<?php echo (string) $segundosRestantes; ?>">
        El codigo vence en <strong data-contador-codigo-texto>02:00</strong>.
    </p>

    <?php if ($mensajeExito = obtener_flash('mensaje_exito')): ?>
        <div class="mensaje-vacio mensaje-vacio--exito u-mb-18"><?php echo sanear_texto($mensajeExito); ?></div>
    <?php endif; ?>

    <?php foreach ($errores as $error): ?>
        <div class="mensaje-vacio mensaje-vacio--error u-mb-18"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <form method="post">
        <input type="hidden" name="accion" value="verificar_codigo">

        <div class="grupo-campo">
            <label for="codigo">Codigo de verificacion</label>
            <input class="campo-texto" id="codigo" type="text" name="codigo" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required>
        </div>

        <button class="boton-principal tarjeta-accion" type="submit">Verificar codigo</button>
    </form>

    <form method="post" class="u-mt-16">
        <input type="hidden" name="accion" value="reenviar_codigo">
        <button class="boton-secundario tarjeta-accion" type="submit">Reenviar codigo</button>
    </form>

    <p class="texto-acceso"><a class="enlace-suave" href="/login.php">Volver al inicio de sesion</a></p>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>

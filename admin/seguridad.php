<?php

/**
 * Modulo: seguridad del administrador.
 * Responsabilidad: permitir que un administrador cambie su contrasena desde el dashboard.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$usuario = usuario_actual();
$errores = [];

if (es_post()) {
    $accion = limpiar_entrada((string) ($_POST['accion'] ?? 'actualizar_contrasena'));

    if ($accion === 'enviar_codigo_recuperacion') {
        require_once __DIR__ . '/../config/funciones_mail.php';

        $nombreCompleto = trim((string) $usuario['nombre'] . ' ' . (string) $usuario['apellido']);
        $codigo = crear_codigo_usuario($conexion, (int) $usuario['id_usuario'], 'recuperacion_contrasena', 2);
        $_SESSION['recuperacion_contrasena_pendiente'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'mail' => (string) $usuario['mail'],
            'nombre' => $nombreCompleto,
            'vence_en' => time() + 120,
        ];
        $_SESSION['recuperacion_desde_admin'] = true;

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
            $errores[] = 'La contrasena actual no es correcta.';
        }

        if ($hashActual !== '' && password_verify($nuevaContrasena, $hashActual)) {
            $errores[] = 'La nueva contrasena debe ser diferente a la actual.';
        }

        if ($nuevaContrasena !== $confirmarContrasena) {
            $errores[] = 'Las contrasenas nuevas no coinciden.';
        }

        foreach (validar_contrasena_segura($nuevaContrasena, 'La nueva contrasena') as $errorContrasena) {
            $errores[] = $errorContrasena;
        }

        if ($errores === []) {
            $sentenciaActualizar = $conexion->prepare('UPDATE usuario SET password = :password, fecha_actualizacion = NOW() WHERE id_usuario = :id_usuario');
            $sentenciaActualizar->execute([
                ':password' => password_hash($nuevaContrasena, PASSWORD_DEFAULT),
                ':id_usuario' => (int) $usuario['id_usuario'],
            ]);

            guardar_flash('mensaje_exito', 'Tu contrasena fue actualizada.');
            redirigir('/admin/seguridad.php');
        }
    }
}

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Seguridad</h1>
    <p>Actualiza tu contrasena de acceso al panel.</p>
</header>

<section class="panel-seccion">
    <div class="tarjeta-resumen tarjeta-resumen--detalle-pedido">
        <?php foreach ($errores as $error): ?>
            <div class="contenido-vacio-admin mensaje-vacio--error u-mb-18 u-text-left"><?php echo sanear_texto($error); ?></div>
        <?php endforeach; ?>

        <form class="formulario-stock" method="post">
            <div class="grupo-campo grupo-contrasena">
                <label for="contrasena_actual">Contrasena actual</label>
                <div class="grupo-contrasena__control">
                    <input class="campo-texto" id="contrasena_actual" type="password" name="contrasena_actual" required>
                    <button class="boton-ojito" type="button" data-boton-contrasena="#contrasena_actual" title="Mostrar contrasena" aria-label="Mostrar contrasena" aria-pressed="false">
                        <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="grupo-campo grupo-contrasena">
                <label for="nueva_contrasena">Nueva contrasena</label>
                <div class="grupo-contrasena__control">
                    <input class="campo-texto" id="nueva_contrasena" type="password" name="nueva_contrasena" required data-campo-contrasena data-objetivo-ayuda="#ayuda-nueva-admin">
                    <button class="boton-ojito" type="button" data-boton-contrasena="#nueva_contrasena" title="Mostrar contrasena" aria-label="Mostrar contrasena" aria-pressed="false">
                        <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
                        </svg>
                    </button>
                </div>
                <div id="ayuda-nueva-admin" class="ayuda-contrasena" data-panel-ayuda-contrasena hidden>
                    <ul class="lista-validacion lista-validacion--dinamica" data-lista-validacion-contrasena>
                        <li data-regla-contrasena="longitud">Minimo 8 caracteres y maximo 16.</li>
                        <li data-regla-contrasena="numero">Al menos 1 numero.</li>
                        <li data-regla-contrasena="letra">Al menos 1 letra.</li>
                        <li data-regla-contrasena="especial">Al menos 1 caracter especial.</li>
                    </ul>
                </div>
            </div>

            <div class="grupo-campo grupo-contrasena">
                <label for="confirmar_contrasena">Confirmar contrasena</label>
                <div class="grupo-contrasena__control">
                    <input class="campo-texto" id="confirmar_contrasena" type="password" name="confirmar_contrasena" required data-campo-contrasena data-objetivo-ayuda="#ayuda-confirmar-admin" data-objetivo-comparar="#nueva_contrasena">
                    <button class="boton-ojito" type="button" data-boton-contrasena="#confirmar_contrasena" title="Mostrar contrasena" aria-label="Mostrar contrasena" aria-pressed="false">
                        <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
                        </svg>
                    </button>
                </div>
                <div id="ayuda-confirmar-admin" class="ayuda-contrasena" data-panel-ayuda-contrasena hidden>
                    <ul class="lista-validacion lista-validacion--dinamica" data-lista-validacion-contrasena>
                        <li data-regla-contrasena="longitud">Minimo 8 caracteres y maximo 16.</li>
                        <li data-regla-contrasena="numero">Al menos 1 numero.</li>
                        <li data-regla-contrasena="letra">Al menos 1 letra.</li>
                        <li data-regla-contrasena="especial">Al menos 1 caracter especial.</li>
                        <li data-regla-contrasena="coincidencia">Coincidir contrasena.</li>
                    </ul>
                </div>
            </div>

            <input type="hidden" name="accion" value="actualizar_contrasena">
            <button class="boton-principal" type="submit">Actualizar contrasena</button>
        </form>

        <form method="post" class="texto-acceso">
            <input type="hidden" name="accion" value="enviar_codigo_recuperacion">
            <span>No recordas tu contrasena actual?</span>
            <button class="enlace-suave enlace-boton" type="submit">recuperar por mail</button>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

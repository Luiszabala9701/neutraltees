<?php

/**
 * Modulo: registro de clientes.
 * Responsabilidad: crear usuario, guardar contrasena hasheada y enviar codigo.
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
$valores = [
    'nombre' => '',
    'apellido' => '',
    'telefono' => '',
    'correo' => '',
];

if (es_post()) {
    $valores['nombre'] = trim(strip_tags((string) ($_POST['nombre'] ?? '')));
    $valores['apellido'] = trim(strip_tags((string) ($_POST['apellido'] ?? '')));
    $valores['telefono'] = trim(strip_tags((string) ($_POST['telefono'] ?? '')));
    $valores['correo'] = trim(strip_tags((string) ($_POST['correo'] ?? '')));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $confirmarContrasena = (string) ($_POST['confirmar_contrasena'] ?? '');
    $aceptaTerminos = isset($_POST['acepta_terminos']);
    $aceptaPrivacidad = isset($_POST['acepta_privacidad']);

    if ($valores['nombre'] === '' || $valores['apellido'] === '' || $valores['correo'] === '' || $contrasena === '' || $confirmarContrasena === '') {
        $errores[] = 'Completá todos los campos obligatorios.';
    }

    if (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresá un correo electrónico válido.';
    }

    if ($valores['telefono'] !== '' && !ctype_digit($valores['telefono'])) {
        $errores[] = 'El telefono debe tener solo numeros.';
    }

    if ($contrasena !== $confirmarContrasena) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    foreach (validar_contrasena_segura($contrasena) as $errorContrasena) {
        $errores[] = $errorContrasena;
    }

    if (!$aceptaTerminos || !$aceptaPrivacidad) {
        $errores[] = 'Debés aceptar los términos y la política de privacidad.';
    }

    if (obtener_usuario_por_correo($conexion, $valores['correo'])) {
        $errores[] = 'Ya existe una cuenta con ese correo.';
    }

    if ($errores === []) {
        $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
        $sentencia = $conexion->prepare(
            'INSERT INTO usuario
             (nombre, apellido, telefono, mail, email_verificado, fecha_email_verificado, password, is_admin, fecha_creacion, fecha_actualizacion, activo, acepta_terminos, fecha_aceptacion_terminos)
             VALUES
             (:nombre, :apellido, :telefono, :mail, 0, NULL, :password, 0, NOW(), NOW(), 1, 1, NOW())'
        );

        $sentencia->execute([
            ':nombre' => $valores['nombre'],
            ':apellido' => $valores['apellido'],
            ':telefono' => $valores['telefono'] !== '' ? $valores['telefono'] : null,
            ':mail' => $valores['correo'],
            ':password' => $contrasenaHash,
        ]);

        $idUsuarioCreado = (int) $conexion->lastInsertId();
        $codigoVerificacion = crear_codigo_usuario($conexion, $idUsuarioCreado, 'verificacion_email', 2);
        $nombreCompleto = trim($valores['nombre'] . ' ' . $valores['apellido']);
        $_SESSION['verificacion_email_pendiente'] = [
            'id_usuario' => $idUsuarioCreado,
            'mail' => $valores['correo'],
            'nombre' => $nombreCompleto,
            'vence_en' => time() + 120,
        ];

        if (!enviar_mail_verificacion($valores['correo'], $nombreCompleto, $codigoVerificacion)) {
            guardar_flash('mensaje_error', 'Tu cuenta fue creada, pero no pudimos enviar el codigo de verificacion. Intenta iniciar sesion para reenviarlo.');
            redirigir('/login.php');
        }

        guardar_flash('mensaje_exito', 'Registro exitoso. Te enviamos un codigo de 6 numeros que vence en 2 minutos.');
        redirigir('/verificar_email.php');

    }
}

require_once __DIR__ . '/includes/cabecera_publica.php';
?>

<section class="tarjeta-formulario">
    <h1 class="tarjeta-formulario__titulo">Crear cuenta</h1>
    <p class="tarjeta-formulario__texto">Las cuentas nuevas se crean como usuarios normales.</p>

    <?php foreach ($errores as $error): ?>
        <div class="mensaje-vacio mensaje-vacio--error mensaje-vacio--registro"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <form method="post">
        <div class="rejilla-campos">
            <div class="grupo-campo">
                <label for="nombre">Nombre</label>
                <input class="campo-texto" id="nombre" type="text" name="nombre" value="<?php echo $valores['nombre']; ?>" required>
            </div>
            <div class="grupo-campo">
                <label for="apellido">Apellido</label>
                <input class="campo-texto" id="apellido" type="text" name="apellido" value="<?php echo $valores['apellido']; ?>" required>
            </div>
        </div>

        <div class="grupo-campo">
            <label for="telefono">Teléfono</label>
            <input class="campo-texto" id="telefono" type="tel" name="telefono" value="<?php echo $valores['telefono']; ?>" inputmode="numeric" pattern="[0-9]*" maxlength="15" autocomplete="tel" data-solo-numeros>
        </div>

        <div class="grupo-campo">
            <label for="correo">Correo electrónico</label>
            <input class="campo-texto" id="correo" type="email" name="correo" value="<?php echo $valores['correo']; ?>" required>
        </div>

        <div class="grupo-campo grupo-contrasena">
            <div class="grupo-campo__encabezado">
                <label for="contrasena">Contraseña</label>
            </div>
            <div class="grupo-contrasena__control">
                <input class="campo-texto" id="contrasena" type="password" name="contrasena" minlength="8" maxlength="16" required data-campo-contrasena data-objetivo-ayuda="#ayuda-contrasena">
                <button class="boton-ojito" type="button" data-boton-contrasena="#contrasena" title="Mostrar contraseña" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
                    </svg>
                </button>
            </div>
            <div id="ayuda-contrasena" class="ayuda-contrasena" data-panel-ayuda-contrasena hidden>
                <ul class="lista-validacion lista-validacion--dinamica" data-lista-validacion-contrasena>
                    <li data-regla-contrasena="longitud">Mínimo 8 caracteres y máximo 16.</li>
                    <li data-regla-contrasena="numero">Al menos 1 número.</li>
                    <li data-regla-contrasena="letra">Al menos 1 letra.</li>
                    <li data-regla-contrasena="especial">Al menos 1 carácter especial.</li>
                </ul>
            </div>
        </div>

        <div class="grupo-campo grupo-contrasena">
            <div class="grupo-campo__encabezado">
                <label for="confirmar_contrasena">Confirmar contraseña</label>
            </div>
            <div class="grupo-contrasena__control">
                <input class="campo-texto" id="confirmar_contrasena" type="password" name="confirmar_contrasena" minlength="8" maxlength="16" required data-campo-contrasena data-objetivo-ayuda="#ayuda-confirmar-contrasena" data-objetivo-comparar="#contrasena">
                <button class="boton-ojito" type="button" data-boton-contrasena="#confirmar_contrasena" title="Mostrar contraseña" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
                    </svg>
                </button>
            </div>
            <div id="ayuda-confirmar-contrasena" class="ayuda-contrasena" data-panel-ayuda-contrasena hidden>
                <ul class="lista-validacion lista-validacion--dinamica" data-lista-validacion-contrasena>
                    <li data-regla-contrasena="longitud">Mínimo 8 caracteres y máximo 16.</li>
                    <li data-regla-contrasena="numero">Al menos 1 número.</li>
                    <li data-regla-contrasena="letra">Al menos 1 letra.</li>
                    <li data-regla-contrasena="especial">Al menos 1 carácter especial.</li>
                    <li data-regla-contrasena="coincidencia">Coincidir contraseña.</li>
                </ul>
            </div>
        </div>

        <div class="casilla-aceptacion">
            <label>
                <input type="checkbox" name="acepta_terminos" required>
                <span>Acepto los</span>
            </label>
            <button class="boton-documento-legal" type="button" data-abrir-documento-legal data-documento-url="/src/Terminos-condiciones-NeutralTees.pdf" data-documento-titulo="Términos y condiciones">términos y condiciones</button>
        </div>
        <div class="casilla-aceptacion">
            <label>
                <input type="checkbox" name="acepta_privacidad" required>
                <span>Acepto la</span>
            </label>
            <button class="boton-documento-legal" type="button" data-abrir-documento-legal data-documento-url="/src/Politicas_privacidad-NeutralTees.pdf" data-documento-titulo="Política de privacidad">política de privacidad</button>
        </div>

        <button class="boton-principal tarjeta-accion" type="submit">Registrarme</button>
    </form>

    <p class="texto-acceso">¿Ya tenés cuenta? <a class="enlace-suave" href="/login.php">Iniciá sesión.</a></p>
</section>

<?php require_once __DIR__ . '/includes/pie_publico.php'; ?>

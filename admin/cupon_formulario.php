<?php

/**
 * Modulo: formulario de cupon.
 * Responsabilidad: crear o modificar reglas de descuento.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
$conexion = obtener_conexion_db();
requiere_admin();

$idCupon = (int) ($_GET['id'] ?? $_POST['id_cupon'] ?? 0);
$esEdicion = $idCupon > 0;
$errores = [];

if ($esEdicion) {
    guardar_flash('mensaje_error', 'Los cupones no se modifican. Podés darlo de baja y crear uno nuevo.');
    redirigir('/admin/cupones.php');
}

// Valores iniciales del formulario. Si estamos editando, se reemplazan con la base de datos.
$datos = [
    'codigo' => '',
    'descripcion' => '',
    'tipo_descuento' => 'porcentaje',
    'valor' => '',
    'compra_minima' => '0',
    'tope_descuento' => '',
    'max_usos_total' => '',
    'fecha_inicio' => '',
    'fecha_fin' => '',
];

if ($esEdicion && !es_post()) {
    $sentenciaCupon = $conexion->prepare('SELECT * FROM cupon WHERE id_cupon = :id_cupon LIMIT 1');
    $sentenciaCupon->execute([':id_cupon' => $idCupon]);
    $cuponExistente = $sentenciaCupon->fetch(PDO::FETCH_ASSOC);

    if (!$cuponExistente) {
        guardar_flash('mensaje_error', 'El cupón solicitado no existe.');
        redirigir('/admin/cupones.php');
    }

    $datos = array_merge($datos, [
        'codigo' => strtoupper((string) $cuponExistente['codigo']),
        'descripcion' => (string) ($cuponExistente['descripcion'] ?? ''),
        'tipo_descuento' => (string) $cuponExistente['tipo_descuento'],
        'valor' => (string) $cuponExistente['valor'],
        'compra_minima' => (string) $cuponExistente['compra_minima'],
        'tope_descuento' => $cuponExistente['tope_descuento'] !== null ? (string) $cuponExistente['tope_descuento'] : '',
        'max_usos_total' => $cuponExistente['max_usos_total'] !== null ? (string) $cuponExistente['max_usos_total'] : '',
        'fecha_inicio' => (string) ($cuponExistente['fecha_inicio'] ?? ''),
        'fecha_fin' => (string) ($cuponExistente['fecha_fin'] ?? ''),
    ]);
}

if (es_post()) {
    $datos = [
        'codigo' => strtoupper(limpiar_entrada((string) ($_POST['codigo'] ?? ''))),
        'descripcion' => limpiar_entrada((string) ($_POST['descripcion'] ?? '')),
        'tipo_descuento' => limpiar_entrada((string) ($_POST['tipo_descuento'] ?? '')),
        'valor' => limpiar_entrada((string) ($_POST['valor'] ?? '')),
        'compra_minima' => limpiar_entrada((string) ($_POST['compra_minima'] ?? '')),
        'tope_descuento' => limpiar_entrada((string) ($_POST['tope_descuento'] ?? '')),
        'max_usos_total' => limpiar_entrada((string) ($_POST['max_usos_total'] ?? '')),
        'fecha_inicio' => limpiar_entrada((string) ($_POST['fecha_inicio'] ?? '')),
        'fecha_fin' => limpiar_entrada((string) ($_POST['fecha_fin'] ?? '')),
    ];

    $valor = (float) $datos['valor'];
    $compraMinima = (float) $datos['compra_minima'];
    $topeDescuento = $datos['tope_descuento'] !== '' ? (float) $datos['tope_descuento'] : null;
    $maxUsosTotal = $datos['max_usos_total'] !== '' ? (int) $datos['max_usos_total'] : null;
    $fechaInicio = $datos['fecha_inicio'] !== '' ? $datos['fecha_inicio'] : null;
    $fechaFin = $datos['fecha_fin'] !== '' ? $datos['fecha_fin'] : null;

    if ($datos['codigo'] === '') {
        $errores[] = 'Completá el código del cupón.';
    } elseif (!preg_match('/^[A-Z0-9_-]{3,50}$/', $datos['codigo'])) {
        $errores[] = 'El código debe tener entre 3 y 50 caracteres. Usá letras, números, guion o guion bajo.';
    }

    if ($esEdicion) {
        $sentenciaExiste = $conexion->prepare('SELECT id_cupon FROM cupon WHERE id_cupon = :id_cupon LIMIT 1');
        $sentenciaExiste->execute([':id_cupon' => $idCupon]);

        if (!$sentenciaExiste->fetchColumn()) {
            $errores[] = 'El cupón que querés modificar no existe.';
        }
    }

    if (!in_array($datos['tipo_descuento'], ['porcentaje', 'monto_fijo'], true)) {
        $errores[] = 'Seleccioná un tipo de descuento válido.';
    }

    if ($valor <= 0) {
        $errores[] = 'El valor del descuento debe ser mayor a cero.';
    }

    if ($datos['tipo_descuento'] === 'porcentaje' && $valor > 100) {
        $errores[] = 'El porcentaje no puede superar el 100%.';
    }

    if ($datos['compra_minima'] === '') {
        $errores[] = 'Completa la compra minima del cupon.';
    }

    if ($datos['compra_minima'] !== '' && $compraMinima < 0) {
        $errores[] = 'La compra mínima no puede ser negativa.';
    }

    if ($datos['tipo_descuento'] === 'monto_fijo' && $compraMinima <= $valor) {
        $errores[] = 'En cupones de monto fijo, la compra minima debe ser mayor al valor del descuento.';
    }

    if ($topeDescuento !== null && $topeDescuento <= 0) {
        $errores[] = 'El tope de descuento debe ser mayor a cero o quedar vacío.';
    }

    if ($maxUsosTotal !== null && $maxUsosTotal <= 0) {
        $errores[] = 'El máximo de usos debe ser mayor a cero o quedar vacío.';
    }

    if ($fechaInicio !== null && $fechaFin !== null && $fechaFin <= $fechaInicio) {
        $errores[] = 'La fecha de fin debe ser mayor a la fecha de inicio.';
    }

    $sentenciaCodigo = $conexion->prepare(
        'SELECT id_cupon
         FROM cupon
         WHERE codigo = :codigo
           AND id_cupon <> :id_cupon
           AND activo = 1
           AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
         LIMIT 1'
    );
    $sentenciaCodigo->execute([
        ':codigo' => $datos['codigo'],
        ':id_cupon' => $idCupon,
    ]);

    if ($sentenciaCodigo->fetchColumn()) {
        $errores[] = 'Ya existe un cupon activo con ese codigo.';
    }

    if ($errores === []) {
        if ($esEdicion) {
            $sentenciaGuardar = $conexion->prepare(
                'UPDATE cupon
                 SET codigo = :codigo,
                     descripcion = :descripcion,
                     tipo_descuento = :tipo_descuento,
                     valor = :valor,
                     compra_minima = :compra_minima,
                     tope_descuento = :tope_descuento,
                     max_usos_total = :max_usos_total,
                     fecha_inicio = :fecha_inicio,
                     fecha_fin = :fecha_fin
                 WHERE id_cupon = :id_cupon'
            );
            $sentenciaGuardar->execute([
                ':codigo' => $datos['codigo'],
                ':descripcion' => $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
                ':tipo_descuento' => $datos['tipo_descuento'],
                ':valor' => $valor,
                ':compra_minima' => $compraMinima,
                ':tope_descuento' => $topeDescuento,
                ':max_usos_total' => $maxUsosTotal,
                ':fecha_inicio' => $fechaInicio,
                ':fecha_fin' => $fechaFin,
                ':id_cupon' => $idCupon,
            ]);

            guardar_flash('mensaje_exito', 'Cupón actualizado.');
        } else {
            $sentenciaGuardar = $conexion->prepare(
                'INSERT INTO cupon
                    (codigo, descripcion, tipo_descuento, valor, compra_minima, tope_descuento, max_usos_total, activo, fecha_inicio, fecha_fin, fecha_creacion)
                 VALUES
                    (:codigo, :descripcion, :tipo_descuento, :valor, :compra_minima, :tope_descuento, :max_usos_total, 1, :fecha_inicio, :fecha_fin, NOW())'
            );
            $sentenciaGuardar->execute([
                ':codigo' => $datos['codigo'],
                ':descripcion' => $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
                ':tipo_descuento' => $datos['tipo_descuento'],
                ':valor' => $valor,
                ':compra_minima' => $compraMinima,
                ':tope_descuento' => $topeDescuento,
                ':max_usos_total' => $maxUsosTotal,
                ':fecha_inicio' => $fechaInicio,
                ':fecha_fin' => $fechaFin,
            ]);

            guardar_flash('mensaje_exito', 'Cupón creado.');
        }

        redirigir('/admin/cupones.php');
    }
}

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Crear cupón</h1>
    <p>Crear descuentos para usar en la tienda</p>
</header>

<section class="panel-seccion">
    <?php foreach ($errores as $error): ?>
        <div class="contenido-vacio-admin mensaje-vacio--error u-mb-18 u-text-left"><?php echo sanear_texto($error); ?></div>
    <?php endforeach; ?>

    <div class="tarjeta-resumen tarjeta-resumen--detalle-pedido">
        <form class="formulario-stock" method="post">
            <div class="rejilla-campos">
                <div class="grupo-campo">
                    <label for="codigo">Código</label>
                    <input class="campo-texto campo-texto--mayusculas" id="codigo" type="text" name="codigo" maxlength="50" required value="<?php echo sanear_texto(strtoupper($datos['codigo'])); ?>" placeholder="Ej: BIENVENIDA10" data-mayusculas>
                </div>

                <div class="grupo-campo">
                    <label for="tipo_descuento">Tipo de descuento</label>
                    <select class="campo-select" id="tipo_descuento" name="tipo_descuento" required>
                        <option value="porcentaje" <?php echo $datos['tipo_descuento'] === 'porcentaje' ? 'selected' : ''; ?>>Porcentaje</option>
                        <option value="monto_fijo" <?php echo $datos['tipo_descuento'] === 'monto_fijo' ? 'selected' : ''; ?>>Monto fijo</option>
                    </select>
                </div>
            </div>

            <div class="grupo-campo">
                <label for="descripcion">Descripción</label>
                <input class="campo-texto" id="descripcion" type="text" name="descripcion" maxlength="255" value="<?php echo sanear_texto($datos['descripcion']); ?>" placeholder="Ej: Cupón para primera compra">
            </div>

            <div class="rejilla-campos">
                <div class="grupo-campo">
                    <label for="valor">Valor</label>
                    <input class="campo-texto" id="valor" type="number" name="valor" step="0.01" min="0.01" required value="<?php echo sanear_texto($datos['valor']); ?>">
                </div>

                <div class="grupo-campo">
                    <label for="compra_minima">Compra mínima</label>
                    <input class="campo-texto" id="compra_minima" type="number" name="compra_minima" step="0.01" min="0" required value="<?php echo sanear_texto($datos['compra_minima']); ?>">
                </div>
            </div>

            <div class="rejilla-campos">
                <div class="grupo-campo">
                    <label for="tope_descuento">Tope de descuento</label>
                    <input class="campo-texto" id="tope_descuento" type="number" name="tope_descuento" step="0.01" min="0" value="<?php echo sanear_texto($datos['tope_descuento']); ?>" placeholder="Opcional">
                </div>

                <div class="grupo-campo">
                    <label for="max_usos_total">Máximo de usos</label>
                    <input class="campo-texto" id="max_usos_total" type="number" name="max_usos_total" min="1" value="<?php echo sanear_texto($datos['max_usos_total']); ?>" placeholder="Opcional">
                </div>
            </div>

            <div class="rejilla-campos">
                <div class="grupo-campo">
                    <label for="fecha_inicio">Fecha de inicio</label>
                    <input class="campo-texto" id="fecha_inicio" type="date" name="fecha_inicio" value="<?php echo sanear_texto($datos['fecha_inicio']); ?>">
                </div>

                <div class="grupo-campo">
                    <label for="fecha_fin">Fecha de fin</label>
                    <input class="campo-texto" id="fecha_fin" type="date" name="fecha_fin" value="<?php echo sanear_texto($datos['fecha_fin']); ?>">
                </div>
            </div>

            <div class="acciones-fila acciones-fila--arriba">
                <button class="boton-principal" type="submit">Crear cupón</button>
                <a class="boton-secundario boton-secundario--gris" href="/admin/cupones.php">Cancelar</a>
            </div>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

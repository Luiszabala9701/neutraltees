<?php

/**
 * Modulo: usuarios admin.
 * Responsabilidad: buscar, filtrar y listar usuarios/clientes del sistema.
 */

require_once __DIR__ . '/../config/conexion_DB.php';
require_once __DIR__ . '/../config/funciones_mail.php';
$conexion = obtener_conexion_db();
requiere_admin();

$itemsPorPagina = 15;
$paginaActual = max(1, (int) ($_POST['pagina'] ?? $_GET['pagina'] ?? 1));
$busqueda = trim(limpiar_entrada((string) ($_POST['q'] ?? $_GET['q'] ?? '')));
$filtroRol = limpiar_entrada((string) ($_POST['filtro_rol'] ?? $_GET['rol'] ?? ''));
$filtroEstado = limpiar_entrada((string) ($_POST['filtro_estado'] ?? $_GET['estado'] ?? ''));

if (!in_array($filtroRol, ['admin', 'cliente'], true)) {
    $filtroRol = '';
}

if (!in_array($filtroEstado, ['activo', 'inactivo'], true)) {
    $filtroEstado = '';
}

function construir_ruta_usuarios(string $busqueda = '', string $rol = '', string $estado = '', int $pagina = 1): string
{
    $parametros = [];

    if ($busqueda !== '') {
        $parametros['q'] = $busqueda;
    }

    if ($rol !== '') {
        $parametros['rol'] = $rol;
    }

    if ($estado !== '') {
        $parametros['estado'] = $estado;
    }

    if ($pagina > 1) {
        $parametros['pagina'] = $pagina;
    }

    return '/admin/usuarios.php' . ($parametros === [] ? '' : '?' . http_build_query($parametros));
}

if (es_post()) {
    $accion = trim(strip_tags((string) ($_POST['accion'] ?? '')));
    $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
    $rutaRetorno = construir_ruta_usuarios($busqueda, $filtroRol, $filtroEstado, $paginaActual);

    if ($accion === 'cambiar_estado_usuario' && $idUsuario > 0) {
        $sentenciaUsuario = $conexion->prepare('SELECT id_usuario, nombre, apellido, mail, is_admin, activo FROM usuario WHERE id_usuario = :id_usuario LIMIT 1');
        $sentenciaUsuario->execute([':id_usuario' => $idUsuario]);
        $usuarioObjetivo = $sentenciaUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioObjetivo) {
            guardar_flash('mensaje_error', 'El usuario no existe.');
            redirigir($rutaRetorno);
        }

        if ((int) $usuarioObjetivo['is_admin'] === 1) {
            guardar_flash('mensaje_error', 'No podes dar de baja a un administrador.');
            redirigir($rutaRetorno);
        }

        $nuevoEstado = (int) $usuarioObjetivo['activo'] === 1 ? 0 : 1;

        if ($nuevoEstado === 0 && usuario_tiene_pedidos_activos($conexion, $idUsuario)) {
            guardar_flash('mensaje_error', 'No podes dar de baja este usuario porque tiene pedidos activos.');
            redirigir($rutaRetorno);
        }

        if ($nuevoEstado === 0) {
            dar_baja_cuenta_usuario($conexion, $idUsuario);

            $nombreUsuarioObjetivo = trim((string) $usuarioObjetivo['nombre'] . ' ' . (string) $usuarioObjetivo['apellido']);
            $nombreUsuarioObjetivo = $nombreUsuarioObjetivo !== '' ? $nombreUsuarioObjetivo : 'Usuario';
            enviar_mail_cuenta_dada_baja((string) $usuarioObjetivo['mail'], $nombreUsuarioObjetivo);
        } else {
            $sentencia = $conexion->prepare('UPDATE usuario SET activo = :activo, fecha_actualizacion = NOW() WHERE id_usuario = :id_usuario');
            $sentencia->execute([
                ':activo' => $nuevoEstado,
                ':id_usuario' => $idUsuario,
            ]);

            $nombreUsuarioObjetivo = trim((string) $usuarioObjetivo['nombre'] . ' ' . (string) $usuarioObjetivo['apellido']);
            $nombreUsuarioObjetivo = $nombreUsuarioObjetivo !== '' ? $nombreUsuarioObjetivo : 'Usuario';
            enviar_mail_cuenta_reactivada((string) $usuarioObjetivo['mail'], $nombreUsuarioObjetivo);
        }

        guardar_flash('mensaje_exito', $nuevoEstado === 0 ? 'Usuario dado de baja.' : 'Usuario reactivado.');
        redirigir($rutaRetorno);
    }
}

$condiciones = [];
$parametros = [];

if ($busqueda !== '') {
    $condiciones[] = "(CONCAT(nombre, ' ', apellido) LIKE :busqueda_completa OR nombre LIKE :busqueda_nombre OR apellido LIKE :busqueda_apellido OR mail LIKE :busqueda_mail)";
    $valorBusqueda = '%' . $busqueda . '%';
    $parametros[':busqueda_completa'] = $valorBusqueda;
    $parametros[':busqueda_nombre'] = $valorBusqueda;
    $parametros[':busqueda_apellido'] = $valorBusqueda;
    $parametros[':busqueda_mail'] = $valorBusqueda;
}

if ($filtroRol !== '') {
    $condiciones[] = 'is_admin = :filtro_rol';
    $parametros[':filtro_rol'] = $filtroRol === 'admin' ? 1 : 0;
}

if ($filtroEstado !== '') {
    $condiciones[] = 'activo = :filtro_estado';
    $parametros[':filtro_estado'] = $filtroEstado === 'activo' ? 1 : 0;
}

$whereUsuarios = $condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones);

$sentenciaTotalUsuarios = $conexion->prepare('SELECT COUNT(*) FROM usuario' . $whereUsuarios);
$sentenciaTotalUsuarios->execute($parametros);
$totalUsuariosFiltrados = (int) $sentenciaTotalUsuarios->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalUsuariosFiltrados / $itemsPorPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$offsetUsuarios = ($paginaActual - 1) * $itemsPorPagina;

$sentenciaUsuarios = $conexion->prepare('SELECT * FROM usuario' . $whereUsuarios . ' ORDER BY fecha_creacion DESC LIMIT :limite OFFSET :offset');
foreach ($parametros as $clave => $valor) {
    $sentenciaUsuarios->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$sentenciaUsuarios->bindValue(':limite', $itemsPorPagina, PDO::PARAM_INT);
$sentenciaUsuarios->bindValue(':offset', $offsetUsuarios, PDO::PARAM_INT);
$sentenciaUsuarios->execute();
$usuarios = $sentenciaUsuarios->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/../includes/cabecera_admin.php';
?>

<header class="encabezado-panel">
    <h1>Usuarios registrados</h1>
    <p>Listado de cuentas creadas en la tienda</p>
</header>

<section class="panel-seccion">
    <div class="pedidos-panel usuarios-panel-filtros">
        <div class="pedidos-panel__barra">
            <form class="pedidos-buscador" method="get" action="/admin/usuarios.php">
                <label class="sr-only" for="busqueda_usuario">Buscar usuario</label>
                <input
                    class="campo-texto pedidos-buscador__entrada"
                    id="busqueda_usuario"
                    type="search"
                    name="q"
                    value="<?php echo sanear_texto($busqueda); ?>"
                    placeholder="Buscar por nombre o email"
                >

                <label class="sr-only" for="filtro_rol_usuario">Filtrar por rol</label>
                <select class="campo-select pedidos-buscador__filtro" id="filtro_rol_usuario" name="rol">
                    <option value="">Todos los roles</option>
                    <option value="admin" <?php echo $filtroRol === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                    <option value="cliente" <?php echo $filtroRol === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                </select>

                <label class="sr-only" for="filtro_estado_usuario">Filtrar por estado</label>
                <select class="campo-select pedidos-buscador__filtro" id="filtro_estado_usuario" name="estado">
                    <option value="">Todos los estados</option>
                    <option value="activo" <?php echo $filtroEstado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                    <option value="inactivo" <?php echo $filtroEstado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                </select>

                <button class="boton-secundario boton-secundario--gris" type="submit">Buscar</button>
                <?php if ($busqueda !== '' || $filtroRol !== '' || $filtroEstado !== ''): ?>
                    <a class="boton-secundario boton-secundario--gris" href="/admin/usuarios.php">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($usuarios === []): ?>
        <div class="contenido-vacio-admin">No hay usuarios para esos filtros.</div>
    <?php else: ?>
        <div class="lista-admin">
            <?php foreach ($usuarios as $usuario): ?>
                <article class="fila-usuario">
                    <div class="fila-usuario__encabezado">
                        <div>
                            <h3 class="usuario__nombre"><?php echo sanear_texto($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h3>
                            <p class="usuario__mail"><?php echo sanear_texto($usuario['mail']); ?></p>
                        </div>
                        <div class="u-text-right">
                            <span class="etiqueta <?php echo (int) $usuario['is_admin'] === 1 ? 'etiqueta--amarillo' : 'etiqueta--verde'; ?>"><?php echo (int) $usuario['is_admin'] === 1 ? 'Administrador' : 'Cliente'; ?></span>
                            <div class="usuario__estado"><?php echo (int) $usuario['activo'] === 1 ? 'Activo' : 'Inactivo'; ?></div>
                            <?php if ((int) $usuario['is_admin'] !== 1): ?>
                                <div class="fila-usuario__acciones u-mt-12">
                                    <form method="post" data-confirmar="Queres cambiar el estado de este usuario?">
                                        <input type="hidden" name="accion" value="cambiar_estado_usuario">
                                        <input type="hidden" name="id_usuario" value="<?php echo (int) $usuario['id_usuario']; ?>">
                                        <input type="hidden" name="q" value="<?php echo sanear_texto($busqueda); ?>">
                                        <input type="hidden" name="filtro_rol" value="<?php echo sanear_texto($filtroRol); ?>">
                                        <input type="hidden" name="filtro_estado" value="<?php echo sanear_texto($filtroEstado); ?>">
                                        <input type="hidden" name="pagina" value="<?php echo $paginaActual; ?>">
                                        <button class="boton-terciario" type="submit"><?php echo (int) $usuario['activo'] === 1 ? 'Dar de baja' : 'Reactivar'; ?></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <nav class="paginacion-admin" aria-label="Paginacion de usuarios">
                <?php for ($pagina = 1; $pagina <= $totalPaginas; $pagina++): ?>
                    <a class="paginacion-admin__enlace <?php echo $pagina === $paginaActual ? 'is-activo' : ''; ?>" href="<?php echo construir_ruta_usuarios($busqueda, $filtroRol, $filtroEstado, $pagina); ?>"><?php echo $pagina; ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/pie_admin.php'; ?>

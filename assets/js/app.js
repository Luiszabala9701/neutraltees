/*
 * Modulo: interacciones de NeutralTees.
 * Responsabilidad: manejar validaciones visuales, modales, buscador,
 * carrito, formularios admin y ayudas dinamicas sin depender de librerias.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Marca todos los campos required con un asterisco visual consistente.
    document.querySelectorAll('input[required], select[required], textarea[required]').forEach((campoObligatorio) => {
        if (campoObligatorio.type === 'hidden' || campoObligatorio.disabled) {
            return;
        }

        let etiqueta = null;

        if (campoObligatorio.id && window.CSS && typeof window.CSS.escape === 'function') {
            etiqueta = document.querySelector(`label[for="${window.CSS.escape(campoObligatorio.id)}"]`);
        }

        if (!etiqueta) {
            etiqueta = campoObligatorio.closest('label');
        }

        if (!etiqueta) {
            const grupoCampo = campoObligatorio.closest('.grupo-campo');
            etiqueta = grupoCampo ? grupoCampo.querySelector('label') : null;
        }

        if (!etiqueta || etiqueta.classList.contains('sr-only') || etiqueta.querySelector('[data-asterisco-obligatorio]')) {
            return;
        }

        const asterisco = document.createElement('span');
        asterisco.className = 'asterisco-obligatorio';
        asterisco.title = 'Obligatorio';
        asterisco.setAttribute('data-asterisco-obligatorio', '');
        asterisco.textContent = '*';
        etiqueta.append(' ', asterisco);
    });

    // Campos como DNI y telefono solo aceptan numeros, incluso al pegar texto.
    document.querySelectorAll('[data-solo-numeros]').forEach((campoNumerico) => {
        const limpiarNumero = () => {
            const valorLimpio = campoNumerico.value.replace(/\D/g, '');
            if (campoNumerico.value !== valorLimpio) {
                campoNumerico.value = valorLimpio;
            }
        };

        campoNumerico.addEventListener('input', limpiarNumero);
        campoNumerico.addEventListener('paste', () => {
            window.setTimeout(limpiarNumero, 0);
        });
    });

    // Los mensajes flash se retiran solos para no tapar la interfaz.
    document.querySelectorAll('.mensaje-flash').forEach((mensajeFlash) => {
        window.setTimeout(() => {
            mensajeFlash.classList.add('is-oculto');
            window.setTimeout(() => {
                mensajeFlash.remove();
            }, 240);
        }, 2000);
    });

    // Cuenta regresiva de codigos de email y recuperacion de contrasena.
    document.querySelectorAll('[data-contador-codigo]').forEach((contadorCodigo) => {
        const textoContador = contadorCodigo.querySelector('[data-contador-codigo-texto]');
        let segundosRestantes = Number(contadorCodigo.getAttribute('data-contador-codigo')) || 0;

        const formatearTiempo = (segundos) => {
            const minutos = Math.floor(segundos / 60);
            const segundosSueltos = segundos % 60;
            return `${String(minutos).padStart(2, '0')}:${String(segundosSueltos).padStart(2, '0')}`;
        };

        const actualizarContador = () => {
            if (!textoContador) {
                return;
            }

            textoContador.textContent = formatearTiempo(Math.max(0, segundosRestantes));

            if (segundosRestantes <= 0) {
                contadorCodigo.classList.add('contador-codigo--vencido');
                contadorCodigo.innerHTML = 'El codigo vencio. Pedi uno nuevo para continuar.';
                window.clearInterval(intervaloContador);
                return;
            }

            segundosRestantes -= 1;
        };

        const intervaloContador = window.setInterval(actualizarContador, 1000);
        actualizarContador();
    });

    // En pedidos, los cambios de estado se guardan al seleccionar una opcion.
    document.querySelectorAll('[data-envio-cambio-estado]').forEach((selectorEstado) => {
        selectorEstado.addEventListener('change', () => {
            const formulario = selectorEstado.closest('form');
            if (formulario) {
                formulario.submit();
            }
        });
    });

    // Modal reutilizable para mostrar la guia de talles desde el detalle de producto.
    const modalGuiaTalles = document.querySelector('[data-modal-guia-talles]');
    const botonAbrirGuiaTalles = document.querySelector('[data-abrir-guia-talles]');
    const botonCerrarGuiaTalles = document.querySelector('[data-cerrar-guia-talles]');

    const cerrarGuiaTalles = () => {
        if (!modalGuiaTalles) {
            return;
        }

        modalGuiaTalles.classList.remove('is-visible');
        modalGuiaTalles.setAttribute('aria-hidden', 'true');
    };

    if (botonAbrirGuiaTalles && modalGuiaTalles) {
        botonAbrirGuiaTalles.addEventListener('click', () => {
            modalGuiaTalles.classList.add('is-visible');
            modalGuiaTalles.setAttribute('aria-hidden', 'false');
            if (botonCerrarGuiaTalles) {
                botonCerrarGuiaTalles.focus();
            }
        });
    }

    if (botonCerrarGuiaTalles) {
        botonCerrarGuiaTalles.addEventListener('click', cerrarGuiaTalles);
    }

    if (modalGuiaTalles) {
        modalGuiaTalles.addEventListener('click', (evento) => {
            if (evento.target === modalGuiaTalles) {
                cerrarGuiaTalles();
            }
        });

        document.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape' && modalGuiaTalles.classList.contains('is-visible')) {
                cerrarGuiaTalles();
            }
        });
    }

    // Modal generica para confirmar bajas o acciones sensibles antes del envio.
    const modalConfirmacion = document.querySelector('[data-modal-confirmacion]');
    const modalTexto = modalConfirmacion ? modalConfirmacion.querySelector('[data-modal-texto]') : null;
    const botonConfirmar = modalConfirmacion ? modalConfirmacion.querySelector('[data-modal-confirmar]') : null;
    const botonCancelar = modalConfirmacion ? modalConfirmacion.querySelector('[data-modal-cancelar]') : null;
    const textoOriginalConfirmar = botonConfirmar ? botonConfirmar.textContent : '';
    const textoOriginalCancelar = botonCancelar ? botonCancelar.textContent : '';
    let formularioPendiente = null;

    const cerrarModalConfirmacion = () => {
        if (!modalConfirmacion) {
            return;
        }

        modalConfirmacion.classList.remove('is-visible');
        modalConfirmacion.setAttribute('aria-hidden', 'true');
        if (botonConfirmar) {
            botonConfirmar.textContent = textoOriginalConfirmar;
        }
        if (botonCancelar) {
            botonCancelar.textContent = textoOriginalCancelar;
        }
        formularioPendiente = null;
    };

    if (modalConfirmacion) {
        modalConfirmacion.addEventListener('click', (evento) => {
            if (evento.target === modalConfirmacion) {
                cerrarModalConfirmacion();
            }
        });
    }

    if (botonCancelar) {
        botonCancelar.addEventListener('click', cerrarModalConfirmacion);
    }

    if (botonConfirmar) {
        botonConfirmar.addEventListener('click', () => {
            if (!formularioPendiente) {
                cerrarModalConfirmacion();
                return;
            }

            const formulario = formularioPendiente;
            formulario.dataset.confirmado = '1';
            formulario.submit();
            delete formulario.dataset.confirmado;
            cerrarModalConfirmacion();
        });
    }

    // Modal de lectura para terminos y politicas en PDF.
    const modalDocumento = document.querySelector('[data-modal-documento]');
    const modalDocumentoTitulo = modalDocumento ? modalDocumento.querySelector('[data-modal-documento-titulo]') : null;
    const modalDocumentoIframe = modalDocumento ? modalDocumento.querySelector('[data-modal-documento-iframe]') : null;
    const modalDocumentoCerrar = modalDocumento ? modalDocumento.querySelector('[data-modal-documento-cerrar]') : null;

    const cerrarModalDocumento = () => {
        if (!modalDocumento) {
            return;
        }

        modalDocumento.classList.remove('is-visible');
        modalDocumento.setAttribute('aria-hidden', 'true');
        if (modalDocumentoIframe) {
            modalDocumentoIframe.setAttribute('src', 'about:blank');
        }
    };

    if (modalDocumento) {
        modalDocumento.addEventListener('click', (evento) => {
            if (evento.target === modalDocumento) {
                cerrarModalDocumento();
            }
        });
    }

    if (modalDocumentoCerrar) {
        modalDocumentoCerrar.addEventListener('click', cerrarModalDocumento);
    }

    document.querySelectorAll('[data-abrir-documento-legal]').forEach((boton) => {
        boton.addEventListener('click', () => {
            if (!modalDocumento || !modalDocumentoTitulo || !modalDocumentoIframe) {
                return;
            }

            modalDocumentoTitulo.textContent = boton.getAttribute('data-documento-titulo') || 'Documento';
            modalDocumentoIframe.setAttribute('src', boton.getAttribute('data-documento-url') || 'about:blank');
            modalDocumento.classList.add('is-visible');
            modalDocumento.setAttribute('aria-hidden', 'false');
        });
    });

    // Buscador publico: intenta AJAX y mantiene fallback por formulario normal.
    const buscadorPublico = document.querySelector('[data-buscador-publico]');
    if (buscadorPublico) {
        const formularioBuscador = buscadorPublico.closest('form');
        const contenedorCatalogo = document.querySelector('[data-catalogo-productos]');
        const rutaBusqueda = '/busqueda.php';

        // Contenedor para mostrar número de coincidencias junto al buscador
        let mensajeCoincidencias = document.querySelector('[data-mensaje-coincidencias]');
        if (!mensajeCoincidencias && buscadorPublico && buscadorPublico.parentNode) {
            mensajeCoincidencias = document.createElement('div');
            mensajeCoincidencias.setAttribute('data-mensaje-coincidencias', '');
            mensajeCoincidencias.className = 'buscador-coincidencias';
            mensajeCoincidencias.setAttribute('aria-live', 'polite');
            mensajeCoincidencias.style.marginTop = '6px';
            mensajeCoincidencias.style.fontSize = '0.95rem';
            buscadorPublico.parentNode.appendChild(mensajeCoincidencias);
        }

        const actualizarContadorCoincidencias = (total, consulta) => {
            if (!mensajeCoincidencias) {
                return;
            }

            const textoConsulta = consulta.trim();
            if (textoConsulta === '') {
                mensajeCoincidencias.textContent = '';
                mensajeCoincidencias.setAttribute('hidden', '');
                return;
            }

            const textoCoincidencias = total === 1 ? '1 coincidencia' : `${total} coincidencias`;
            mensajeCoincidencias.textContent = textoCoincidencias;
            mensajeCoincidencias.removeAttribute('hidden');
        };

        const actualizarContenidoResultados = (datos) => {
            if (!contenedorCatalogo) {
                return;
            }

            contenedorCatalogo.innerHTML = datos.html || '';
            actualizarContadorCoincidencias(Number(datos.total || 0), String(datos.query || ''));
        };

        let temporizadorBusqueda = null;
        let controladorBusqueda = null;

        const ejecutarBusqueda = async () => {
            if (!contenedorCatalogo) {
                return;
            }

            const consulta = buscadorPublico.value.trim();

            if (controladorBusqueda) {
                controladorBusqueda.abort();
            }

            controladorBusqueda = new AbortController();

            try {
                const url = new URL(rutaBusqueda, window.location.origin);
                url.searchParams.set('ajax', '1');
                url.searchParams.set('q', consulta);

                const respuesta = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'fetch',
                    },
                    cache: 'no-store',
                    signal: controladorBusqueda.signal,
                });

                if (!respuesta.ok) {
                    throw new Error(`HTTP ${respuesta.status}`);
                }

                const datos = await respuesta.json();
                if (!datos || datos.ok !== true) {
                    throw new Error('Respuesta de búsqueda inválida');
                }

                actualizarContenidoResultados(datos);
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                console.error('Error al buscar productos:', error);
            }
        };

        buscadorPublico.addEventListener('input', () => {
            window.clearTimeout(temporizadorBusqueda);
            temporizadorBusqueda = window.setTimeout(() => {
                ejecutarBusqueda();
            }, 250);
        });

        if (formularioBuscador) {
            formularioBuscador.setAttribute('action', rutaBusqueda);
            formularioBuscador.setAttribute('method', 'get');
        }

        actualizarContadorCoincidencias(contenedorCatalogo ? contenedorCatalogo.querySelectorAll('[data-tarjeta-producto]').length : 0, buscadorPublico.value);
    }

    // Iconos SVG usados por los botones para mostrar u ocultar contrasenas.
    const iconoMostrar = `
        <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="12" cy="12" r="2.75" fill="none" stroke="currentColor" stroke-width="2.1"/>
        </svg>
    `;

    const iconoOcultar = `
        <svg class="icono-contrasena" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"/>
            <path d="M2 12s3.5-7 10-7c1.6 0 3.1.4 4.4 1.1M22 12s-3.5 7-10 7c-1.6 0-3.1-.4-4.4-1.1" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9.2 9.2A2.75 2.75 0 0 0 12 14.8c.4 0 .8-.1 1.1-.2" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/>
        </svg>
    `;

    // Reglas compartidas por registro, seguridad y recuperacion.
    const obtenerReglasContrasena = (valor, valorCoincidir) => ({
        longitud: valor.length >= 8 && valor.length <= 16,
        numero: /[0-9]/.test(valor),
        letra: /[a-zA-ZÁÉÍÓÚÜÑáéíóúüñ]/u.test(valor),
        especial: /[^a-zA-Z0-9ÁÉÍÓÚÜÑáéíóúüñ]/u.test(valor),
        coincidencia: typeof valorCoincidir === 'string' ? valor.length > 0 && valor === valorCoincidir : true,
    });

    const actualizarAyudaContrasena = (campo) => {
        const selectorPanel = campo.getAttribute('data-objetivo-ayuda');
        const panel = selectorPanel ? document.querySelector(selectorPanel) : null;
        const lista = panel ? panel.querySelector('[data-lista-validacion-contrasena]') : null;
        const selectorCoincidencia = campo.getAttribute('data-objetivo-comparar');
        const campoCoincidir = selectorCoincidencia ? document.querySelector(selectorCoincidencia) : null;

        if (!lista) {
            return;
        }

        const reglas = obtenerReglasContrasena(campo.value.trim(), campoCoincidir ? campoCoincidir.value.trim() : undefined);

        lista.querySelectorAll('[data-regla-contrasena]').forEach((item) => {
            const regla = item.getAttribute('data-regla-contrasena');
            item.classList.toggle('es-valido', regla ? Boolean(reglas[regla]) : false);
        });
    };

    const camposConAyuda = document.querySelectorAll('[data-campo-contrasena]');
    camposConAyuda.forEach((campo) => {
        const selectorPanel = campo.getAttribute('data-objetivo-ayuda');
        const panel = selectorPanel ? document.querySelector(selectorPanel) : null;
        const actualizar = () => actualizarAyudaContrasena(campo);

        const mostrarPanel = () => {
            if (panel) {
                panel.removeAttribute('hidden');
            }
            actualizar();
        };

        const ocultarPanel = () => {
            if (panel) {
                panel.setAttribute('hidden', '');
            }
        };

        campo.addEventListener('focus', mostrarPanel);
        campo.addEventListener('input', () => {
            actualizar();
            const selectorBase = campo.getAttribute('id');
            if (selectorBase) {
                document.querySelectorAll('[data-objetivo-comparar="#' + selectorBase + '"]').forEach((campoRelacionado) => {
                    campoRelacionado.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
        });
        campo.addEventListener('blur', () => {
            window.setTimeout(ocultarPanel, 150);
        });

        actualizar();
    });

    document.querySelectorAll('[data-boton-contrasena]').forEach((boton) => {
        const selector = boton.getAttribute('data-boton-contrasena');
        const campo = selector ? document.querySelector(selector) : null;

        const actualizarIcono = (mostrando) => {
            boton.innerHTML = mostrando ? iconoOcultar : iconoMostrar;
            boton.setAttribute('title', mostrando ? 'Ocultar contraseña' : 'Mostrar contraseña');
            boton.setAttribute('aria-label', mostrando ? 'Ocultar contraseña' : 'Mostrar contraseña');
            boton.setAttribute('aria-pressed', mostrando ? 'true' : 'false');
        };

        actualizarIcono(campo ? campo.getAttribute('type') === 'text' : false);

        boton.addEventListener('click', () => {
            if (!campo) {
                return;
            }

            const mostrando = campo.getAttribute('type') === 'text';
            campo.setAttribute('type', mostrando ? 'password' : 'text');
            actualizarIcono(!mostrando);
            campo.focus();
        });
    });

    // Slider de ofertas del inicio; solo se activa si hay productos destacados.
    const bannerOfertas = document.querySelector('[data-banner-ofertas]');
    if (bannerOfertas) {
        const track = bannerOfertas.querySelector('[data-banner-track]');
        const slides = Array.from(bannerOfertas.querySelectorAll('[data-banner-slide]'));
        const dots = Array.from(bannerOfertas.querySelectorAll('[data-banner-dot]'));
        const intervalo = Number(bannerOfertas.getAttribute('data-banner-intervalo')) || 5500;

        if (track && slides.length > 1) {
            let indiceActivo = 0;

            const actualizar = () => {
                track.style.transform = `translateX(-${indiceActivo * 100}%)`;
                dots.forEach((dot, indice) => {
                    dot.classList.toggle('is-active', indice === indiceActivo);
                });
            };

            dots.forEach((dot, indice) => {
                dot.addEventListener('click', () => {
                    indiceActivo = indice;
                    actualizar();
                });
            });

            actualizar();

            window.setInterval(() => {
                indiceActivo = (indiceActivo + 1) % slides.length;
                actualizar();
            }, intervalo);
        }
    }

    document.querySelectorAll('.perfil-usuario__resumen').forEach((resumen) => {
        resumen.style.cursor = 'pointer';
    });

    // Vista previa y validacion de precios cuando el admin marca un producto en oferta.
    const formulariosOferta = document.querySelectorAll('[data-formulario-oferta]');
    formulariosOferta.forEach((formulario) => {
        const casillaOferta = formulario.querySelector('[data-campo-oferta]');
        const campoPrecioNormal = formulario.querySelector('[data-campo-precio-normal]');
        const campoPrecioDescuento = formulario.querySelector('[data-campo-precio-descuento]');
        const vistaDescuento = formulario.querySelector('[data-vista-descuento]');
        const errorPrecioDescuento = formulario.querySelector('[data-error-precio-descuento]');

        const mostrarError = (elemento, mensaje) => {
            if (!elemento) {
                return;
            }

            if (mensaje) {
                elemento.textContent = mensaje;
                elemento.removeAttribute('hidden');
            } else {
                elemento.textContent = '';
                elemento.setAttribute('hidden', '');
            }
        };

        const actualizarOferta = () => {
            if (!casillaOferta || !campoPrecioNormal || !campoPrecioDescuento) {
                return;
            }

            const ofertaActiva = casillaOferta.checked;
            campoPrecioNormal.disabled = false;
            campoPrecioDescuento.disabled = !ofertaActiva;

            if (!ofertaActiva) {
                campoPrecioDescuento.value = '';
                mostrarError(errorPrecioDescuento, '');
                if (vistaDescuento) {
                    vistaDescuento.textContent = '';
                }
                return;
            }

            const precioNormal = Number(campoPrecioNormal.value);
            const precioDescuento = Number(campoPrecioDescuento.value);

            if (Number.isFinite(precioNormal) && Number.isFinite(precioDescuento) && precioNormal > precioDescuento && precioDescuento > 0) {
                mostrarError(errorPrecioDescuento, '');
                const porcentaje = Math.round((1 - (precioDescuento / precioNormal)) * 100);
                if (vistaDescuento) {
                    vistaDescuento.textContent = `${porcentaje}% off`;
                }
            } else {
                mostrarError(errorPrecioDescuento, 'El precio con descuento debe ser menor al precio normal.');
                if (vistaDescuento) {
                    vistaDescuento.textContent = '';
                }
            }
        };

        ['change', 'input', 'keyup'].forEach((eventoNombre) => {
            casillaOferta.addEventListener(eventoNombre, actualizarOferta);
            campoPrecioNormal.addEventListener(eventoNombre, actualizarOferta);
            campoPrecioDescuento.addEventListener(eventoNombre, actualizarOferta);
        });

        actualizarOferta();
    });

    document.querySelectorAll('[data-validar-variantes-stock]').forEach((formulario) => {
        const camposSku = Array.from(formulario.querySelectorAll('input[data-campo-sku]'));
        const camposStock = formulario.querySelectorAll('input[name*="[stock]"]');
        const idProducto = formulario.getAttribute('data-producto-id') || '';
        const temporizadoresSku = new WeakMap();
        const solicitudesSku = new WeakMap();
        const obtenerErrorSku = (campo) => {
            const contenedor = campo.closest('.grupo-campo');
            return contenedor ? contenedor.querySelector('[data-error-sku]') : null;
        };

        const mostrarErrorSku = (campo, mensaje) => {
            const errorSku = obtenerErrorSku(campo);

            if (!errorSku) {
                return;
            }

            if (mensaje) {
                errorSku.textContent = mensaje;
                errorSku.removeAttribute('hidden');
            } else {
                errorSku.textContent = '';
                errorSku.setAttribute('hidden', '');
            }
        };

        const cancelarValidacionRemota = (campo) => {
            const solicitudActual = solicitudesSku.get(campo) || 0;
            solicitudesSku.set(campo, solicitudActual + 1);
            window.clearTimeout(temporizadoresSku.get(campo));
        };

        const validarSkuLocal = (campo, mostrarMensaje = true) => {
            const valor = campo.value.trim();

            if (valor === '') {
                if (mostrarMensaje) {
                    mostrarErrorSku(campo, '');
                }
                cancelarValidacionRemota(campo);
                return true;
            }

            const repetido = camposSku.some((otroCampo) => otroCampo !== campo && otroCampo.value.trim() === valor);

            if (repetido) {
                if (mostrarMensaje) {
                    mostrarErrorSku(campo, 'El SKU no puede repetirse entre variantes.');
                }
                cancelarValidacionRemota(campo);
                return false;
            }

            if (mostrarMensaje) {
                mostrarErrorSku(campo, '');
            }

            return true;
        };

        const validarSkuEnBase = (campo) => {
            if (!validarSkuLocal(campo, true)) {
                return;
            }

            const valor = campo.value.trim();

            const solicitudAnterior = solicitudesSku.get(campo) || 0;
            const siguienteSolicitud = solicitudAnterior + 1;
            solicitudesSku.set(campo, siguienteSolicitud);

            window.clearTimeout(temporizadoresSku.get(campo));
            temporizadoresSku.set(campo, window.setTimeout(() => {
                const url = new URL('/admin/producto_formulario.php', window.location.origin);
                url.searchParams.set('ajax', 'validar_sku');
                url.searchParams.set('sku', valor);
                if (idProducto !== '') {
                    url.searchParams.set('id_producto', idProducto);
                }

                fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                })
                    .then((respuesta) => (respuesta.ok ? respuesta.json() : null))
                    .then((datos) => {
                        if (solicitudesSku.get(campo) !== siguienteSolicitud || !datos) {
                            return;
                        }

                        if (datos.existe) {
                            mostrarErrorSku(campo, 'Ese SKU ya existe en otro producto.');
                        } else {
                            mostrarErrorSku(campo, '');
                        }
                    })
                    .catch(() => {
                        if (solicitudesSku.get(campo) === siguienteSolicitud) {
                            mostrarErrorSku(campo, '');
                        }
                    });
            }, 260));
        };

        const validarTodo = (campoPreferido = null) => {
            let todoValido = true;

            camposSku.forEach((campo) => {
                const esCampoPreferido = campoPreferido ? campo === campoPreferido : true;
                const esValidoLocal = validarSkuLocal(campo, esCampoPreferido);
                todoValido = todoValido && esValidoLocal;

                if (esValidoLocal) {
                    validarSkuEnBase(campo);
                }
            });

            return todoValido;
        };

        camposSku.forEach((campo) => {
            campo.addEventListener('keyup', () => {
                validarTodo(campo);
            });
            campo.addEventListener('input', () => {
                validarTodo(campo);
            });
        });

        formulario.addEventListener('submit', (evento) => {
            const hayStockPositivo = Array.from(camposStock).some((campo) => {
                const valor = Number(campo.value);
                return Number.isFinite(valor) && valor > 0;
            });

            if (formulario.hasAttribute('data-exigir-stock-positivo') && !hayStockPositivo) {
                alert('Debés cargar stock en al menos una variante para poder guardar el producto.');
                evento.preventDefault();
                return;
            }

            if (!validarTodo(null)) {
                evento.preventDefault();
            }
        });
    });

    // Actualiza el badge del carrito sin recargar la pagina.
    const actualizarContadorCarrito = (cantidad) => {
        if (!Number.isFinite(cantidad)) {
            return;
        }

        const enlaceCarrito = document.querySelector('[data-enlace-carrito]');
        if (!enlaceCarrito) {
            return;
        }

        let contador = enlaceCarrito.querySelector('.contador-carrito');
        if (!contador) {
            contador = document.createElement('span');
            contador.className = 'contador-carrito';
            enlaceCarrito.appendChild(contador);
        }

        contador.textContent = String(cantidad);
        contador.hidden = cantidad <= 0;
    };

    // Sincroniza talles, stock visible y alta al carrito en el detalle de producto.
    const formularioProducto = document.querySelector('[data-formulario-producto]');
    if (formularioProducto) {
        const selectorVariante = formularioProducto.querySelector('[data-variante-selector]');
        const stockVariante = formularioProducto.querySelector('[data-variante-stock-input]');
        const skuVariante = formularioProducto.querySelector('[data-variante-sku-input]');
        const stockVarianteTexto = formularioProducto.querySelector('[data-variante-stock-texto]');
        const skuVarianteTexto = formularioProducto.querySelector('[data-variante-sku-texto]');
        const hiddenTalles = Array.from(formularioProducto.querySelectorAll('[data-variante-hidden-talle]'));
        const hiddenStocks = Array.from(formularioProducto.querySelectorAll('[data-variante-hidden-stock]'));
        const hiddenSkus = Array.from(formularioProducto.querySelectorAll('[data-variante-hidden-sku]'));
        const inputCantidad = formularioProducto.querySelector('input[name="cantidad"]');
        const botonAgregar = formularioProducto.querySelector('button[type="submit"]');
        let indiceVarianteActiva = selectorVariante ? Number(selectorVariante.value) : 0;
        let vistaVarianteInicializada = false;

        const sincronizarIndice = (indice) => {
            if (!Number.isFinite(indice) || indice < 0) {
                return;
            }

            if (hiddenStocks[indice] && stockVariante) {
                hiddenStocks[indice].value = stockVariante.value;
            }

            if (hiddenSkus[indice] && skuVariante) {
                hiddenSkus[indice].value = skuVariante.value.trim();
            }
        };

        const actualizarVistaVariante = () => {
            if (!selectorVariante || (!stockVariante && !stockVarianteTexto) || (!skuVariante && !skuVarianteTexto)) {
                return;
            }

            const opcionSeleccionada = selectorVariante.options[selectorVariante.selectedIndex];
            if (!opcionSeleccionada) {
                return;
            }

            // En la primera carga todavía no hay datos visibles para guardar.
            // Sin esta condición, el stock inicial se copiaba vacío al campo oculto y terminaba como 0.
            if (vistaVarianteInicializada) {
                sincronizarIndice(indiceVarianteActiva);
            }

            const indiceSeleccionado = Number(selectorVariante.value);
            const stockOculto = hiddenStocks[indiceSeleccionado] ? hiddenStocks[indiceSeleccionado].value : '';
            const stockDesdeOpcion = Number(opcionSeleccionada.getAttribute('data-stock'));
            const stockDesdeHidden = stockOculto !== '' ? Number(stockOculto) : stockDesdeOpcion;
            const skuDesdeHidden = hiddenSkus[indiceSeleccionado] ? hiddenSkus[indiceSeleccionado].value : '';

            const stockMostrado = Number.isFinite(stockDesdeHidden) ? String(Math.max(0, stockDesdeHidden)) : '0';
            const skuMostrado = skuDesdeHidden !== '' ? skuDesdeHidden : (opcionSeleccionada.getAttribute('data-sku') || 'Sin SKU');

            if (stockVariante) {
                stockVariante.value = stockMostrado;
            }

            if (stockVarianteTexto) {
                stockVarianteTexto.textContent = stockMostrado;
            }

            if (skuVariante) {
                skuVariante.value = skuMostrado;
            }

            if (skuVarianteTexto) {
                skuVarianteTexto.textContent = skuMostrado;
            }
            indiceVarianteActiva = Number(selectorVariante.value);
            vistaVarianteInicializada = true;
        };

        const sincronizarVarianteActiva = () => {
            if (!selectorVariante || !stockVariante || !skuVariante) {
                return;
            }

            const indiceActivo = Number(selectorVariante.value);
            if (!Number.isFinite(indiceActivo)) {
                return;
            }

            sincronizarIndice(indiceActivo);

            const opcionSeleccionada = selectorVariante.options[selectorVariante.selectedIndex];
            if (opcionSeleccionada) {
                opcionSeleccionada.setAttribute('data-stock', stockVariante.value);
                opcionSeleccionada.setAttribute('data-sku', skuVariante.value.trim());
            }
        };

        if (selectorVariante) {
            selectorVariante.addEventListener('change', actualizarVistaVariante);
            actualizarVistaVariante();
        }

        if (stockVariante) {
            stockVariante.addEventListener('input', sincronizarVarianteActiva);
            stockVariante.addEventListener('change', sincronizarVarianteActiva);
        }

        if (skuVariante) {
            skuVariante.addEventListener('input', sincronizarVarianteActiva);
            skuVariante.addEventListener('change', sincronizarVarianteActiva);
        }

        const actualizarCantidadMaxima = () => {
            const seleccionado = formularioProducto.querySelector('input[type="radio"][name="id_variante"]:checked');
            const stock = seleccionado ? Number(seleccionado.getAttribute('data-stock-max')) : NaN;
            if (!inputCantidad) {
                return;
            }

            const maximo = Number.isFinite(stock) && stock > 0 ? stock : 1;
            inputCantidad.setAttribute('max', String(maximo));
            if (Number(inputCantidad.value) > maximo) {
                inputCantidad.value = String(maximo);
            }

            if (botonAgregar) {
                botonAgregar.disabled = maximo <= 0;
            }
        };

        const esFormularioDetalleProducto = Boolean(
            inputCantidad && formularioProducto.querySelector('input[type="radio"][name="id_variante"]')
        );

        formularioProducto.addEventListener('change', () => {
            actualizarCantidadMaxima();
            sincronizarVarianteActiva();
        });

        if (esFormularioDetalleProducto) {
            formularioProducto.addEventListener('submit', async (evento) => {
                evento.preventDefault();

                sincronizarVarianteActiva();

                const seleccionado = formularioProducto.querySelector('input[type="radio"][name="id_variante"]:checked');
                const stock = seleccionado ? Number(seleccionado.getAttribute('data-stock-max')) : NaN;
                const cantidad = inputCantidad ? Number(inputCantidad.value) : 1;
                const maximo = Number.isFinite(stock) && stock > 0 ? stock : 0;

                if (maximo <= 0) {
                    alert('No hay stock disponible para la variante seleccionada.');
                    return;
                }

                const cantidadValida = Number.isFinite(cantidad) ? Math.min(Math.max(1, cantidad), maximo) : 1;
                if (inputCantidad) {
                    inputCantidad.value = String(cantidadValida);
                }

                const datos = new FormData(formularioProducto);
                datos.set('cantidad', String(cantidadValida));
                datos.set('origen', 'ajax');

                try {
                    const respuesta = await fetch(formularioProducto.action, {
                        method: 'POST',
                        body: datos,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const contenido = await respuesta.json().catch(() => null);
                    if (!respuesta.ok || !contenido || contenido.ok !== true) {
                        alert((contenido && contenido.mensaje) ? contenido.mensaje : 'No se pudo agregar el producto al carrito.');
                        return;
                    }

                    if (typeof contenido.cantidad_carrito === 'number') {
                        actualizarContadorCarrito(contenido.cantidad_carrito);
                    }

                    if (seleccionado) {
                        const stockRestante = Math.max(0, maximo - cantidadValida);
                        seleccionado.setAttribute('data-stock-max', String(stockRestante));
                        if (inputCantidad) {
                            inputCantidad.setAttribute('max', String(stockRestante));
                            inputCantidad.value = stockRestante > 0 ? '1' : '0';
                        }
                        if (botonAgregar) {
                            botonAgregar.disabled = stockRestante <= 0;
                        }
                    }

                    if (botonAgregar) {
                        botonAgregar.textContent = 'Agregado';
                        setTimeout(() => {
                            botonAgregar.textContent = 'Agregar al carrito';
                        }, 1200);
                    }
                } catch (error) {
                    alert('No se pudo agregar el producto al carrito.');
                }
            });

            actualizarCantidadMaxima();
        } else {
            formularioProducto.addEventListener('submit', () => {
                sincronizarVarianteActiva();
            });
        }
    }

    // Abre/cierra el menu de perfil en pantallas publicas.
    document.querySelectorAll('[data-abrir-perfil]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const menu = document.querySelector('[data-menu-perfil]');
            if (menu) {
                menu.toggleAttribute('hidden');
            }
        });
    });

    // Formularios con data-confirmar pasan por modal antes de enviarse.
    document.querySelectorAll('[data-confirmar]').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            if (formulario.dataset.confirmado === '1') {
                return;
            }

            const texto = formulario.getAttribute('data-confirmar') || '¿Confirmás la acción?';
            if (!modalConfirmacion || !modalTexto || !botonConfirmar) {
                evento.preventDefault();
                return;
            }

            evento.preventDefault();
            formularioPendiente = formulario;
            modalTexto.textContent = texto;
            if (botonConfirmar) {
                botonConfirmar.textContent = formulario.getAttribute('data-confirmar-aceptar') || textoOriginalConfirmar;
            }
            if (botonCancelar) {
                botonCancelar.textContent = formulario.getAttribute('data-confirmar-cancelar') || textoOriginalCancelar;
            }
            modalConfirmacion.classList.add('is-visible');
            modalConfirmacion.setAttribute('aria-hidden', 'false');
            if (typeof botonConfirmar.focus === 'function') {
                botonConfirmar.focus();
            }
        });
    });

    // Valida variantes del formulario de producto antes de guardar.
    document.querySelectorAll('[data-validar-variantes-stock]').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            const camposStock = formulario.querySelectorAll('input[name*="[stock]"]');
            const camposSku = formulario.querySelectorAll('input[data-campo-sku]');
            const hayStockPositivo = Array.from(camposStock).some((campo) => {
                const valor = Number(campo.value);
                return Number.isFinite(valor) && valor > 0;
            });

            const skus = Array.from(camposSku)
                .map((campo) => campo.value.trim())
                .filter((valor) => valor !== '');
            const skusUnicos = new Set(skus);

            if (formulario.hasAttribute('data-exigir-stock-positivo') && !hayStockPositivo) {
                alert('Debés cargar stock en al menos una variante para poder guardar el producto.');
                evento.preventDefault();
                return;
            }

            if (skus.length !== skusUnicos.size) {
                alert('No se pueden repetir los SKU entre variantes del mismo producto.');
                evento.preventDefault();
            }
        });
    });

    // En alta de variante, oculta talles que ya existen para el producto elegido.
    const productoParaVariante = document.querySelector('[data-producto-para-variante]');
    const talleParaVariante = document.querySelector('[data-talle-para-variante]');

    if (productoParaVariante && talleParaVariante) {
        const actualizarTallesDisponibles = () => {
            const opcionProducto = productoParaVariante.options[productoParaVariante.selectedIndex];
            const tallesCreados = new Set(
                (opcionProducto ? opcionProducto.getAttribute('data-talles-creados') || '' : '')
                    .split(',')
                    .map((talle) => talle.trim())
                    .filter((talle) => talle !== '')
            );

            Array.from(talleParaVariante.options).forEach((opcionTalle) => {
                if (opcionTalle.value === '') {
                    opcionTalle.disabled = false;
                    return;
                }

                opcionTalle.disabled = tallesCreados.has(opcionTalle.value);
            });

            if (talleParaVariante.selectedOptions[0] && talleParaVariante.selectedOptions[0].disabled) {
                talleParaVariante.value = '';
            }
        };

        productoParaVariante.addEventListener('change', actualizarTallesDisponibles);
        actualizarTallesDisponibles();
    }

    // En movimiento de stock, muestra solo variantes reales del producto seleccionado.
    const productoMovimientoStock = document.querySelector('[data-producto-movimiento-stock]');
    const varianteMovimientoStock = document.querySelector('[data-variante-movimiento-stock]');

    if (productoMovimientoStock && varianteMovimientoStock) {
        const actualizarVariantesDeProducto = () => {
            const idProducto = productoMovimientoStock.value;
            let hayVarianteVisibleSeleccionada = false;

            Array.from(varianteMovimientoStock.options).forEach((opcionVariante) => {
                if (opcionVariante.value === '') {
                    opcionVariante.hidden = false;
                    opcionVariante.disabled = false;
                    return;
                }

                const perteneceAlProducto = idProducto !== '' && opcionVariante.getAttribute('data-producto-id') === idProducto;
                opcionVariante.hidden = !perteneceAlProducto;
                opcionVariante.disabled = !perteneceAlProducto;

                if (opcionVariante.selected && perteneceAlProducto) {
                    hayVarianteVisibleSeleccionada = true;
                }
            });

            if (!hayVarianteVisibleSeleccionada) {
                varianteMovimientoStock.value = '';
            }
        };

        productoMovimientoStock.addEventListener('change', actualizarVariantesDeProducto);
        actualizarVariantesDeProducto();
    }

    // Cierra la sesion si pasan 3 minutos sin actividad en el navegador.
    const controlInactividad = document.body.hasAttribute('data-control-inactividad');
    const tiempoInactividadSegundos = Number(document.body.dataset.tiempoInactividad || 0);

    if (controlInactividad && tiempoInactividadSegundos > 0) {
        const tiempoInactividadMs = tiempoInactividadSegundos * 1000;
        let temporizadorInactividad = null;
        let ultimoReinicio = 0;

        const cerrarPorInactividad = () => {
            window.location.href = '/sesion_expirada.php';
        };

        const reiniciarTemporizadorInactividad = () => {
            const ahora = Date.now();
            if (ahora - ultimoReinicio < 1000) {
                return;
            }

            ultimoReinicio = ahora;
            window.clearTimeout(temporizadorInactividad);
            temporizadorInactividad = window.setTimeout(cerrarPorInactividad, tiempoInactividadMs);
        };

        ['click', 'keydown', 'scroll', 'touchstart', 'input'].forEach((evento) => {
            document.addEventListener(evento, reiniciarTemporizadorInactividad, { passive: true });
        });

        reiniciarTemporizadorInactividad();
    }
});

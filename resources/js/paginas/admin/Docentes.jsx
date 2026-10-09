import PropTypes from 'prop-types';
import { useRef, useState } from 'react';
import { ArrowRight, Copy, KeyRound, ShieldCheck } from 'lucide-react';
import { api } from '../../api/cliente';
import { codigoDe, erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Campo from '../../componentes/Campo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import FiltroFacultad, {
    PuntoFacultad,
    usarColorDeFacultad,
} from '../../componentes/FiltroFacultad';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Tarjeta from '../../componentes/Tarjeta';

const POR_PAGINA = 20;
const FICHA =
    'inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const fichaClases = (activo) =>
    `${FICHA} ${activo ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'}`;
const USUARIO = /^[a-z0-9._-]+$/i;
const CORREO = /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i;
const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;
const miles = (n) => (typeof n === 'number' ? n.toLocaleString('es-BO') : undefined);

const CUENTA = {
    activa: { tono: 'exito', texto: 'Cuenta activa' },
    temporal: { tono: 'primario', texto: 'Temporal' },
    sin_cuenta: { tono: 'neutro', texto: 'Sin cuenta' },
};

const usuarioValido = (v) => USUARIO.test(v.trim());
const correoValido = (v) => v.trim() === '' || CORREO.test(v.trim());

// Usuario propuesto por el servidor, editable, y correo opcional.
function FormularioActivar({ docente, onActivada, onRechazo }) {
    const [usuario, setUsuario] = useState(docente.usuario_sugerido ?? '');
    const [correo, setCorreo] = useState('');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    const valido = usuarioValido(usuario) && correoValido(correo);

    function activar(evento) {
        evento.preventDefault();
        if (!valido || enviando) return;
        const escrito = correo.trim();
        setEnviando(true);
        api.post(`/docentes/${docente.id}/cuenta`, {
            usuario: usuario.trim().toLowerCase(),
            correo: escrito === '' ? null : escrito,
        })
            .then((respuesta) => onActivada(respuesta.data?.data ?? {}, escrito))
            .catch((error) => {
                setEnviando(false);
                const porCampo = erroresDe(error);
                if (porCampo.usuario || porCampo.correo) setErrores(porCampo);
                else onRechazo(error);
            });
    }

    return (
        <form className="space-y-4" onSubmit={activar} noValidate>
            <Campo
                etiqueta="Usuario"
                requerido
                placeholder="nombre.apellido"
                value={usuario}
                error={errores.usuario}
                ayuda={usuario === docente.usuario_sugerido ? 'Sugerido' : undefined}
                autoComplete="off"
                autoCapitalize="none"
                spellCheck={false}
                onChange={(e) => {
                    setUsuario(e.target.value);
                    setErrores((previos) => ({ ...previos, usuario: undefined }));
                }}
                validar={(v) =>
                    usuarioValido(String(v)) ? null : 'Solo letras, números, puntos y guiones'
                }
            />
            <Campo
                etiqueta="Correo"
                type="email"
                placeholder="usuario@umss.edu.bo"
                value={correo}
                error={errores.correo}
                autoComplete="off"
                onChange={(e) => {
                    setCorreo(e.target.value);
                    setErrores((previos) => ({ ...previos, correo: undefined }));
                }}
                validar={(v) => (correoValido(String(v)) ? null : 'Correo no válido')}
            />
            <Boton type="submit" className="w-full sm:w-auto" disabled={!valido || enviando}>
                <KeyRound className="h-4 w-4" aria-hidden="true" /> Activar cuenta
            </Boton>
        </form>
    );
}

FormularioActivar.propTypes = {
    docente: PropTypes.shape({
        id: PropTypes.number.isRequired,
        usuario_sugerido: PropTypes.string,
    }).isRequired,
    onActivada: PropTypes.func.isRequired,
    onRechazo: PropTypes.func.isRequired,
};

// Docentes de la oferta. Un docente es una sola persona con una sola
// cuenta, aunque dicte en más de una facultad. La activación funciona como
// una cola: se filtra «Sin cuenta», se activa y se pasa al siguiente.
export default function Docentes() {
    const lista = usarPaginaServidor({
        porPagina: POR_PAGINA,
        filtros: { facultad: null, sin_cuenta: null, varias_facultades: null },
    });
    const { datos, meta, cargando, error, actualizando, recargar } = usarConsulta('/docentes', {
        parametros: lista.parametros,
    });
    const [elegido, setElegido] = useState(null);
    // La contraseña temporal recién emitida: vive solo mientras el detalle
    // del docente activado sigue abierto.
    const [recien, setRecien] = useState(null);
    const [buscando, setBuscando] = useState(false);
    const posicion = useRef(0);
    const detalle = useRef(null);
    const [aviso, avisar] = useAviso();
    const colorDe = usarColorDeFacultad();

    // Al cargar se abre el primero de la lista.
    if (elegido === null && datos?.length) setElegido(datos[0].id);

    const ficha = usarConsulta(elegido === null ? null : `/docentes/${elegido}`);
    const docente = ficha.datos?.id === elegido ? ficha.datos : null;
    const activada = recien !== null && recien.docenteId === elegido ? recien : null;

    // Los que siguen sin cuenta en la lista filtrada, una vez activada una.
    const restantes = usarConsulta('/docentes', {
        parametros: { ...lista.parametros, sin_cuenta: 1, pagina: 1, por_pagina: 1 },
        activa: activada !== null,
    });
    const quedan = (restantes.meta?.total ?? 0) > 0;

    const conteos = meta?.conteos ?? {};
    const { facultad, sin_cuenta: soloSinCuenta, varias_facultades: soloVarias } = lista.filtros;

    function elegir(id, desplazar = true) {
        setElegido(id);
        setRecien(null);
        // En el teléfono el detalle queda debajo de la lista.
        if (desplazar && window.matchMedia?.('(max-width: 1023px)').matches) {
            requestAnimationFrame(() =>
                detalle.current?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
            );
        }
    }

    function alActivar(resultado, correoEscrito) {
        posicion.current = Math.max(
            0,
            (datos ?? []).findIndex((d) => d.id === elegido)
        );
        setRecien({ ...resultado, docenteId: elegido, correoEscrito });
        avisar('Cuenta activada');
        recargar();
        ficha.recargar();
    }

    function alRechazar(rechazo) {
        const yaTiene = estadoDe(rechazo) === 409 && codigoDe(rechazo) === 'YA_TIENE_CUENTA';
        avisar(mensajeDe(rechazo, 'No se pudo activar'), 'error');
        if (yaTiene || estadoDe(rechazo) === 404) {
            recargar();
            ficha.recargar();
        }
    }

    function copiar() {
        const copia = navigator.clipboard?.writeText(activada.contrasena_temporal);
        if (!copia) {
            avisar('No se pudo copiar', 'error');
            return;
        }
        copia
            .then(() => avisar('Contraseña copiada'))
            .catch(() => avisar('No se pudo copiar', 'error'));
    }

    // El siguiente sin cuenta de la lista filtrada: en esta página desde el
    // lugar del recién activado; si no, en las páginas que siguen; si
    // tampoco, el primero de la lista.
    async function irAlSiguiente() {
        const pagina = datos ?? [];
        const lugar = pagina.findIndex((d) => d.id === elegido);
        const desde = lugar >= 0 ? lugar + 1 : posicion.current;
        const sirve = (d) => d.cuenta === 'sin_cuenta' && d.id !== elegido;

        const aqui = pagina.slice(desde).find(sirve);
        if (aqui) {
            elegir(aqui.id, false);
            return;
        }

        setBuscando(true);
        try {
            const ultima = Math.max(1, Math.ceil((meta?.total ?? 0) / POR_PAGINA));
            for (let n = (meta?.pagina ?? lista.pagina) + 1; n <= ultima; n += 1) {
                const respuesta = await api.get('/docentes', {
                    params: { ...lista.parametros, pagina: n },
                });
                const hallado = (respuesta.data?.data ?? []).find(sirve);
                if (hallado) {
                    lista.irA(n);
                    elegir(hallado.id, false);
                    return;
                }
            }
            const primero = (restantes.datos ?? []).find((d) => d.id !== elegido);
            if (primero) elegir(primero.id, false);
        } catch (fallo) {
            avisar(mensajeDe(fallo, 'No se pudo cargar'), 'error');
        } finally {
            setBuscando(false);
        }
    }

    return (
        <div className="space-y-6">
            <Encabezado
                titulo="Docentes"
                subtitulo={
                    meta
                        ? `${miles(conteos.todas ?? 0)} docentes · ${miles(conteos.varias_facultades ?? 0)} en más de una facultad`
                        : undefined
                }
            />

            <FiltroFacultad
                campo="clave"
                valor={facultad}
                onCambiar={(clave) => lista.ponerFiltro('facultad', clave)}
                conteo={(sigla) => miles(sigla ? conteos[sigla] : conteos.todas)}
                extra={
                    <>
                        <button
                            type="button"
                            aria-pressed={Boolean(soloSinCuenta)}
                            onClick={() =>
                                lista.ponerFiltro('sin_cuenta', soloSinCuenta ? null : 1)
                            }
                            className={fichaClases(Boolean(soloSinCuenta))}
                        >
                            Sin cuenta
                            <span className={soloSinCuenta ? 'text-slate-300' : 'text-slate-500'}>
                                {miles(conteos.sin_cuenta)}
                            </span>
                        </button>
                        <button
                            type="button"
                            aria-pressed={Boolean(soloVarias)}
                            onClick={() =>
                                lista.ponerFiltro('varias_facultades', soloVarias ? null : 1)
                            }
                            className={fichaClases(Boolean(soloVarias))}
                        >
                            Más de una facultad
                            <span className={soloVarias ? 'text-slate-300' : 'text-slate-500'}>
                                {miles(conteos.varias_facultades)}
                            </span>
                        </button>
                    </>
                }
            />

            <div className="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
                <Tarjeta sinRelleno titulo="Docentes" className="self-start">
                    <div className="border-b border-slate-200 p-4">
                        <Buscador
                            valor={lista.buscar}
                            onCambiar={lista.ponerBuscar}
                            placeholder="Nombre del docente"
                            etiqueta="Buscar docente"
                        />
                    </div>
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        vacio={datos?.length === 0}
                        textoVacio="Sin resultados"
                        onReintentar={recargar}
                        filas={8}
                    >
                        <ul className="divide-y divide-slate-200">
                            {(datos ?? []).map((d) => {
                                const actual = elegido === d.id;
                                const cuenta = CUENTA[d.cuenta] ?? CUENTA.sin_cuenta;
                                return (
                                    <li key={d.id}>
                                        <button
                                            type="button"
                                            aria-pressed={actual}
                                            onClick={() => elegir(d.id)}
                                            className={`flex min-h-14 w-full items-center gap-3 px-4 py-2.5 text-left focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600 ${
                                                actual ? 'bg-primary-50' : 'hover:bg-slate-50'
                                            }`}
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span
                                                    className="block truncate text-sm font-medium text-slate-800"
                                                    title={d.nombre}
                                                >
                                                    {d.nombre}
                                                </span>
                                                <span className="mt-0.5 flex flex-wrap gap-x-3 gap-y-1">
                                                    {d.facultades.map((f) => (
                                                        <span
                                                            key={f.sigla}
                                                            className="inline-flex items-center gap-1.5 text-xs text-slate-600"
                                                        >
                                                            <span
                                                                className="h-2 w-2 rounded-full"
                                                                style={{
                                                                    backgroundColor: colorDe(
                                                                        f.sigla
                                                                    ),
                                                                }}
                                                            />
                                                            {f.sigla} ·{' '}
                                                            {plural(f.grupos, 'grupo', 'grupos')}
                                                        </span>
                                                    ))}
                                                </span>
                                            </span>
                                            <Insignia tono={cuenta.tono}>{cuenta.texto}</Insignia>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </EstadoCarga>
                    <Paginacion
                        {...lista.paginacion(meta)}
                        unidad={['docente', 'docentes']}
                        className="border-t border-slate-200"
                    />
                </Tarjeta>

                <div ref={detalle} className="min-w-0 scroll-mt-4 lg:sticky lg:top-4 lg:self-start">
                    {elegido !== null && !docente && (
                        <Tarjeta sinRelleno titulo="Docente">
                            <EstadoCarga
                                cargando={!ficha.error}
                                error={ficha.error}
                                onReintentar={ficha.recargar}
                                filas={4}
                            />
                        </Tarjeta>
                    )}
                    {docente && (
                        <Tarjeta
                            titulo={docente.nombre}
                            acciones={
                                <span className="text-xs text-slate-600">
                                    {plural(docente.grupos, 'grupo', 'grupos')} · 1 cuenta
                                </span>
                            }
                        >
                            <ul className="mb-5 space-y-3">
                                {docente.facultades.map((f) => (
                                    <li
                                        key={f.sigla}
                                        className="rounded-lg border border-slate-200 p-3"
                                        style={{
                                            borderLeftWidth: 4,
                                            borderLeftColor: colorDe(f.sigla),
                                        }}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <PuntoFacultad sigla={f.sigla} />
                                            <span className="text-xs text-slate-600">
                                                {plural(f.grupos, 'grupo', 'grupos')}
                                            </span>
                                        </div>
                                        <ul className="mt-1.5 space-y-0.5 text-sm text-slate-700">
                                            {f.materias.map((m) => (
                                                <li key={m} className="truncate" title={m}>
                                                    {m}
                                                </li>
                                            ))}
                                        </ul>
                                    </li>
                                ))}
                            </ul>

                            {activada ? (
                                <div className="space-y-3">
                                    <div className="rounded-lg border border-primary-200 bg-primary-50 p-4">
                                        <p className="text-xs font-medium uppercase tracking-wide text-primary-700">
                                            Contraseña temporal
                                        </p>
                                        <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                                            <code className="break-all text-lg font-semibold tracking-wider text-slate-900">
                                                {activada.contrasena_temporal}
                                            </code>
                                            <Boton variante="secundario" onClick={copiar}>
                                                <Copy className="h-4 w-4" aria-hidden="true" />{' '}
                                                Copiar
                                            </Boton>
                                        </div>
                                        <p className="mt-2 break-words text-sm text-slate-700">
                                            {activada.usuario}
                                            {activada.enviada_a
                                                ? ` · enviada a ${activada.enviada_a}`
                                                : activada.correoEscrito
                                                  ? ' · correo no enviado'
                                                  : ' · sin correo'}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <p className="text-sm text-slate-600">Caduca en 72 horas</p>
                                        {quedan && (
                                            <Boton
                                                onClick={irAlSiguiente}
                                                disabled={buscando || actualizando}
                                            >
                                                Siguiente sin cuenta{' '}
                                                <ArrowRight
                                                    className="h-4 w-4"
                                                    aria-hidden="true"
                                                />
                                            </Boton>
                                        )}
                                    </div>
                                </div>
                            ) : docente.cuenta ? (
                                <div className="flex items-center gap-3 rounded-lg bg-success-50 p-4 text-sm text-success-900">
                                    <ShieldCheck
                                        className="h-5 w-5 shrink-0 text-success-600"
                                        aria-hidden="true"
                                    />
                                    <p className="min-w-0 break-words">
                                        <strong>{docente.cuenta.usuario}</strong>
                                        {docente.cuenta.correo ? ` · ${docente.cuenta.correo}` : ''}
                                        {docente.cuenta.estado === 'temporal'
                                            ? ' · contraseña temporal'
                                            : ' · contraseña propia'}
                                    </p>
                                </div>
                            ) : (
                                <FormularioActivar
                                    key={docente.id}
                                    docente={docente}
                                    onActivada={alActivar}
                                    onRechazo={alRechazar}
                                />
                            )}
                        </Tarjeta>
                    )}
                </div>
            </div>
            {aviso}
        </div>
    );
}

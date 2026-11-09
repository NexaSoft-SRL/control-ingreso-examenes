import PropTypes from 'prop-types';
import { useState } from 'react';
import { CircleCheck, Hourglass, ListChecks, LogIn, ScanLine } from 'lucide-react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { api } from '../../api/cliente';
import { estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import usarSondeo from '../../api/usarSondeo';
import { useAviso } from '../../componentes/Aviso';
import Buscador from '../../componentes/Buscador';
import Chips from '../../componentes/Chips';
import SinActividad from '../../componentes/SinActividad';
import Dato from '../../componentes/Dato';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import { BotonIncidencia, ListaIncidencias } from '../../componentes/Incidencias';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Tarjeta from '../../componentes/Tarjeta';
import { usarSesion } from '../../sesion/SesionContexto';
import { diaMes, plural } from '../../utiles/texto';

const INTERVALO = 15000;
const POR_PAGINA = 25;
const CONTROLAR =
    'inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// '2026-10-09T16:07:16-04:00' → '16:07:16'.
const horaDe = (instante) => String(instante ?? '').slice(11, 16);

// Solo las filas de «Ingresaron» traen `hora` y `expulsado`. Mientras el
// ingreso sigue abierto, quien no entró todavía no es ausente.
function condicionDe(fila, abierto) {
    if (!('hora' in fila)) return ['neutro', abierto ? 'Sin ingreso' : 'Ausente'];
    return fila.expulsado ? ['peligro', 'Expulsado'] : ['exito', 'Presente'];
}

// Cada aula que la cuenta puede seguir, con su examen (GET /api/en-curso).
const puestosDe = (examenes) =>
    examenes.flatMap((examen) => (examen.aulas ?? []).map((aula) => ({ examen, aula })));

// El puesto de `?examen=&aula=`; si falta o no es de la cuenta, el del
// mismo examen, el de un examen abierto o el primero.
function puestoDe(puestos, examenId, aulaId) {
    return (
        puestos.find((p) => p.examen.examen_id === examenId && p.aula.aula_id === aulaId) ||
        (examenId && puestos.find((p) => p.examen.examen_id === examenId)) ||
        (aulaId && puestos.find((p) => p.aula.aula_id === aulaId)) ||
        puestos.find((p) => p.examen.abierto) ||
        puestos[0] ||
        null
    );
}

// La lista de un aula: cifras, incidencias y estudiantes. Se monta de nuevo
// con cada aula (`key`), así el cambio limpia la búsqueda y la página.
function ListaDelAula({ examen, aula, esDocente, selector }) {
    const { usuario, puede } = usarSesion();
    const [aviso, avisar] = useAviso();
    const examenId = examen.examen_id;
    const lista = usarPaginaServidor({ porPagina: POR_PAGINA, filtros: { lista: 'faltan' } });
    const vivo = usarSondeo(`/examenes/${examenId}/aulas/${aula.aula_id}/asistencia`, {
        parametros: lista.parametros,
        intervalo: INTERVALO,
    });
    const cuerpo = vivo.cuerpo;
    const reportes = usarSondeo(`/examenes/${examenId}/incidencias`, {
        parametros: { aula: aula.aula_id },
        intervalo: INTERVALO,
        activa: cuerpo !== null,
    });

    const filas = vivo.datos ?? [];
    const cifras = cuerpo?.cifras;
    const abierto = cuerpo?.abierto ?? false;
    // Las incidencias solo se reportan el día del examen.
    const esHoy = cuerpo !== null && String(cuerpo.hora_servidor).slice(0, 10) === examen.fecha;
    const incidencias = reportes.datos ?? [];
    const sinAtender = incidencias.filter((i) => !i.atendida);
    const [soloSinAtender, setSoloSinAtender] = useState(true);
    const sinAlcance = estadoDe(vivo.error) === 403;

    const corregir = async (id, cuerpo) => {
        await api.post(`/incidencias/${id}/correccion`, cuerpo);
        avisar('Incidencia corregida');
        reportes.recargar();
    };

    const atender = async (id) => {
        try {
            await api.post(`/incidencias/${id}/atencion`);
            avisar('Incidencia atendida');
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo atender'), 'error');
        } finally {
            reportes.recargar();
        }
    };

    return (
        <>
            <Encabezado
                titulo={esDocente ? `Aula ${aula.nombre}` : `Mi aula · ${aula.nombre}`}
                subtitulo={
                    cuerpo
                        ? `${cuerpo.examen.tipo} · ${cuerpo.examen.asignatura} · ${String(examen.hora ?? '').slice(0, 5)}`
                        : examen.texto
                }
                volver={
                    esDocente ? { a: `/en-curso?examen=${examenId}`, texto: 'En curso' } : undefined
                }
                sinConexion={vivo.sinConexion}
            >
                {esHoy && (
                    <BotonIncidencia
                        aula={{ id: aula.aula_id, nombre: aula.nombre }}
                        por={usuario?.nombre}
                        hora={horaDe(cuerpo.hora_servidor)}
                        registrar={(datos) => api.post(`/examenes/${examenId}/incidencias`, datos)}
                        alRegistrar={reportes.recargar}
                    />
                )}
                {abierto && puede('punto_control') && (
                    <Link
                        to={`${esDocente ? '/control' : '/ingreso'}?aula=${aula.aula_id}`}
                        className={CONTROLAR}
                    >
                        <ScanLine className="h-4 w-4" /> Controlar
                    </Link>
                )}
            </Encabezado>

            {selector}

            {sinAlcance ? (
                <p
                    role="alert"
                    className="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-600"
                >
                    {mensajeDe(vivo.error, 'Sin acceso')}
                </p>
            ) : (
                <>
                    <div className="grid grid-cols-3 gap-3">
                        <Dato
                            etiqueta="Ingresaron"
                            icono={LogIn}
                            valor={cifras?.ingresaron ?? '—'}
                            tono="exito"
                        />
                        <Dato
                            etiqueta="Faltan"
                            icono={Hourglass}
                            valor={cifras?.faltan ?? '—'}
                            tono="advertencia"
                        />
                        <Dato
                            etiqueta="Habilitados"
                            icono={CircleCheck}
                            valor={cifras?.asignados ?? '—'}
                        />
                    </div>
                    {(cifras?.en_otra_aula > 0 || cifras?.de_otra_aula > 0) && (
                        <p className="text-sm text-slate-600">
                            {[
                                cifras.de_otra_aula > 0 &&
                                    `${plural(cifras.de_otra_aula, 'ingresó', 'ingresaron')} aquí desde otra aula`,
                                cifras.en_otra_aula > 0 &&
                                    `${plural(cifras.en_otra_aula, 'asignado entró', 'asignados entraron')} por otra aula`,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    )}

                    {incidencias.length > 0 && (
                        <Tarjeta
                            titulo={`Incidencias · ${incidencias.length}`}
                            sinRelleno
                            acciones={
                                <Chips
                                    etiqueta="Incidencias"
                                    valor={soloSinAtender}
                                    onCambiar={setSoloSinAtender}
                                    opciones={[
                                        {
                                            valor: true,
                                            etiqueta: 'Sin atender',
                                            conteo: sinAtender.length,
                                        },
                                        {
                                            valor: false,
                                            etiqueta: 'Todas',
                                            conteo: incidencias.length,
                                        },
                                    ]}
                                />
                            }
                        >
                            <ListaIncidencias
                                items={soloSinAtender ? sinAtender : incidencias}
                                atender={esDocente ? atender : undefined}
                                corregir={esDocente ? corregir : undefined}
                                limite={5}
                            />
                        </Tarjeta>
                    )}

                    <Chips
                        etiqueta="Lista"
                        valor={lista.filtros.lista}
                        onCambiar={(valor) => lista.ponerFiltro('lista', valor)}
                        opciones={[
                            { valor: 'faltan', etiqueta: 'Faltan', conteo: cifras?.faltan },
                            {
                                valor: 'ingresaron',
                                etiqueta: 'Ingresaron',
                                conteo: cifras?.ingresaron,
                            },
                        ]}
                    />

                    <Tarjeta sinRelleno>
                        <div className="border-b border-slate-200 p-3">
                            <Buscador
                                valor={lista.buscar}
                                onCambiar={lista.ponerBuscar}
                                placeholder="Nombre, código o documento"
                                etiqueta="Buscar estudiante"
                            />
                        </div>
                        <EstadoCarga
                            cargando={vivo.cargando}
                            error={vivo.error}
                            vacio={filas.length === 0}
                            onReintentar={vivo.recargar}
                        >
                            <ul className="divide-y divide-slate-200">
                                {filas.map((e) => {
                                    const [tono, condicion] = condicionDe(e, abierto);
                                    return (
                                        <li
                                            key={e.codigo}
                                            className="flex min-h-12 items-center gap-3 px-4 py-2"
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium text-slate-800">
                                                    {e.nombre}
                                                </span>
                                                <span className="block truncate text-xs text-slate-600">
                                                    <span className="font-mono">{e.codigo}</span> ·
                                                    Grupo {e.grupo}
                                                </span>
                                            </span>
                                            <span className="flex shrink-0 flex-col items-end gap-0.5">
                                                <Insignia tono={tono}>{condicion}</Insignia>
                                                {e.hora && (
                                                    <span className="text-xs tabular-nums text-slate-600">
                                                        {e.hora.slice(0, 5)}
                                                    </span>
                                                )}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                            <Paginacion
                                {...lista.paginacion(vivo.meta)}
                                unidad={['estudiante', 'estudiantes']}
                                className="border-t border-slate-200"
                            />
                        </EstadoCarga>
                    </Tarjeta>
                </>
            )}
            {aviso}
        </>
    );
}

ListaDelAula.propTypes = {
    examen: PropTypes.shape({
        examen_id: PropTypes.number.isRequired,
        texto: PropTypes.string,
        fecha: PropTypes.string,
        hora: PropTypes.string,
    }).isRequired,
    aula: PropTypes.shape({
        aula_id: PropTypes.number.isRequired,
        nombre: PropTypes.string.isRequired,
    }).isRequired,
    esDocente: PropTypes.bool,
    selector: PropTypes.node,
};

// Presentes, ausentes y expulsados de un aula, en vivo (HU-21). El auxiliar
// ve el aula donde lo asignaron (`/mi-aula`) y el docente llega desde «En
// curso» a cualquiera de las de su examen (`/en-curso/aula?examen=1&aula=31`).
// La lista se sondea cada 15 s y es de consulta: aquí no se registra ni se
// corrige ningún ingreso.
export default function Asistencia() {
    const { pathname } = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const esDocente = pathname.startsWith('/en-curso');
    const { datos, cargando, error, recargar } = usarConsulta('/en-curso');

    // Solo lo que se está tomando, como en «En curso»: lo ya rendido se ve en
    // los reportes y lo que viene, cuando abra.
    const puestos = puestosDe((datos ?? []).filter((e) => e.abierto));
    const puesto = puestoDe(
        puestos,
        Number(parametros.get('examen')) || null,
        Number(parametros.get('aula')) || null
    );

    if (!puesto) {
        return (
            <div className="space-y-5">
                <Encabezado
                    titulo="Lista del aula"
                    volver={esDocente ? { a: '/en-curso', texto: 'En curso' } : undefined}
                />
                <Tarjeta sinRelleno>
                    <EstadoCarga cargando={cargando} error={error} onReintentar={recargar}>
                        <SinActividad
                            Icono={ListChecks}
                            titulo="Ningún examen abierto en tus aulas"
                            detalle="La lista del aula se abre una hora antes del examen y se cierra al terminar."
                            conReportes
                        />
                    </EstadoCarga>
                </Tarjeta>
            </div>
        );
    }

    // El docente pasa de un aula a otra de su examen; el auxiliar, entre las
    // que atiende (puede tener más de un examen).
    const alcanzables = esDocente
        ? puestos.filter((p) => p.examen.examen_id === puesto.examen.examen_id)
        : puestos;
    const clave = (p) => `${p.examen.examen_id}-${p.aula.aula_id}`;
    const variosExamenes = new Set(alcanzables.map((p) => p.examen.examen_id)).size > 1;
    const ir = (valor) => {
        const [examenId, aulaId] = valor.split('-');
        setParametros(
            (previos) => {
                const siguiente = new URLSearchParams(previos);
                siguiente.set('examen', examenId);
                siguiente.set('aula', aulaId);
                return siguiente;
            },
            { replace: true }
        );
    };

    // Se pidió un aula que no es de la cuenta o no existe: se avisa en vez
    // de cambiar en silencio a otra.
    const pedidaAula = Number(parametros.get('aula')) || null;
    const pedidoExamen = Number(parametros.get('examen')) || null;
    const otraQueLaPedida =
        (pedidaAula !== null && puesto.aula.aula_id !== pedidaAula) ||
        (pedidoExamen !== null && puesto.examen.examen_id !== pedidoExamen);

    return (
        <div className="space-y-5">
            {otraQueLaPedida && (
                <p
                    role="status"
                    className="rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800"
                >
                    Aula pedida no disponible · Aula {puesto.aula.nombre}
                </p>
            )}
            <ListaDelAula
                key={clave(puesto)}
                examen={puesto.examen}
                aula={puesto.aula}
                esDocente={esDocente}
                selector={
                    alcanzables.length > 1 && (
                        <Chips
                            etiqueta="Aula"
                            valor={clave(puesto)}
                            onCambiar={ir}
                            opciones={alcanzables.map((p) => ({
                                valor: clave(p),
                                etiqueta: variosExamenes
                                    ? `${p.aula.nombre} · ${diaMes(p.examen.fecha)} ${p.examen.hora} · ${p.examen.asignatura}`
                                    : p.aula.nombre,
                            }))}
                        />
                    )
                }
            />
        </div>
    );
}

import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { CircleCheck, Hourglass, LogIn, ScanLine } from 'lucide-react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { api } from '../../api/cliente';
import { estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarSondeo from '../../api/usarSondeo';
import Buscador from '../../componentes/Buscador';
import Chips from '../../componentes/Chips';
import Dato from '../../componentes/Dato';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import { BotonIncidencia } from '../../componentes/Incidencias';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Tarjeta from '../../componentes/Tarjeta';
import { usarSesion } from '../../sesion/SesionContexto';
import { diaMes, plural } from '../../utiles/texto';
import usarTituloPestana from '../../utiles/usarTituloPestana';
import IncidenciasDelExamen from '../monitoreo/IncidenciasDelExamen';

const INTERVALO = 15000;
// La búsqueda viaja al servidor 300 ms después de la última tecla.
const ESPERA_BUSQUEDA = 300;
const CONTROLAR =
    'inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

const TIPOS = {
    PRIMER_PARCIAL: 'Primer parcial',
    SEGUNDO_PARCIAL: 'Segundo parcial',
    FINAL: 'Examen final',
    SEGUNDA_INSTANCIA: 'Segunda instancia',
    MESA: 'Examen de mesa',
};

// '2026-10-09T16:07:16-04:00' o '2026-10-09 16:07:16' → '16:07'.
const horaDe = (instante) => String(instante ?? '').slice(11, 16);

// Mientras el ingreso sigue abierto, quien no entró todavía no es ausente.
function condicionDe(fila, abierto) {
    if (!fila.registrado_en) return ['neutro', abierto ? 'Sin ingreso' : 'Ausente'];
    return fila.expulsado_en ? ['peligro', 'Expulsado'] : ['exito', 'Presente'];
}

// La lista de un aula: cifras, incidencias y estudiantes. Se monta de nuevo
// con cada aula (`key`), así el cambio limpia la búsqueda y la página.
function ListaDelAula({ puesto, esDocente, selector }) {
    const { usuario, puede } = usarSesion();
    const [lista, setLista] = useState('faltan');
    const [buscar, setBuscar] = useState('');
    const [buscado, setBuscado] = useState('');
    const [pagina, setPagina] = useState(1);
    const [reportadas, setReportadas] = useState(0);

    useEffect(() => {
        const texto = buscar.trim();
        if (texto === buscado) return undefined;
        const reloj = setTimeout(() => {
            setBuscado(texto);
            setPagina(1);
        }, ESPERA_BUSQUEDA);
        return () => clearTimeout(reloj);
    }, [buscar, buscado]);

    const vivo = usarSondeo(`/seguimiento/examenes/${puesto.examen_id}/aulas/${puesto.aula_id}`, {
        parametros: { estado: lista, buscar: buscado, pagina },
        intervalo: INTERVALO,
    });
    const detalle = vivo.datos;
    const cifras = detalle?.cifras;
    const abierto = Boolean(detalle?.abierto);
    const filas = detalle?.estudiantes ?? [];
    // Las incidencias solo se reportan el día del examen.
    const esHoy =
        Boolean(detalle) &&
        String(detalle.examen.hora_servidor).slice(0, 10) === detalle.examen.fecha;
    const sinAlcance = [403, 404].includes(estadoDe(vivo.error));
    const operaPuerta = Boolean(detalle?.puede_operar) && puede('punto_control');
    const tipo = TIPOS[puesto.tipo] ?? puesto.tipo;

    return (
        <>
            <Encabezado
                titulo={esDocente ? `Aula ${puesto.aula}` : `Mi aula · ${puesto.aula}`}
                subtitulo={`${tipo} · ${puesto.asignatura} · ${puesto.hora}`}
                volver={
                    esDocente
                        ? { a: `/en-curso?examen=${puesto.examen_id}`, texto: 'Volver a En curso' }
                        : undefined
                }
                sinConexion={vivo.sinConexion}
            >
                {esHoy && (
                    <BotonIncidencia
                        aula={{ id: puesto.aula_id, nombre: puesto.aula }}
                        por={usuario?.nombre}
                        hora={horaDe(detalle.examen.hora_servidor)}
                        registrar={(datos) =>
                            api.post(`/examenes/${puesto.examen_id}/incidencias`, datos)
                        }
                        alRegistrar={() => {
                            setReportadas((n) => n + 1);
                            vivo.recargar();
                        }}
                    />
                )}
                {abierto && operaPuerta && (
                    <Link to={`/punto-control?aula=${puesto.aula_id}`} className={CONTROLAR}>
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

                    {detalle && (
                        <IncidenciasDelExamen
                            key={reportadas}
                            examenId={puesto.examen_id}
                            aulaId={puesto.aula_id}
                            puedeAtender={Boolean(detalle.puede_atender)}
                        />
                    )}

                    <Chips
                        etiqueta="Lista"
                        valor={lista}
                        onCambiar={(valor) => {
                            setLista(valor);
                            setPagina(1);
                        }}
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
                                valor={buscar}
                                onCambiar={setBuscar}
                                placeholder="Nombre, código o documento"
                                etiqueta="Buscar estudiante"
                            />
                        </div>
                        <EstadoCarga
                            cargando={vivo.cargando}
                            error={vivo.error}
                            vacio={filas.length === 0}
                            textoVacio={buscado ? 'Sin resultados' : 'Sin estudiantes'}
                            onReintentar={vivo.recargar}
                        >
                            <ul
                                aria-label={lista === 'faltan' ? 'Faltan' : 'Ingresaron'}
                                className="divide-y divide-slate-200"
                            >
                                {filas.map((e) => {
                                    const [tono, condicion] = condicionDe(e, abierto);
                                    return (
                                        <li
                                            key={e.estudiante_id}
                                            className="flex min-h-12 items-center gap-3 px-4 py-2"
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium text-slate-800">
                                                    {e.apellidos} {e.nombres}
                                                </span>
                                                <span className="block truncate text-xs text-slate-600">
                                                    <span className="font-mono">{e.codigo}</span>
                                                    {e.grupos?.length
                                                        ? ` · ${e.grupos.join(', ')}`
                                                        : ''}
                                                </span>
                                            </span>
                                            <span className="flex shrink-0 flex-col items-end gap-0.5">
                                                <Insignia tono={tono}>{condicion}</Insignia>
                                                {e.registrado_en && (
                                                    <span className="text-xs tabular-nums text-slate-600">
                                                        {horaDe(e.registrado_en)}
                                                    </span>
                                                )}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                            <Paginacion
                                total={detalle?.pagina?.total ?? filas.length}
                                pagina={detalle?.pagina?.actual ?? pagina}
                                porPagina={detalle?.pagina?.por_pagina ?? 25}
                                onCambiar={setPagina}
                                unidad={['estudiante', 'estudiantes']}
                                className="border-t border-slate-200"
                            />
                        </EstadoCarga>
                    </Tarjeta>
                </>
            )}
        </>
    );
}

const formaPuesto = PropTypes.shape({
    examen_id: PropTypes.number.isRequired,
    aula_id: PropTypes.number.isRequired,
    aula: PropTypes.string,
    fecha: PropTypes.string,
    hora: PropTypes.string,
    tipo: PropTypes.string,
    asignatura: PropTypes.string,
});

ListaDelAula.propTypes = {
    puesto: formaPuesto.isRequired,
    esDocente: PropTypes.bool,
    selector: PropTypes.node,
};

// Presentes y los que faltan de un aula, en vivo (HU-21). El auxiliar ve
// las aulas donde lo asignaron (`/mi-aula`) y el docente llega desde «En
// curso» a cualquiera de las de su examen (`/en-curso/aula?examen=1&aula=31`).
// La lista se sondea cada 15 s y es de consulta: aquí no se registra ni se
// corrige ningún ingreso.
export default function Seguimiento() {
    const { pathname } = useLocation();
    const esDocente = pathname.startsWith('/en-curso');
    usarTituloPestana(esDocente ? 'Lista del aula' : 'Mi aula');
    const [parametros, setParametros] = useSearchParams();
    const { datos, cargando, error, recargar } = usarConsulta('/seguimiento/aulas');
    const puestos = Array.isArray(datos) ? datos : [];

    const pedidoExamen = Number(parametros.get('examen')) || null;
    const pedidaAula = Number(parametros.get('aula')) || null;
    const puesto =
        puestos.find((p) => p.examen_id === pedidoExamen && p.aula_id === pedidaAula) ??
        (pedidoExamen ? puestos.find((p) => p.examen_id === pedidoExamen) : null) ??
        puestos[0] ??
        null;

    if (!puesto) {
        return (
            <div className="space-y-5">
                <Encabezado
                    titulo={esDocente ? 'Lista del aula' : 'Mi aula'}
                    volver={esDocente ? { a: '/en-curso', texto: 'Volver a En curso' } : undefined}
                />
                <Tarjeta sinRelleno>
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        vacio={puestos.length === 0}
                        textoVacio="Sin aula asignada"
                        onReintentar={recargar}
                    />
                </Tarjeta>
            </div>
        );
    }

    // El docente pasa de un aula a otra de su examen; el auxiliar, entre las
    // que atiende (puede tener más de un examen).
    const alcanzables = esDocente
        ? puestos.filter((p) => p.examen_id === puesto.examen_id)
        : puestos;
    const clave = (p) => `${p.examen_id}-${p.aula_id}`;
    const variosExamenes = new Set(alcanzables.map((p) => p.examen_id)).size > 1;
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

    // Se pidió un aula que no es de la cuenta o no es de hoy: se avisa en
    // vez de cambiar en silencio a otra.
    const otraQueLaPedida =
        (pedidaAula !== null && puesto.aula_id !== pedidaAula) ||
        (pedidoExamen !== null && puesto.examen_id !== pedidoExamen);

    return (
        <div className="space-y-5">
            {otraQueLaPedida && (
                <p
                    role="status"
                    className="rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800"
                >
                    Aula pedida no disponible · Aula {puesto.aula}
                </p>
            )}
            <ListaDelAula
                key={clave(puesto)}
                puesto={puesto}
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
                                    ? `${p.aula} · ${diaMes(p.fecha)} ${p.hora} · ${p.asignatura}`
                                    : p.aula,
                            }))}
                        />
                    )
                }
            />
        </div>
    );
}

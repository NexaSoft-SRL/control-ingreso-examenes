import React from 'react';
import PropTypes from 'prop-types';

// Datos temporales para construir la pantalla antes de integrar los endpoints.
const EXAMENES_EJEMPLO = [
    {
        id: 1,
        asignatura: 'Cálculo III',
        codigoGrupo: 'MAT-207 · grupo A',
        tipo: 'Segundo parcial',
        fecha: '2026-10-17',
        hora: '07:00',
        duracion: '90 min',
        docente: 'Peredo Antezana, Rosa',
    },
    {
        id: 2,
        asignatura: 'Base de Datos I',
        codigoGrupo: 'INF-271 · grupo A',
        tipo: 'Primer parcial',
        fecha: '2026-10-16',
        hora: '14:00',
        duracion: '120 min',
        docente: 'Camacho Rojas, Iván',
    },
    {
        id: 3,
        asignatura: 'Redes de Computadoras',
        codigoGrupo: 'INF-342 · grupo A',
        tipo: 'Primer parcial',
        fecha: '2026-10-15',
        hora: '08:30',
        duracion: '90 min',
        docente: 'Quiroga Vargas, Marcela',
    },
];

const NORMAS_EJEMPLO = {
    1: [
        {
            id: 1,
            alcance: 'general',
            texto: 'Prohibido el uso de calculadora programable y de teléfono celular.',
        },
        {
            id: 2,
            alcance: 'particular',
            texto: 'Rinde en sala aparte, con treinta minutos adicionales.',
            estudiante: 'Alvarado Claros, Kevin René',
            codigoUniversitario: '202104821',
            motivo: 'Certificado médico presentado en secretaría académica.',
        },
    ],
    2: [
        {
            id: 3,
            alcance: 'general',
            texto: 'Se permite una hoja de fórmulas escrita a mano.',
        },
    ],
    3: [],
};

function NormaItem({ norma }) {
    const particular = norma.alcance === 'particular';

    return (
        <li className="flex items-start justify-between gap-4 border-b border-slate-100 py-4 last:border-b-0">
            <div className="min-w-0">
                <span
                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                        particular ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'
                    }`}
                >
                    {particular ? 'Particular' : 'General'}
                </span>
                <p className="mt-2 text-sm font-medium text-slate-800">{norma.texto}</p>
                {particular && (
                    <>
                        <p className="mt-1 text-xs text-slate-600">
                            {norma.estudiante} · {norma.codigoUniversitario}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">Motivo: {norma.motivo}</p>
                    </>
                )}
            </div>
        </li>
    );
}

NormaItem.propTypes = {
    norma: PropTypes.shape({
        alcance: PropTypes.oneOf(['general', 'particular']).isRequired,
        texto: PropTypes.string.isRequired,
        estudiante: PropTypes.string,
        codigoUniversitario: PropTypes.string,
        motivo: PropTypes.string,
    }).isRequired,
};

function ExamenesNormas() {
    const [examenSeleccionado, setExamenSeleccionado] = React.useState(EXAMENES_EJEMPLO[0].id);

    const examen = EXAMENES_EJEMPLO.find((item) => item.id === examenSeleccionado);
    const normas = [...(NORMAS_EJEMPLO[examenSeleccionado] ?? [])].sort(
        (a, b) => Number(a.alcance !== 'general') - Number(b.alcance !== 'general')
    );

    return (
        <section className="min-w-0 p-4 sm:p-8">
            <header className="mb-6">
                <h1 className="text-2xl font-bold text-slate-900">Exámenes</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Evaluaciones registradas por grupo de asignatura
                </p>
            </header>

            <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-semibold">
                                Asignatura
                            </th>
                            <th scope="col" className="px-4 py-3 font-semibold">
                                Tipo
                            </th>
                            <th scope="col" className="px-4 py-3 font-semibold">
                                Fecha
                            </th>
                            <th scope="col" className="px-4 py-3 font-semibold">
                                Hora
                            </th>
                            <th scope="col" className="px-4 py-3 font-semibold">
                                Docente
                            </th>
                            <th scope="col" className="px-4 py-3 font-semibold">
                                Acción
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {EXAMENES_EJEMPLO.map((item) => (
                            <tr key={item.id} className="text-slate-700">
                                <td className="px-4 py-4">
                                    <p className="font-semibold text-slate-800">
                                        {item.asignatura}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {item.codigoGrupo}
                                    </p>
                                </td>
                                <td className="px-4 py-4">{item.tipo}</td>
                                <td className="px-4 py-4">{item.fecha}</td>
                                <td className="px-4 py-4">
                                    <p>{item.hora}</p>
                                    <p className="mt-1 text-xs text-slate-500">{item.duracion}</p>
                                </td>
                                <td className="px-4 py-4">{item.docente}</td>
                                <td className="px-4 py-4">
                                    <button
                                        type="button"
                                        aria-label={`Normas de ${item.asignatura}`}
                                        aria-pressed={examenSeleccionado === item.id}
                                        className={`font-medium ${
                                            examenSeleccionado === item.id
                                                ? 'text-blue-800'
                                                : 'text-blue-600 hover:text-blue-800'
                                        }`}
                                        onClick={() => setExamenSeleccionado(item.id)}
                                    >
                                        Normas
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {examen && (
                <section
                    aria-labelledby="normas-titulo"
                    className="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h2 id="normas-titulo" className="text-lg font-bold text-slate-900">
                                Normas del examen
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                {examen.asignatura} · {examen.tipo} · {examen.codigoGrupo}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                Las generales aplican a todo el examen; las particulares nombran a
                                un estudiante y explican el motivo.
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-4">
                            <span className="text-xs font-medium text-slate-500">
                                {normas.length} {normas.length === 1 ? 'norma' : 'normas'}
                            </span>
                            <button
                                type="button"
                                className="text-sm font-medium text-slate-500 hover:text-slate-800"
                                onClick={() => setExamenSeleccionado(null)}
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>

                    {normas.length > 0 ? (
                        <ol className="divide-y divide-slate-100">
                            {normas.map((norma) => (
                                <NormaItem key={norma.id} norma={norma} />
                            ))}
                        </ol>
                    ) : (
                        <p className="py-6 text-sm text-slate-500">
                            Este examen todavía no tiene normas registradas.
                        </p>
                    )}
                </section>
            )}
        </section>
    );
}

export default ExamenesNormas;

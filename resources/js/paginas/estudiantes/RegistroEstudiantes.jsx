import React, { useState } from 'react';

const mockEstudiantesIniciales = [
    {
        id: 1,
        codigo: '282104821',
        ci: '7928194-CBB',
        nombre: 'Alvarado Claros, Kevin René',
        carrera: 'Ingeniería de Sistemas',
        estado: 'ACTIVO',
    },
    {
        id: 2,
        codigo: '202008472',
        ci: '8839210-CBB',
        nombre: 'Bustamante Torrico, Valeria',
        carrera: 'Ingeniería Informática',
        estado: 'ACTIVO',
    },
    {
        id: 3,
        codigo: '281901349',
        ci: '6492819-LPZ',
        nombre: 'Camacho Zeballos, Diego Andrés',
        carrera: 'Ingeniería de Sistemas',
        estado: 'ACTIVO',
    },
    {
        id: 4,
        codigo: '282201994',
        ci: '9348122-CBB',
        nombre: 'Fernández Rojas, Mariana Lucía',
        carrera: 'Ingeniería Electrónica',
        estado: 'ACTIVO',
    },
];

export default function RegistroEstudiantes() {
    const [estudiantes, setEstudiantes] = useState(mockEstudiantesIniciales);

    // Estados para el formulario y edición
    const [mostrarModal, setMostrarModal] = useState(false);
    const [estudianteEditando, setEstudianteEditando] = useState(null);
    const [codigo, setCodigo] = useState('');
    const [ci, setCi] = useState('');
    const [nombre, setNombre] = useState('');
    const [carrera, setCarrera] = useState('');
    const [estadoEstudiante, setEstadoEstudiante] = useState('ACTIVO');

    const abrirNuevoEstudiante = () => {
        setEstudianteEditando(null);
        setCodigo('');
        setCi('');
        setNombre('');
        setCarrera('');
        setEstadoEstudiante('ACTIVO');
        setMostrarModal(true);
    };

    const abrirEditarEstudiante = (index) => {
        const est = estudiantes[index];
        setEstudianteEditando(index);
        setCodigo(est.codigo);
        setCi(est.ci);
        setNombre(est.nombre);
        setCarrera(est.carrera);
        setEstadoEstudiante(est.estado || 'ACTIVO');
        setMostrarModal(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!codigo || !ci || !nombre || !carrera) return;

        if (estudianteEditando === null) {
            // Crear nuevo
            const nuevo = {
                id: estudiantes.length + 1,
                codigo,
                ci,
                nombre,
                carrera,
                estado: estadoEstudiante,
            };
            setEstudiantes([...estudiantes, nuevo]);
        } else {
            // Actualizar existente
            const actualizados = [...estudiantes];
            actualizados[estudianteEditando] = {
                ...actualizados[estudianteEditando],
                codigo,
                ci,
                nombre,
                carrera,
                estado: estadoEstudiante,
            };
            setEstudiantes(actualizados);
            setEstudianteEditando(null);
        }

        setCodigo('');
        setCi('');
        setNombre('');
        setCarrera('');
        setEstadoEstudiante('ACTIVO');
        setMostrarModal(false);
    };

    return (
        <div className="p-8">
            {/* Cabecera Padrón */}
            <div className="flex justify-between items-center mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-800">Padrón</h1>
                    <p className="text-sm text-gray-500">Gestión del padrón estudiantil</p>
                </div>
                <button
                    onClick={abrirNuevoEstudiante}
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition flex items-center gap-2"
                >
                    + Nuevo estudiante
                </button>
            </div>

            {/* Caja de Carga CSV (Mockup) */}
            <div className="bg-white border-2 border-dashed border-blue-200 rounded-xl p-6 mb-6 flex justify-between items-center shadow-sm">
                <div className="flex items-center gap-4">
                    <div className="bg-blue-50 p-3 rounded-lg text-blue-600">
                        <svg
                            className="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                            />
                        </svg>
                    </div>
                    <div>
                        <p className="text-sm font-medium text-gray-700">
                            Arrastrá tu archivo CSV o hacé clic para subir
                        </p>
                        <p className="text-xs text-gray-400">
                            Formato delimitado por comas con codificación UTF-8
                        </p>
                    </div>
                </div>
                <button className="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-medium">
                    Cargar
                </button>
            </div>

            {/* Alerta de errores de carga (Mockup) */}
            <div className="bg-amber-50 border-l-4 border-amber-400 p-4 mb-6 rounded-r-lg flex items-center justify-between shadow-sm">
                <div className="flex items-center gap-2 text-amber-800 text-sm font-medium">
                    <span>⚠️</span>
                    <span>
                        3 registros no se pudieron cargar{' '}
                        <a href="#detalle" className="underline font-bold hover:text-amber-900">
                            (ver detalle)
                        </a>
                    </span>
                </div>
            </div>

            {/* Formulario desplegable para "+ Nuevo estudiante" o "Editar" */}
            {mostrarModal && (
                <form
                    onSubmit={handleSubmit}
                    className="bg-white p-6 rounded-xl shadow-md mb-6 border border-blue-100"
                >
                    <h2 className="text-lg font-bold mb-4 text-gray-700">
                        {estudianteEditando === null
                            ? 'Registrar nuevo estudiante'
                            : 'Editar estudiante'}
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                CÓDIGO
                            </label>
                            <input
                                type="text"
                                value={codigo}
                                onChange={(e) => setCodigo(e.target.value)}
                                placeholder="Ej. 282104821"
                                className="w-full p-2 border rounded text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                C.I.
                            </label>
                            <input
                                type="text"
                                value={ci}
                                onChange={(e) => setCi(e.target.value)}
                                placeholder="Ej. 7928194-CBB"
                                className="w-full p-2 border rounded text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                NOMBRE (Apellido, Nombre)
                            </label>
                            <input
                                type="text"
                                value={nombre}
                                onChange={(e) => setNombre(e.target.value)}
                                placeholder="Ej. Pérez, Juan"
                                className="w-full p-2 border rounded text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                CARRERA
                            </label>
                            <input
                                type="text"
                                value={carrera}
                                onChange={(e) => setCarrera(e.target.value)}
                                placeholder="Ej. Ingeniería de Sistemas"
                                className="w-full p-2 border rounded text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">
                                ESTADO
                            </label>
                            <select
                                value={estadoEstudiante}
                                onChange={(e) => setEstadoEstudiante(e.target.value)}
                                className="w-full p-2 border rounded text-sm bg-white"
                            >
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                                <option value="SUSPENDIDO">SUSPENDIDO</option>
                            </select>
                        </div>
                    </div>
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setMostrarModal(false)}
                            className="px-4 py-2 border rounded text-sm text-gray-600 hover:bg-gray-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 font-medium"
                        >
                            {estudianteEditando === null ? 'Guardar Estudiante' : 'Guardar Cambios'}
                        </button>
                    </div>
                </form>
            )}

            {/* Tabla Estilo Mockup */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table className="w-full text-left border-collapse">
                    <thead className="bg-gray-50 text-gray-400 text-xs uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th className="p-4 font-semibold">Código</th>
                            <th className="p-4 font-semibold">C.I.</th>
                            <th className="p-4 font-semibold">Nombre</th>
                            <th className="p-4 font-semibold">Carrera</th>
                            <th className="p-4 font-semibold">Estado</th>
                            <th className="p-4 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm text-gray-600">
                        {estudiantes.map((est, index) => (
                            <tr key={est.id} className="hover:bg-gray-50 transition">
                                <td className="p-4 font-medium text-gray-700">{est.codigo}</td>
                                <td className="p-4">{est.ci}</td>
                                <td className="p-4 font-medium text-gray-900">{est.nombre}</td>
                                <td className="p-4">{est.carrera}</td>
                                <td className="p-4">
                                    <span
                                        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${
                                            est.estado === 'INACTIVO'
                                                ? 'bg-gray-100 text-gray-600'
                                                : est.estado === 'SUSPENDIDO'
                                                  ? 'bg-red-50 text-red-600'
                                                  : 'bg-green-50 text-green-600'
                                        }`}
                                    >
                                        <span
                                            className={`w-1.5 h-1.5 rounded-full ${
                                                est.estado === 'INACTIVO'
                                                    ? 'bg-gray-400'
                                                    : est.estado === 'SUSPENDIDO'
                                                      ? 'bg-red-500'
                                                      : 'bg-green-500'
                                            }`}
                                        ></span>{' '}
                                        {est.estado || 'ACTIVO'}
                                    </span>
                                </td>
                                <td className="p-4 text-right">
                                    <button
                                        onClick={() => abrirEditarEstudiante(index)}
                                        className="text-blue-600 hover:text-blue-800 font-medium text-sm"
                                    >
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="p-4 bg-gray-50 text-xs text-gray-400 border-t border-gray-100">
                    Mostrando {estudiantes.length} de 145 estudiantes
                </div>
            </div>
        </div>
    );
}

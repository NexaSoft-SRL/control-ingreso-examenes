import React, { useState } from 'react';
import AsignaturasAmbientes from '../paginas/admin/AsignaturasAmbientes';
import RegistroEstudiantes from '../paginas/estudiantes/RegistroEstudiantes';

export default function Aplicacion() {
    // Estado para controlar qué vista se muestra ('asignaturas' o 'estudiantes')
    const [vistaActiva, setVistaActiva] = useState('asignaturas');

    return (
        <div className="flex min-h-screen min-w-0 flex-col bg-gray-50 font-sans text-gray-900 md:flex-row">
            {/* 1. MENÚ LATERAL FIJO */}
            <aside className="hidden w-64 shrink-0 flex-col border-r border-gray-200 bg-white md:flex">
                <div className="h-16 flex items-center gap-3 px-6 border-b border-gray-200">
                    <div className="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        ✓
                    </div>
                    <div>
                        <div className="text-xs font-bold text-gray-800">UMSS FCyT</div>
                        <div className="text-[9px] tracking-wider text-gray-400 font-semibold">
                            CONTROL DE INGRESO
                        </div>
                    </div>
                </div>

                <div className="p-4 flex-1">
                    <div className="text-[10px] font-bold text-gray-400 tracking-wider px-3 mb-2">
                        ADMINISTRADOR
                    </div>

                    {/* Botón Padrón */}
                    <button
                        onClick={() => setVistaActiva('estudiantes')}
                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition ${
                            vistaActiva === 'estudiantes'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-100'
                        }`}
                    >
                        <span>♙</span> Padrón
                    </button>

                    {/* Botón Asignaturas y ambientes */}
                    <button
                        onClick={() => setVistaActiva('asignaturas')}
                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition mt-1 ${
                            vistaActiva === 'asignaturas'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-100'
                        }`}
                    >
                        <span>▤</span> Asignaturas y ambientes
                    </button>

                    {/* Resto de opciones con su funcionalidad original */}
                    <button
                        onClick={() => alert('Códigos QR: próximamente')}
                        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition mt-1"
                    >
                        <span>#</span> Códigos QR
                    </button>

                    <button
                        onClick={() => alert('Usuarios y roles: próximamente')}
                        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition mt-1"
                    >
                        <span>👤</span> Usuarios y roles
                    </button>

                    <button
                        onClick={() => alert('Bitácora: próximamente')}
                        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition mt-1"
                    >
                        <span>📋</span> Bitácora
                    </button>

                    <button
                        onClick={() => alert('Respaldo: próximamente')}
                        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition mt-1"
                    >
                        <span>🔄</span> Respaldo
                    </button>
                </div>
            </aside>

            {/* 2. CONTENIDO DINÁMICO DE LA DERECHA */}
            <main className="flex-1 flex flex-col min-w-0">
                {/* Cabecera superior común */}
                <nav className="flex items-center justify-start gap-3 border-b border-gray-200 bg-white px-4 py-3 md:hidden">
                    <span className="text-xs font-semibold text-gray-500">Sección</span>
                    <select
                        value={vistaActiva}
                        onChange={(e) => {
                            const opcion = e.target.value;
                            if (opcion === 'estudiantes' || opcion === 'asignaturas') {
                                setVistaActiva(opcion);
                            } else {
                                alert(`${e.target.options[e.target.selectedIndex].text}: próximamente`);
                            }
                        }}
                        className="max-w-[75%] rounded-lg border border-blue-200 bg-white px-3 py-2 text-right text-xs font-medium text-gray-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        aria-label="Seleccionar sección"
                    >
                        <option value="estudiantes">Padrón</option>
                        <option value="asignaturas">Asignaturas y ambientes</option>
                        <option value="qr">Códigos QR</option>
                        <option value="usuarios">Usuarios y roles</option>
                        <option value="bitacora">Bitácora</option>
                        <option value="respaldo">Respaldo</option>
                    </select>
                </nav>

                <header className="flex min-h-16 items-center justify-between border-b border-gray-200 bg-white px-4 py-3 sm:px-8">
                    <div className="flex min-w-0 items-center gap-2 text-xs font-medium text-gray-700">
                        <span className="text-blue-600 text-lg">◇</span> Sistema Institucional de
                        Verificación
                    </div>
                    <div className="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                        ●
                    </div>
                </header>

                {/* Renderizado condicional de las vistas */}
                <div className="min-w-0 flex-1 overflow-y-auto">
                    {vistaActiva === 'asignaturas' && <AsignaturasAmbientes />}
                    {vistaActiva === 'estudiantes' && <RegistroEstudiantes />}
                </div>
            </main>
        </div>
    );
}

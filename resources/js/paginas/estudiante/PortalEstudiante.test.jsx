import { fireEvent, screen, waitFor } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import PortalEstudiante from './PortalEstudiante';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

beforeAll(() => {
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:foto');
    globalThis.URL.revokeObjectURL = vi.fn();
});

const ESTUDIANTE = { id: 7, codigo: '202100417', nombre: 'Diego Mamani', con_foto: false };
const PLAZO = { desde: '2026-10-01', hasta: '2026-10-31', abierto: true };

const EXAMENES = [
    {
        id: 1,
        asignatura: 'Introducción a la Programación',
        tipo: 'Primer parcial',
        fecha: '2026-10-22',
        hora: '08:15',
        estado: 'HABILITADO',
        normas: ['Presentar carnet universitario.', 'No usar celular.'],
        aula: '691A',
        edificio: 'Edificio Académico 2',
        piso: '1° Piso',
    },
    {
        id: 2,
        asignatura: 'Cálculo I',
        tipo: 'Segundo parcial',
        fecha: '2026-11-05',
        hora: '10:00',
        estado: 'NO_HABILITADO',
        motivo: 'Debe la práctica 3.',
        aula: null,
    },
];

function Donde() {
    return <p>Estoy en {useLocation().pathname}</p>;
}

function montar(ruta = '/estudiante', rutas = {}) {
    simularApi(api, {
        'GET /estudiante/sesion': { estudiante: ESTUDIANTE, plazo_foto: PLAZO },
        'GET /estudiante/examenes': { data: EXAMENES, hoy: '2026-10-22' },
        'DELETE /auth/estudiante': null,
        ...rutas,
    });
    return renderConSesion(
        <Routes>
            <Route path="/estudiante" element={<PortalEstudiante />} />
            <Route path="/estudiante/examenes" element={<PortalEstudiante />} />
            <Route path="/estudiante/foto" element={<PortalEstudiante />} />
            <Route path="*" element={<Donde />} />
        </Routes>,
        { usuario: null, ruta }
    );
}

describe('PortalEstudiante', () => {
    it('sin sesión vuelve al acceso', async () => {
        montar('/estudiante', { 'GET /estudiante/sesion': errorHttp(401) });
        expect(await screen.findByText('Estoy en /estudiante/ingreso')).toBeInTheDocument();
    });

    it('muestra cada examen con su hora, su aula y si está habilitado', async () => {
        montar();

        expect(await screen.findByRole('heading', { name: 'Diego Mamani' })).toBeInTheDocument();
        expect(await screen.findByText('Aula 691A')).toBeInTheDocument();
        expect(screen.getByText('Hoy')).toBeInTheDocument();
        expect(screen.getByText('Habilitado')).toBeInTheDocument();
        expect(screen.getByText('No habilitado')).toBeInTheDocument();
        expect(screen.getByText('Debe la práctica 3.')).toBeInTheDocument();
        expect(screen.getByText('Edificio Académico 2 · 1° Piso')).toBeInTheDocument();
        expect(screen.getByText('Normas del examen')).toBeInTheDocument();
        expect(screen.getByText('No usar celular.')).toBeInTheDocument();
    });

    it('sube la foto dentro del plazo', async () => {
        montar('/estudiante/foto', { 'POST /estudiante/foto': { message: 'Foto registrada.' } });

        await screen.findByRole('button', { name: 'Guardar foto' });
        const foto = new File(['jpeg'], 'yo.jpg', { type: 'image/jpeg' });
        fireEvent.change(screen.getByLabelText('Foto'), { target: { files: [foto] } });
        await screen.findByRole('img', { name: 'Foto' });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar foto' }));
        expect(api.post).not.toHaveBeenCalled();
        expect(screen.getByText('Después no se puede cambiar')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Confirmar' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/estudiante/foto', expect.any(FormData))
        );
        expect(api.post.mock.calls[0][1].get('foto')).toBe(foto);
        expect(await screen.findByText('Foto registrada')).toBeInTheDocument();
    });

    it('rechaza una foto mayor a 8 MB antes de intentar subirla', async () => {
        montar('/estudiante/foto');
        await screen.findByRole('button', { name: 'Guardar foto' });
        const demasiadoGrande = new File([new Uint8Array(8 * 1024 * 1024 + 1)], 'foto-grande.jpg', {
            type: 'image/jpeg',
        });

        fireEvent.change(screen.getByLabelText('Foto'), {
            target: { files: [demasiadoGrande] },
        });

        expect(await screen.findByRole('alert')).toHaveTextContent('Máximo 8 MB');
        expect(api.post).not.toHaveBeenCalled();
    });

    it('con foto la muestra y no deja cambiarla', async () => {
        montar('/estudiante/foto', {
            'GET /estudiante/sesion': {
                estudiante: { ...ESTUDIANTE, con_foto: true },
                plazo_foto: PLAZO,
            },
            'GET /estudiante/foto': new Blob(['jpeg']),
        });

        expect(await screen.findByRole('img', { name: 'Mi foto' })).toHaveAttribute(
            'src',
            'blob:foto'
        );
        expect(screen.getByText('Registrada')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Guardar foto' })).not.toBeInTheDocument();
    });

    it('con el general cerrado sube con el de su grupo y avisa dónde ya venció', async () => {
        montar('/estudiante/foto', {
            'GET /estudiante/sesion': {
                estudiante: ESTUDIANTE,
                plazo_foto: { ...PLAZO, abierto: false },
                plazos_foto_grupo: [
                    {
                        id: 4,
                        asignatura: 'Introducción a la Programación',
                        grupo: '2',
                        desde: '2026-11-01',
                        hasta: '2026-11-15',
                    },
                ],
                foto: {
                    puede_subir: true,
                    habilitada_hasta: null,
                    habilitada_por: null,
                    vencido_en: ['Física'],
                },
            },
        });

        expect(
            await screen.findByText(
                'Plazo · Introducción a la Programación · 2: 1 nov – 15 nov 2026'
            )
        ).toBeInTheDocument();
        expect(screen.getByRole('alert')).toHaveTextContent('Sin foto · no habilitado en Física');
        expect(screen.getByText('Sin lentes ni gorra')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Guardar foto' })).toBeInTheDocument();
    });

    it('con el cambio habilitado por un docente muestra la actual y deja reemplazarla', async () => {
        montar('/estudiante/foto', {
            'GET /estudiante/sesion': {
                estudiante: { ...ESTUDIANTE, con_foto: true },
                plazo_foto: { ...PLAZO, abierto: false },
                foto: {
                    puede_subir: true,
                    habilitada_hasta: '2026-10-30',
                    habilitada_por: 'Blanco Coca Leticia',
                },
            },
            'GET /estudiante/foto': new Blob(['jpeg']),
        });

        expect(
            await screen.findByText(
                'Cambio de foto habilitada hasta el 30 oct 2026 · por Blanco Coca Leticia'
            )
        ).toBeInTheDocument();
        expect(await screen.findByRole('img', { name: 'Foto actual' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Guardar foto' })).toBeInTheDocument();
    });

    it('fuera del plazo no ofrece subirla', async () => {
        montar('/estudiante/foto', {
            'GET /estudiante/sesion': {
                estudiante: ESTUDIANTE,
                plazo_foto: { ...PLAZO, abierto: false },
            },
        });

        expect(await screen.findByText('Plazo cerrado · 1 oct – 31 oct 2026')).toBeInTheDocument();
    });

    it('cerrar sesión vuelve al acceso', async () => {
        montar();
        fireEvent.click(await screen.findByRole('button', { name: 'Cerrar sesión' }));

        expect(await screen.findByText('Estoy en /estudiante/ingreso')).toBeInTheDocument();
        expect(api.delete).toHaveBeenCalledWith('/auth/estudiante', { silencioso: true });
    });

    it('si la sesión no carga ofrece reintentar', async () => {
        montar('/estudiante', { 'GET /estudiante/sesion': errorHttp(500) });
        expect(await screen.findByRole('button', { name: /Reintentar/ })).toBeInTheDocument();
    });

    it('un examen pasado sin habilitar no figura como rendido', async () => {
        montar('/estudiante', {
            'GET /estudiante/examenes': {
                data: [{ ...EXAMENES[1], fecha: '2026-10-01' }],
                hoy: '2026-10-22',
            },
        });
        expect(await screen.findByText('Pasado')).toBeInTheDocument();
        expect(screen.queryByText('Rendido')).not.toBeInTheDocument();
    });

    it('un examen pasado: «Rendido» con ingreso y «No asistió» sin él', async () => {
        montar('/estudiante', {
            'GET /estudiante/examenes': {
                data: [
                    { ...EXAMENES[0], id: 5, fecha: '2026-10-01', ingreso: '08:12' },
                    { ...EXAMENES[0], id: 6, fecha: '2026-10-02', ingreso: null },
                ],
                hoy: '2026-10-22',
            },
        });
        expect(await screen.findByText('Rendido')).toBeInTheDocument();
        expect(screen.getByText('No asistió')).toBeInTheDocument();
    });

    it('al pedir confirmación el foco pasa a «Confirmar»', async () => {
        montar('/estudiante/foto');
        await screen.findByRole('button', { name: 'Guardar foto' });
        const foto = new File(['jpeg'], 'yo.jpg', { type: 'image/jpeg' });
        fireEvent.change(screen.getByLabelText('Foto'), { target: { files: [foto] } });
        await screen.findByRole('img', { name: 'Foto' });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar foto' }));

        expect(screen.getByRole('button', { name: 'Confirmar' })).toHaveFocus();
        fireEvent.click(screen.getByRole('button', { name: 'Elegir otra' }));
        expect(screen.queryByRole('img', { name: 'Foto' })).not.toBeInTheDocument();
    });
});

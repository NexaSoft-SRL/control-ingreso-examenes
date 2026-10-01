import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Habilitacion from './Habilitacion';
import { guardarSesion, limpiarSesion } from '../../componentes/sesion.js';

const examenes = [
    { id: 7, asignatura_codigo: 'INF-342', nombre: 'Primer parcial', fecha: '2026-10-15' },
];

const estudiantes = [
    {
        id: 15,
        codigo_universitario: '202100001',
        ci: '1234567',
        nombre: 'Kevin',
        apellido: 'Alvarado',
        carrera: 'Ingeniería de Sistemas',
        condicion: 'HABILITADO',
        motivo: null,
        registrado_por: 'Docente UMSS',
    },
    {
        id: 16,
        codigo_universitario: '202100002',
        ci: '7654321',
        nombre: 'Valeria',
        apellido: 'Bustamante',
        carrera: 'Ingeniería Informática',
        condicion: 'NO_HABILITADO',
        motivo: 'Deuda pendiente',
        registrado_por: 'Docente UMSS',
    },
];

function respuestaLista(datos = estudiantes) {
    const habilitados = datos.filter(({ condicion }) => condicion === 'HABILITADO').length;

    return {
        data: {
            data: datos,
            totales: {
                total: datos.length,
                habilitados,
                no_habilitados: datos.length - habilitados,
            },
        },
    };
}

beforeEach(() => {
    guardarSesion({ id: 1, name: 'Docente UMSS', permisos: ['habilitacion'] });
    window.axios = {
        get: vi.fn((url) =>
            url === '/api/habilitacion/examenes'
                ? Promise.resolve({ data: { data: examenes } })
                : Promise.resolve(respuestaLista())
        ),
        post: vi.fn().mockResolvedValue({ data: {} }),
    };
});

afterEach(() => {
    limpiarSesion();
});

describe('Habilitacion', () => {
    it('carga el examen, el padrón, las condiciones y los conteos', async () => {
        render(<Habilitacion />);

        expect(await screen.findAllByText('Alvarado, Kevin')).not.toHaveLength(0);
        expect(screen.getAllByText('Bustamante, Valeria')).not.toHaveLength(0);
        expect(screen.getByText('1 habilitados')).toBeInTheDocument();
        expect(screen.getByText('1 sin habilitar')).toBeInTheDocument();
        expect(screen.getAllByText('Deuda pendiente')).not.toHaveLength(0);
        expect(window.axios.get).toHaveBeenCalledWith('/api/habilitacion/examenes/7/estudiantes');
    });

    it('envía los estudiantes seleccionados en una sola operación', async () => {
        window.axios.get.mockImplementation((url) => {
            if (url === '/api/habilitacion/examenes') {
                return Promise.resolve({ data: { data: examenes } });
            }

            return Promise.resolve(respuestaLista());
        });

        render(<Habilitacion />);
        await screen.findAllByText('Alvarado, Kevin');

        fireEvent.click(screen.getAllByRole('checkbox', { name: 'Seleccionar Kevin Alvarado' })[0]);
        fireEvent.click(
            screen.getAllByRole('checkbox', { name: 'Seleccionar Valeria Bustamante' })[0]
        );
        fireEvent.click(screen.getByRole('button', { name: /Habilitar seleccionados/ }));

        await waitFor(() =>
            expect(window.axios.post).toHaveBeenCalledWith(
                '/api/habilitacion/examenes/7/condiciones',
                {
                    estudiante_ids: [15, 16],
                    condicion: 'HABILITADO',
                    motivo: null,
                }
            )
        );
    });

    it('exige un motivo antes de inhabilitar', async () => {
        render(<Habilitacion />);
        await screen.findAllByText('Alvarado, Kevin');

        fireEvent.click(screen.getAllByRole('checkbox', { name: 'Seleccionar Kevin Alvarado' })[0]);
        fireEvent.click(screen.getByRole('button', { name: /Inhabilitar seleccionados/ }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'El motivo es obligatorio para inhabilitar.'
        );
        expect(window.axios.post).not.toHaveBeenCalled();
    });

    it('expone la descarga para el examen seleccionado', async () => {
        render(<Habilitacion />);
        await screen.findAllByText('Alvarado, Kevin');

        expect(screen.getByRole('link', { name: 'Exportar listado' })).toHaveAttribute(
            'href',
            '/api/habilitacion/examenes/7/exportar'
        );
    });
});

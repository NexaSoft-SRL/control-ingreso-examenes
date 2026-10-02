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
        fecha_habilitacion: '2026-10-02T14:30:00+00:00',
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

    it('marca el motivo como obligatorio y avisa mientras se escribe', async () => {
        render(<Habilitacion />);
        await screen.findAllByText('Alvarado, Kevin');

        const motivo = screen.getByLabelText(/Motivo de la inhabilitación/);

        expect(motivo).toBeRequired();
        expect(motivo).toHaveAttribute('aria-invalid', 'false');

        fireEvent.change(motivo, { target: { value: 'no' } });

        expect(motivo).toHaveAttribute('aria-invalid', 'true');
        expect(
            screen.getByText('El motivo debe explicar la inhabilitación con al menos 5 caracteres.')
        ).toBeInTheDocument();
        expect(screen.getByText('2/1000')).toBeInTheDocument();

        fireEvent.change(motivo, { target: { value: 'Adeuda la matrícula' } });

        expect(motivo).toHaveAttribute('aria-invalid', 'false');
        expect(
            screen.queryByText(
                'El motivo debe explicar la inhabilitación con al menos 5 caracteres.'
            )
        ).not.toBeInTheDocument();
    });

    it('no envía la inhabilitación con un motivo demasiado corto', async () => {
        render(<Habilitacion />);
        await screen.findAllByText('Alvarado, Kevin');

        fireEvent.click(screen.getAllByRole('checkbox', { name: 'Seleccionar Kevin Alvarado' })[0]);
        fireEvent.change(screen.getByLabelText(/Motivo de la inhabilitación/), {
            target: { value: 'no' },
        });
        fireEvent.click(screen.getByRole('button', { name: /Inhabilitar seleccionados/ }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'El motivo debe explicar la inhabilitación con al menos 5 caracteres.'
        );
        expect(window.axios.post).not.toHaveBeenCalled();
    });

    it('envía el motivo sin espacios sobrantes al inhabilitar', async () => {
        render(<Habilitacion />);
        await screen.findAllByText('Alvarado, Kevin');

        fireEvent.click(screen.getAllByRole('checkbox', { name: 'Seleccionar Kevin Alvarado' })[0]);
        fireEvent.change(screen.getByLabelText(/Motivo de la inhabilitación/), {
            target: { value: '  Adeuda la matrícula  ' },
        });
        fireEvent.click(screen.getByRole('button', { name: /Inhabilitar seleccionados/ }));

        await waitFor(() =>
            expect(window.axios.post).toHaveBeenCalledWith(
                '/api/habilitacion/examenes/7/condiciones',
                {
                    estudiante_ids: [15],
                    condicion: 'NO_HABILITADO',
                    motivo: 'Adeuda la matrícula',
                }
            )
        );
    });

    it('muestra quién registró la condición y cuándo, en hora de Bolivia', async () => {
        render(<Habilitacion />);
        await screen.findAllByText('Bustamante, Valeria');

        expect(screen.getAllByText('Docente UMSS')).not.toHaveLength(0);
        expect(screen.getAllByText(/02\/10\/2026,? 10:30/)).not.toHaveLength(0);
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

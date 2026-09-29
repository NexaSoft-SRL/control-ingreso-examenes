import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AsignaturasAmbientes from './AsignaturasAmbientes';

const asignatura = {
    id: 4,
    codigo: 'INF-401',
    nombre: 'Materia inicial',
    semestre: '6',
    descripcion: 'Descripción que debe conservarse.',
    grupos: [
        {
            id: 9,
            codigo_grupo: 'A',
            cupo: 30,
            docente: {
                id: 17,
                codigo_docente: 'DOC-17',
                nombres: 'Ana',
                apellidos: 'Pérez',
            },
        },
    ],
};

const docentes = [
    asignatura.grupos[0].docente,
    {
        id: 18,
        codigo_docente: 'DOC-18',
        nombres: 'Luis',
        apellidos: 'Rojas',
    },
];

beforeEach(() => {
    window.axios = {
        get: vi.fn((url) => {
            if (url === '/api/asignaturas') {
                return Promise.resolve({ data: { data: [asignatura] } });
            }

            if (url === '/api/docentes') {
                return Promise.resolve({ data: { data: docentes } });
            }

            return Promise.resolve({ data: [] });
        }),
        post: vi.fn().mockResolvedValue({ data: {} }),
        put: vi.fn().mockResolvedValue({ data: {} }),
        delete: vi.fn().mockResolvedValue({ data: {} }),
    };
});

describe('AsignaturasAmbientes', () => {
    it('precarga y actualiza la materia y el docente responsable', async () => {
        render(<AsignaturasAmbientes onNavigate={vi.fn()} />);

        await screen.findByText('Materia inicial');
        fireEvent.click(screen.getByRole('button', { name: 'Editar' }));

        expect(screen.getByRole('heading', { name: 'Editar asignatura' })).toBeInTheDocument();
        expect(screen.getByLabelText('Sigla')).toHaveValue('INF-401');
        expect(screen.getByLabelText('Materia')).toHaveValue('Materia inicial');
        expect(screen.getByLabelText('Docente responsable')).toHaveValue('17');
        expect(screen.getByLabelText('Cupo del grupo')).toHaveValue(30);

        fireEvent.change(screen.getByLabelText('Sigla'), {
            target: { value: 'INF-402' },
        });
        fireEvent.change(screen.getByLabelText('Materia'), {
            target: { value: 'Materia actualizada' },
        });
        fireEvent.change(screen.getByLabelText('Docente responsable'), {
            target: { value: '18' },
        });
        fireEvent.change(screen.getByLabelText('Cupo del grupo'), {
            target: { value: '40' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }));

        await waitFor(() =>
            expect(window.axios.put).toHaveBeenCalledWith('/api/asignaturas/4', {
                codigo: 'INF-402',
                nombre: 'Materia actualizada',
                semestre: '6',
                descripcion: 'Descripción que debe conservarse.',
                grupos: [
                    {
                        codigo_grupo: 'A',
                        docente_id: 18,
                        cupo: 40,
                    },
                ],
            })
        );
    });

         it('rechaza una capacidad negativa sin llamar al backend', async () => {
        render(<AsignaturasAmbientes onNavigate={vi.fn()} />);

        await screen.findByText('Materia inicial');

        fireEvent.click(screen.getByRole('button', { name: '+ Nuevo' }));

        fireEvent.change(screen.getByLabelText('Nombre del aula'), {
            target: { value: 'Aula Negativa' },
        });
        fireEvent.change(screen.getByLabelText('Capacidad'), {
            target: { value: '-60' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        await waitFor(() => {
            expect(
                screen.getByText('La capacidad debe ser un número entero positivo superior a 0.')
            ).toBeInTheDocument();
        });

        expect(window.axios.post).not.toHaveBeenCalledWith(
            '/api/admin/ambientes',
            expect.anything()
        );
    });

    it('rechaza una capacidad cero sin llamar al backend', async () => {
        render(<AsignaturasAmbientes onNavigate={vi.fn()} />);

        await screen.findByText('Materia inicial');

        fireEvent.click(screen.getByRole('button', { name: '+ Nuevo' }));

        fireEvent.change(screen.getByLabelText('Nombre del aula'), {
            target: { value: 'Aula Cero' },
        });
        fireEvent.change(screen.getByLabelText('Capacidad'), {
            target: { value: '0' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        await waitFor(() => {
            expect(
                screen.getByText('La capacidad debe ser un número entero positivo superior a 0.')
            ).toBeInTheDocument();
        });

        expect(window.axios.post).not.toHaveBeenCalled();
    });

    it('muestra el mensaje del backend cuando el nombre de ambiente ya existe', async () => {
        window.axios.post = vi.fn().mockRejectedValueOnce({
            response: {
                status: 422,
                data: {
                    errors: {
                        nombre: ['Ya existe un ambiente registrado con el nombre Aula 690.'],
                    },
                },
            },
        });

        render(<AsignaturasAmbientes onNavigate={vi.fn()} />);

        await screen.findByText('Materia inicial');

        fireEvent.click(screen.getByRole('button', { name: '+ Nuevo' }));

        fireEvent.change(screen.getByLabelText('Nombre del aula'), {
            target: { value: 'Aula 690' },
        });
        fireEvent.change(screen.getByLabelText('Capacidad'), {
            target: { value: '70' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        await waitFor(() => {
            expect(
                screen.getByText('Ya existe un ambiente registrado con el nombre Aula 690.')
            ).toBeInTheDocument();
        });
    });   
});

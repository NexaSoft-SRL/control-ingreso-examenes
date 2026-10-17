import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AtencionAulas from './AtencionAulas';
import { api } from '../../api/cliente';

vi.mock('../../api/cliente', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('AtencionAulas (HU-18)', () => {
    const cuenta = { id: 1, nombre: 'Docente Titular', rol: 'Docente' };
    const docentesDisponibles = [
        { id: 1, nombre: 'Docente Titular' },
        { id: 2, nombre: 'Docente Colega' },
    ];
    const nombreAula = (id) => (id === 101 ? '691A' : id === 102 ? '691B' : '691C');
    const ubicacionAula = (id) => `Edificio Central · Aula ${id}`;

    beforeEach(() => {
        vi.clearAllMocks();
        api.get.mockImplementation((url) => {
            if (url === '/auxiliares') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 50,
                                nombre: 'Auxiliar Juan',
                                codigo: '20210001',
                                correo: 'juan@est.umss.edu',
                            },
                        ],
                    },
                });
            }
            return Promise.resolve({ data: { data: [] } });
        });
    });

    it('CA-1: si no hay aulas muestra «Sin aulas»', () => {
        render(
            <AtencionAulas
                aulas={[]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={{}}
                onCambiarAtencion={vi.fn()}
            />
        );

        expect(screen.getByText('Quién atiende')).toBeInTheDocument();
        expect(screen.getByText('Sin aulas')).toBeInTheDocument();
    });

    it('CA-1: lista cada aula con su ubicación y la cantidad de aulas', async () => {
        const atencion = {
            101: {
                a_cargo: 1,
                apoyo: [{ id: 50, nombre: 'Auxiliar Juan' }],
                codigo: '123456',
            },
        };

        render(
            <AtencionAulas
                aulas={[101]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={atencion}
                onCambiarAtencion={vi.fn()}
                examenId={5}
                fechaExamen="martes 20 de octubre"
            />
        );

        expect(screen.getByText('4. Quién atiende')).toBeInTheDocument();
        expect(screen.getByText('1 aula')).toBeInTheDocument();
        expect(screen.getByText('Aula 691A')).toBeInTheDocument();
        expect(screen.getByText('Edificio Central · Aula 101')).toBeInTheDocument();
        expect(screen.getByLabelText('A cargo del aula 691A')).toHaveValue('1');
        expect(screen.getByText('Auxiliar Juan')).toBeInTheDocument();
        expect(screen.getByText('123456')).toBeInTheDocument();
    });

    it('CA-2 y CA-3: cambiar a cargo saca a esa persona de apoyo si estaba', async () => {
        const atencion = {
            101: {
                a_cargo: 1,
                apoyo: [{ id: 50, nombre: 'Auxiliar Juan' }],
                codigo: null,
            },
        };
        const onCambiarAtencion = vi.fn();

        render(
            <AtencionAulas
                aulas={[101]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={atencion}
                onCambiarAtencion={onCambiarAtencion}
            />
        );

        // Esperar a que cargue la lista de auxiliares
        expect(await screen.findByText('Auxiliar Juan')).toBeInTheDocument();

        // Cambiar a cargo al auxiliar 50
        fireEvent.change(screen.getByLabelText('A cargo del aula 691A'), {
            target: { value: '50' },
        });

        // Auxiliar 50 pasa a a_cargo y sale de apoyo
        expect(onCambiarAtencion).toHaveBeenCalledWith({
            101: {
                a_cargo: 50,
                apoyo: [],
                codigo: null,
            },
        });
    });

    it('CA-4: botón «Repartir» sólo con más de 2 aulas y al menos un auxiliar', async () => {
        const onCambiarAtencion = vi.fn();
        const atencion = {
            101: { a_cargo: 1, apoyo: [], codigo: null },
            102: { a_cargo: 1, apoyo: [], codigo: null },
            103: { a_cargo: 1, apoyo: [], codigo: null },
        };

        const { rerender } = render(
            <AtencionAulas
                aulas={[101, 102]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={atencion}
                onCambiarAtencion={onCambiarAtencion}
            />
        );

        // Esperar a que cargue la lista de auxiliares
        expect(await screen.findByText('Auxiliar Juan')).toBeInTheDocument();

        // Con 2 aulas no se ofrece Repartir
        expect(screen.queryByRole('button', { name: 'Repartir' })).not.toBeInTheDocument();

        // Con 3 aulas se ofrece
        rerender(
            <AtencionAulas
                aulas={[101, 102, 103]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={atencion}
                onCambiarAtencion={onCambiarAtencion}
            />
        );

        const botonRepartir = await screen.findByRole('button', { name: 'Repartir' });
        expect(botonRepartir).toBeInTheDocument();

        fireEvent.click(botonRepartir);

        // Primera aula con docente (1), segunda con auxiliar (50), tercera con docente (1)
        expect(onCambiarAtencion).toHaveBeenCalledWith({
            101: { a_cargo: 1, apoyo: [], codigo: null },
            102: { a_cargo: 50, apoyo: [], codigo: null },
            103: { a_cargo: 1, apoyo: [], codigo: null },
        });
    });

    it('CA-5: busca auxiliares desde 3 caracteres y permite sumarlos a la lista', async () => {
        api.get.mockImplementation((url, config) => {
            if (config?.params?.buscar === 'Marcos') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 80,
                                nombre: 'Marcos Encinas',
                                codigo: '20220002',
                                correo: 'marcos@est.umss.edu',
                            },
                        ],
                    },
                });
            }
            return Promise.resolve({ data: { data: [] } });
        });

        render(
            <AtencionAulas
                aulas={[101]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={{ 101: { a_cargo: 1, apoyo: [], codigo: null } }}
                onCambiarAtencion={vi.fn()}
            />
        );

        fireEvent.change(screen.getByRole('textbox', { name: 'Buscar auxiliar' }), {
            target: { value: 'Marcos' },
        });

        const item = await screen.findByText('Marcos Encinas');
        expect(item).toBeInTheDocument();
        fireEvent.click(within(item.closest('li')).getByRole('button', { name: 'Agregar' }));

        expect(await screen.findByText('Auxiliar agregado')).toBeInTheDocument();
    });

    it('CA-6 y CA-7: valida formulario de auxiliar nuevo y muestra avisos correspondientes', async () => {
        api.post.mockResolvedValueOnce({
            data: {
                message: 'Auxiliar agregado',
                data: {
                    id: 99,
                    nombre: 'Carlos Rojas',
                    codigo: '20230005',
                    correo: '20230005@est.umss.edu',
                },
            },
        });

        render(
            <AtencionAulas
                aulas={[101]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={{ 101: { a_cargo: 1, apoyo: [], codigo: null } }}
                onCambiarAtencion={vi.fn()}
            />
        );

        // Sin datos señala Obligatorio
        fireEvent.click(screen.getByRole('button', { name: 'Agregar' }));
        expect(screen.getAllByText('Obligatorio')).toHaveLength(2);

        // Con nombre < 3 o código inválido
        fireEvent.change(screen.getByLabelText(/^Nombre completo/), { target: { value: 'Jo' } });
        fireEvent.change(screen.getByLabelText(/^Código universitario/), {
            target: { value: '123' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Agregar' }));

        expect(screen.getByText('Mínimo 3 caracteres')).toBeInTheDocument();
        expect(screen.getByText('Entre 5 y 10 dígitos')).toBeInTheDocument();

        // Con datos válidos
        fireEvent.change(screen.getByLabelText(/^Nombre completo/), {
            target: { value: 'Carlos Rojas' },
        });
        fireEvent.change(screen.getByLabelText(/^Código universitario/), {
            target: { value: '20230005' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Agregar' }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/auxiliares', {
                nombre_completo: 'Carlos Rojas',
                codigo_estudiante: '20230005',
            });
        });

        expect(await screen.findByText('Auxiliar agregado')).toBeInTheDocument();
    });

    it('CA-8: quitar auxiliar abre diálogo, revierte aulas al docente y avisa «Auxiliar quitado»', async () => {
        const onCambiarAtencion = vi.fn();
        const atencion = {
            101: { a_cargo: 50, apoyo: [], codigo: null },
        };
        api.delete.mockResolvedValueOnce({ data: { message: 'Auxiliar quitado' } });

        render(
            <AtencionAulas
                aulas={[101]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={atencion}
                onCambiarAtencion={onCambiarAtencion}
            />
        );

        // Esperar a que se cargue el auxiliar habitual (id: 50, Juan)
        expect(await screen.findByText('Auxiliar Juan')).toBeInTheDocument();

        // Pulsar la equis del auxiliar en la lista
        fireEvent.click(screen.getByRole('button', { name: 'Quitar auxiliar Auxiliar Juan' }));

        // Diálogo de confirmación
        expect(await screen.findByRole('heading', { name: 'Quitar auxiliar' })).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Quitar auxiliar' }));

        await waitFor(() => {
            expect(api.delete).toHaveBeenCalledWith('/auxiliares/50');
        });

        expect(await screen.findByText('Auxiliar quitado')).toBeInTheDocument();
        // Aulas donde estaba revierten al docente
        expect(onCambiarAtencion).toHaveBeenCalledWith({
            101: {
                a_cargo: 1,
                apoyo: [],
                codigo: null,
            },
        });
    });

    it('CA-11, CA-12, CA-13: llave abre diálogo de código, genera, copia y regenera', async () => {
        const onCambiarAtencion = vi.fn();
        const atencion = {
            101: { a_cargo: 1, apoyo: [], codigo: null },
        };

        api.post.mockResolvedValueOnce({
            data: { data: { codigo: '654321' } },
        });

        const { rerender } = render(
            <AtencionAulas
                aulas={[101]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={atencion}
                onCambiarAtencion={onCambiarAtencion}
                examenId={7}
                fechaExamen="viernes 23 de octubre"
            />
        );

        // Pulsar la llave
        fireEvent.click(screen.getByRole('button', { name: 'Código de acceso del aula 691A' }));

        const dialogo = await screen.findByRole('dialog');
        expect(
            within(dialogo).getByRole('heading', { name: 'Código de acceso' })
        ).toBeInTheDocument();
        expect(within(dialogo).getByText('Sin código')).toBeInTheDocument();
        expect(within(dialogo).getByText(/Aula:/)).toBeInTheDocument();
        expect(within(dialogo).getByText(/691A/)).toBeInTheDocument();
        expect(within(dialogo).getByText(/Fecha del examen:/)).toBeInTheDocument();
        expect(within(dialogo).getByText(/viernes 23 de octubre/)).toBeInTheDocument();

        // Pulsar Generar
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Generar' }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/examenes/7/aulas/101/codigo');
        });

        expect(await screen.findByText('Código generado')).toBeInTheDocument();

        // Re-render con el código generado
        rerender(
            <AtencionAulas
                aulas={[101]}
                nombreAula={nombreAula}
                ubicacionAula={ubicacionAula}
                docentesDisponibles={docentesDisponibles}
                cuenta={cuenta}
                atencion={{ 101: { a_cargo: 1, apoyo: [], codigo: '654321' } }}
                onCambiarAtencion={onCambiarAtencion}
                examenId={7}
                fechaExamen="viernes 23 de octubre"
            />
        );

        // Copiar
        navigator.clipboard = { writeText: vi.fn() };
        fireEvent.click(screen.getByRole('button', { name: /Copiar/ }));
        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('654321');
        expect(await screen.findByText('Código copiado')).toBeInTheDocument();

        // Regenerar
        api.post.mockResolvedValueOnce({
            data: { data: { codigo: '999888' } },
        });
        fireEvent.click(screen.getByRole('button', { name: /Regenerar/ }));
        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/examenes/7/aulas/101/codigo');
        });
        expect(await screen.findByText('Código regenerado')).toBeInTheDocument();

        // Cerrar con la X
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cerrar' }));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
});

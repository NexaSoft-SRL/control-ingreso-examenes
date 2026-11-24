import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../api/cliente';
import { errorHttp, simularApi } from '../../test/apoyo';
import CalendarioAulas from './CalendarioAulas';

vi.mock('../../api/cliente');

const AULAS = [
    {
        id: 5,
        nombre: '691A',
        edificio_id: 1,
        edificio: 'Edificio FCyT',
        piso: null,
        facultad: 'FCyT',
    },
    {
        id: 6,
        nombre: '617',
        edificio_id: 2,
        edificio: 'Edificio Nuevo',
        piso: null,
        facultad: 'FCyT',
    },
    { id: 7, nombre: 'AULVIR', edificio_id: null, edificio: null, piso: null, facultad: null },
];

// Semana del lunes 23 al sábado 28 de noviembre de 2026.
const SEMANA = {
    clases: [
        {
            fecha: '2026-11-23',
            inicio: '08:15',
            fin: '09:45',
            asignatura: 'Cálculo I',
            grupo: 'G1',
            docente: 'Ana Rojas',
            auxiliatura: false,
        },
        {
            fecha: '2026-11-27',
            inicio: '11:15',
            fin: '12:45',
            asignatura: 'Física I',
            grupo: 'G4',
            docente: null,
            auxiliatura: false,
        },
    ],
    examenes: [
        {
            id: 9,
            fecha: '2026-11-25',
            inicio: '14:00',
            fin: '15:30',
            asignatura: 'Álgebra',
            tipo: 'Primer parcial',
            docente: 'Carla Salazar',
            propio: true,
        },
        {
            id: null,
            fecha: '2026-11-26',
            inicio: '16:30',
            fin: '17:30',
            asignatura: 'Química',
            tipo: 'Examen final',
            docente: 'Juan Pérez',
            propio: false,
        },
    ],
};

const pedidos = () => api.get.mock.calls.filter(([ruta]) => ruta === '/calendario/aulas');

function montar({ entrada = '/calendario?aula=5', semana = SEMANA, calendario } = {}) {
    simularApi(api, {
        'GET /aulas': { data: AULAS },
        'GET /calendario/aulas':
            calendario ??
            ((pedido) => ({
                desde: pedido.params.desde,
                hasta: pedido.params.hasta,
                aulas: { [pedido.params.aulas[0]]: semana },
            })),
    });
    return render(
        <MemoryRouter initialEntries={[entrada]}>
            <CalendarioAulas />
        </MemoryRouter>
    );
}

const movil = (activo) => {
    window.matchMedia = vi.fn().mockImplementation((consulta) => ({
        matches: activo && consulta === '(max-width: 639px)',
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    }));
};

beforeEach(() => {
    // Martes 24 de noviembre de 2026.
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-11-24T10:00:00'));
    movil(false);
});

afterEach(() => {
    vi.useRealTimers();
    delete window.matchMedia;
});

describe('Calendario de aulas', () => {
    it('sin aula pide elegir una y no consulta el calendario', async () => {
        montar({ entrada: '/calendario' });

        expect(await screen.findByText('Elija un aula')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Registrar examen/ })).toHaveAttribute(
            'href',
            '/examenes/nuevo'
        );
        expect(pedidos()).toHaveLength(0);
    });

    it('con un aula elegida, «Registrar examen» lleva el aula y el día a la pantalla de registro', async () => {
        montar();

        const enlace = await screen.findByRole('link', { name: /Registrar examen/ });
        expect(enlace.getAttribute('href')).toMatch(
            /^\/examenes\/nuevo\?aula=5&fecha=\d{4}-\d{2}-\d{2}$/
        );
    });

    it('pide la semana del aula y dibuja sus clases y exámenes, cada uno con su tipo', async () => {
        montar();

        expect(
            await screen.findByRole('button', { name: 'Clase · Cálculo I · 08:15 a 09:45' })
        ).toBeInTheDocument();
        expect(pedidos()[0][1]).toEqual({
            params: { desde: '2026-11-23', hasta: '2026-11-28', aulas: [5] },
        });
        expect(screen.getByText('Aula 691A · Edificio FCyT')).toBeInTheDocument();
        expect(screen.getByText('23 – 28 nov 2026')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Tu examen · Álgebra · 14:00 a 15:30' })
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Examen · Química · 16:30 a 17:30' })
        ).toBeInTheDocument();
        expect(screen.queryByText('Aula libre esta semana')).not.toBeInTheDocument();
        // Los seis días de la semana, con hoy marcado.
        expect(screen.getByLabelText('martes 24 de noviembre, hoy')).toBeInTheDocument();
        expect(screen.getByLabelText('sábado 28 de noviembre')).toBeInTheDocument();
    });

    it('un aula sin nada esa semana dice que está libre', async () => {
        montar({ semana: { clases: [], examenes: [] } });

        expect(await screen.findByText('Aula libre esta semana')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Clase ·/ })).not.toBeInTheDocument();
    });

    it('pasa de semana, vuelve con «Hoy» y pide cada una', async () => {
        montar();
        await screen.findByRole('button', { name: /Cálculo I/ });

        fireEvent.click(screen.getByRole('button', { name: 'Semana siguiente' }));
        expect(await screen.findByText('30 nov – 5 dic 2026')).toBeInTheDocument();
        await waitFor(() =>
            expect(pedidos().at(-1)[1].params).toEqual({
                desde: '2026-11-30',
                hasta: '2026-12-05',
                aulas: [5],
            })
        );

        fireEvent.click(screen.getByRole('button', { name: 'Semana anterior' }));
        fireEvent.click(screen.getByRole('button', { name: 'Semana anterior' }));
        expect(await screen.findByText('16 – 21 nov 2026')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Hoy' }));
        expect(await screen.findByText('23 – 28 nov 2026')).toBeInTheDocument();
    });

    it('tocar una clase muestra su detalle; tocar tu examen ofrece abrirlo', async () => {
        montar();
        fireEvent.click(await screen.findByRole('button', { name: /Clase · Cálculo I/ }));

        const detalle = within(screen.getByRole('dialog', { name: 'Cálculo I' }));
        expect(detalle.getByText('lunes 23 de noviembre')).toBeInTheDocument();
        expect(detalle.getByText('08:15 a 09:45')).toBeInTheDocument();
        expect(detalle.getByText('Grupo G1')).toBeInTheDocument();
        expect(detalle.getByText('Ana Rojas')).toBeInTheDocument();
        expect(detalle.queryByRole('link', { name: 'Ver examen' })).not.toBeInTheDocument();

        fireEvent.click(detalle.getAllByRole('button', { name: 'Cerrar' }).at(-1));
        fireEvent.click(screen.getByRole('button', { name: /Tu examen · Álgebra/ }));

        const mio = within(screen.getByRole('dialog', { name: 'Álgebra' }));
        expect(mio.getByText('Primer parcial')).toBeInTheDocument();
        expect(mio.getByRole('link', { name: 'Ver examen' })).toHaveAttribute(
            'href',
            '/examenes/detalle?examen=9'
        );
    });

    it('el examen de otro docente se ve ocupado, pero sin abrirlo', async () => {
        montar();
        fireEvent.click(await screen.findByRole('button', { name: /Examen · Química/ }));

        const detalle = within(screen.getByRole('dialog', { name: 'Química' }));
        expect(detalle.getByText('Juan Pérez')).toBeInTheDocument();
        expect(detalle.getByText('Examen final')).toBeInTheDocument();
        expect(detalle.queryByRole('link', { name: 'Ver examen' })).not.toBeInTheDocument();
    });

    it('una clase sin docente dice «Por designar»', async () => {
        montar();
        fireEvent.click(await screen.findByRole('button', { name: /Clase · Física I/ }));

        expect(within(screen.getByRole('dialog')).getByText('Por designar')).toBeInTheDocument();
    });

    it('elige el aula de una lista que se filtra por edificio y por texto', async () => {
        montar({ entrada: '/calendario' });
        await screen.findByText('Elija un aula');
        await waitFor(() => expect(api.get).toHaveBeenCalledWith('/aulas', expect.anything()));

        fireEvent.click(screen.getByRole('combobox', { name: 'Aula' }));
        const todas = within(screen.getByRole('listbox', { name: 'Opciones de Aula' }));
        expect(todas.getAllByRole('option').map((o) => o.textContent)).toEqual([
            'Elegir aula',
            '617 · Edificio Nuevo',
            '691A · Edificio FCyT',
            'AULVIR',
        ]);
        fireEvent.keyDown(screen.getByRole('combobox', { name: 'Aula' }), { key: 'Escape' });

        fireEvent.change(screen.getByRole('textbox', { name: 'Buscar aula' }), {
            target: { value: '691' },
        });
        fireEvent.click(screen.getByRole('combobox', { name: 'Aula' }));
        const buscadas = within(screen.getByRole('listbox', { name: 'Opciones de Aula' }));
        expect(buscadas.getAllByRole('option').map((o) => o.textContent)).toEqual([
            'Elegir aula',
            '691A · Edificio FCyT',
        ]);

        fireEvent.click(buscadas.getByRole('option', { name: '691A · Edificio FCyT' }));

        expect(await screen.findByText('Aula 691A · Edificio FCyT')).toBeInTheDocument();
        await waitFor(() => expect(pedidos().at(-1)[1].params.aulas).toEqual([5]));
    });

    it('filtra las aulas por edificio, con «Sin edificio» para las que no están ubicadas', async () => {
        montar({ entrada: '/calendario' });
        await waitFor(() => expect(api.get).toHaveBeenCalledWith('/aulas', expect.anything()));

        fireEvent.click(screen.getByRole('combobox', { name: 'Edificio' }));
        fireEvent.click(screen.getByRole('option', { name: 'Sin edificio' }));
        fireEvent.click(screen.getByRole('combobox', { name: 'Aula' }));

        expect(
            within(screen.getByRole('listbox', { name: 'Opciones de Aula' }))
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual(['Elegir aula', 'AULVIR']);
    });

    it('si falla, ofrece reintentar', async () => {
        montar({ calendario: errorHttp(500, { message: 'Error' }) });

        fireEvent.click(await screen.findByRole('button', { name: 'Reintentar' }));

        await waitFor(() => expect(pedidos().length).toBeGreaterThan(1));
    });
});

describe('Calendario de aulas en el teléfono', () => {
    it('muestra un solo día y se cambia con los botones de día', async () => {
        movil(true);
        montar();
        const dias = await screen.findByRole('group', { name: 'Día' });

        // Hoy es martes: no hay nada ese día, y es el que está marcado.
        expect(
            within(dias).getByRole('button', { name: 'martes 24 de noviembre' })
        ).toHaveAttribute('aria-pressed', 'true');
        expect(screen.queryByRole('button', { name: /Cálculo I|Álgebra|Química/ })).toBeNull();
        expect(screen.queryByText('Aula libre esta semana')).not.toBeInTheDocument();

        fireEvent.click(within(dias).getByRole('button', { name: 'miércoles 25 de noviembre' }));
        expect(
            screen.getByRole('button', { name: 'Tu examen · Álgebra · 14:00 a 15:30' })
        ).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Cálculo I/ })).toBeNull();

        fireEvent.click(within(dias).getByRole('button', { name: 'lunes 23 de noviembre' }));
        expect(screen.getByRole('button', { name: /Cálculo I/ })).toBeInTheDocument();
    });

    it('al pasar de semana conserva el día elegido', async () => {
        movil(true);
        montar();
        const dias = await screen.findByRole('group', { name: 'Día' });
        fireEvent.click(within(dias).getByRole('button', { name: 'viernes 27 de noviembre' }));
        expect(screen.getByRole('button', { name: /Física I/ })).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Semana siguiente' }));

        expect(
            await screen.findByRole('button', { name: 'viernes 4 de diciembre' })
        ).toHaveAttribute('aria-pressed', 'true');
    });
});

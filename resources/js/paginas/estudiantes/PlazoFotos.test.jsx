import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { BotonPlazoFotos } from './PlazoFotos';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

const plazo = (cambios = {}) => ({
    data: {
        desde: '2026-10-01',
        hasta: '2026-10-31',
        abierto: true,
        estado: 'abierto',
        ...cambios,
    },
});

function montar(rutas = {}) {
    const avisar = vi.fn();
    simularApi(api, { 'GET /estudiantes/plazo-fotos': plazo(), ...rutas });
    renderConSesion(<BotonPlazoFotos avisar={avisar} />);
    return avisar;
}

describe('Plazo de fotos', () => {
    it('el botón muestra el plazo vigente', async () => {
        montar();
        expect(
            await screen.findByRole('button', { name: /Fotos: 1 oct – 31 oct 2026/ })
        ).toBeInTheDocument();
    });

    it('dice si todavía no empieza o ya cerró', async () => {
        montar({ 'GET /estudiantes/plazo-fotos': plazo({ abierto: false, estado: 'proximo' }) });
        expect(await screen.findByText('Próximo')).toBeInTheDocument();
    });

    it('sin plazo ofrece fijarlo y lo guarda', async () => {
        const avisar = montar({
            'GET /estudiantes/plazo-fotos': { data: null },
            'PUT /estudiantes/plazo-fotos': plazo(),
        });

        fireEvent.click(await screen.findByRole('button', { name: /Plazo de fotos/ }));
        const dialogo = screen.getByRole('dialog');
        fireEvent.change(within(dialogo).getByLabelText(/Desde/), {
            target: { value: '2026-10-01' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Hasta/), {
            target: { value: '2026-10-31' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        await waitFor(() =>
            expect(api.put).toHaveBeenCalledWith('/estudiantes/plazo-fotos', {
                desde: '2026-10-01',
                hasta: '2026-10-31',
            })
        );
        expect(avisar).toHaveBeenCalledWith('Plazo guardado');
    });

    it('muestra bajo el campo el límite de 180 días', async () => {
        montar({
            'PUT /estudiantes/plazo-fotos': errorHttp(422, {
                message: 'Máximo 180 días',
                errors: { hasta: ['Máximo 180 días'] },
            }),
        });

        fireEvent.click(await screen.findByRole('button', { name: /Fotos:/ }));
        fireEvent.click(
            within(screen.getByRole('dialog')).getByRole('button', { name: 'Guardar' })
        );

        expect(await screen.findByText('Máximo 180 días')).toBeInTheDocument();
    });
});

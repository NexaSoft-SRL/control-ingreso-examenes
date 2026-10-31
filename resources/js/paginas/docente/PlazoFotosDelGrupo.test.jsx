import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PlazoFotosDelGrupo from './PlazoFotosDelGrupo';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const plazo = (datos) => ({
    data: { hasta: null, fijado_por: null, inscritos: 40, sin_foto: 12, vencido: false, ...datos },
});

const montar = (onAvisar = vi.fn()) =>
    renderConSesion(<PlazoFotosDelGrupo grupoId={4} onAvisar={onAvisar} />, {
        usuario: usuarioDePrueba('Docente'),
    });

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-28T10:00:00'));
});

afterEach(() => vi.useRealTimers());

describe('PlazoFotosDelGrupo', () => {
    it('sin plazo propone dos semanas y lo fija', async () => {
        simularApi(api, {
            'GET /docente/grupos/4/plazo-foto': plazo(),
            'PUT /docente/grupos/4/plazo-foto': plazo({ hasta: '2026-11-15' }),
        });
        const onAvisar = vi.fn();
        montar(onAvisar);

        fireEvent.click(
            await screen.findByRole('button', { name: 'Plazo de fotos · 12 sin foto' })
        );
        expect(screen.getByText('12 de 40 sin foto')).toBeInTheDocument();
        const dia = screen.getByLabelText(/Suben su foto hasta/);
        expect(dia).toHaveValue('2026-11-11');
        expect(dia).toHaveAttribute('min', '2026-10-28');
        expect(dia).toHaveAttribute('max', '2027-04-26');
        expect(screen.queryByRole('button', { name: 'Quitar plazo' })).not.toBeInTheDocument();

        fireEvent.change(dia, { target: { value: '2026-11-15' } });
        fireEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        await waitFor(() => expect(onAvisar).toHaveBeenCalledWith('Fotos hasta el 15 nov 2026'));
        expect(api.put).toHaveBeenCalledWith('/docente/grupos/4/plazo-foto', {
            hasta: '2026-11-15',
        });
    });

    it('con plazo lo muestra y lo quita', async () => {
        simularApi(api, {
            'GET /docente/grupos/4/plazo-foto': plazo({ hasta: '2026-11-15' }),
            'PUT /docente/grupos/4/plazo-foto': plazo(),
        });
        const onAvisar = vi.fn();
        montar(onAvisar);

        fireEvent.click(
            await screen.findByRole('button', { name: 'Fotos hasta el 15 nov 2026 · 12 sin foto' })
        );
        expect(screen.getByLabelText(/Suben su foto hasta/)).toHaveValue('2026-11-15');
        fireEvent.click(screen.getByRole('button', { name: 'Quitar plazo' }));

        await waitFor(() => expect(onAvisar).toHaveBeenCalledWith('Plazo de fotos quitado'));
        expect(api.put).toHaveBeenCalledWith('/docente/grupos/4/plazo-foto', { hasta: null });
    });

    it('vencido lo marca y deja moverlo', async () => {
        simularApi(api, {
            'GET /docente/grupos/4/plazo-foto': plazo({
                hasta: '2026-10-20',
                fijado_por: 'Blanco Coca Leticia',
                vencido: true,
            }),
            'PUT /docente/grupos/4/plazo-foto': errorHttp(422, {
                errors: { hasta: ['Máximo 180 días'] },
            }),
        });
        montar();

        fireEvent.click(
            await screen.findByRole('button', { name: 'Fotos vencidas · 12 sin foto' })
        );
        expect(screen.getByText('Venció el 20 oct 2026 · Blanco Coca Leticia')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        expect(await screen.findByText('Máximo 180 días')).toBeInTheDocument();
    });
});

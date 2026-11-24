import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ControlDelIdentificado from './ControlDelIdentificado';
import { api } from '../../api/cliente';
import { errorHttp, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

const BASE = { estudiante: { id: 7 }, examen: { id: 3 } };

function montar(veredicto, rutas = {}) {
    simularApi(api, rutas);
    return render(<ControlDelIdentificado aulaId={5} veredicto={{ ...BASE, ...veredicto }} />);
}

describe('ControlDelIdentificado', () => {
    it('compara el carnet y dice que coincide', async () => {
        montar(
            { veredicto: 'admitido' },
            {
                'POST /puerta/aulas/5/carnet': {
                    coincide: true,
                    sin_carnet: false,
                    incidencia: null,
                },
            }
        );

        fireEvent.click(screen.getByRole('button', { name: /Verificar carnet/ }));
        fireEvent.click(screen.getByRole('button', { name: 'Comparar carnet' }));
        expect(screen.getByText('Escriba el carnet')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();

        fireEvent.change(screen.getByLabelText(/Carnet que presenta/), {
            target: { value: '5003748' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Comparar carnet' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/puerta/aulas/5/carnet', {
                estudiante_id: 7,
                examen_id: 3,
                documento: '5003748',
            })
        );
        expect(await screen.findByText('Carnet coincide')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Foto del intruso/ })).not.toBeInTheDocument();
    });

    it('si no coincide avisa la suplantación y ofrece la foto de quien llegó', async () => {
        montar(
            { veredicto: 'admitido' },
            {
                'POST /puerta/aulas/5/carnet': {
                    coincide: false,
                    sin_carnet: false,
                    incidencia: { id: 41 },
                },
                'POST /puerta/aulas/5/incidencias/41/foto': {
                    message: 'Foto guardada.',
                    evidencias: 1,
                },
            }
        );

        fireEvent.click(screen.getByRole('button', { name: /Verificar carnet/ }));
        fireEvent.change(screen.getByLabelText(/Carnet que presenta/), {
            target: { value: '7777777' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Comparar carnet' }));

        expect(await screen.findByText(/No coincide/)).toBeInTheDocument();
        const boton = screen.getByRole('button', { name: /Foto del intruso/ });
        expect(boton).toBeInTheDocument();

        const foto = new File(['jpeg'], 'yo.jpg', { type: 'image/jpeg' });
        fireEvent.change(screen.getByLabelText('Foto del intruso', { selector: 'input' }), {
            target: { files: [foto] },
        });
        await waitFor(() =>
            expect(api.post).toHaveBeenLastCalledWith(
                '/puerta/aulas/5/incidencias/41/foto',
                expect.any(FormData)
            )
        );
        expect(await screen.findByRole('button', { name: /1\/3/ })).toBeInTheDocument();
    });

    it('sin carnet registrado lo dice', async () => {
        montar(
            { veredicto: 'no_habilitado' },
            {
                'POST /puerta/aulas/5/carnet': {
                    coincide: null,
                    sin_carnet: true,
                    incidencia: null,
                },
            }
        );

        fireEvent.click(screen.getByRole('button', { name: /Verificar carnet/ }));
        fireEvent.change(screen.getByLabelText(/Carnet que presenta/), {
            target: { value: '5003748' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Comparar carnet' }));

        expect(await screen.findByText(/Sin carnet registrado/)).toBeInTheDocument();
    });

    it('un rechazo sin la incidencia asentada ofrece la foto desde el veredicto', () => {
        montar({ veredicto: 'no_habilitado', incidencia: { id: 12 } });

        expect(screen.getByRole('button', { name: /Foto del intruso/ })).toBeInTheDocument();
    });

    it('un fallo al guardar la foto se dice', async () => {
        montar(
            { veredicto: 'ya_ingreso', incidencia: { id: 12 } },
            {
                'POST /puerta/aulas/5/incidencias/12/foto': errorHttp(422, {
                    errors: { foto: ['Ya tiene 3 fotos'] },
                }),
            }
        );

        const foto = new File(['jpeg'], 'yo.jpg', { type: 'image/jpeg' });
        fireEvent.change(screen.getByLabelText('Foto del intruso', { selector: 'input' }), {
            target: { files: [foto] },
        });
        expect(await screen.findByText('Ya tiene 3 fotos')).toBeInTheDocument();
    });

    it('un intento sin incidencia ni estudiante no ofrece nada', () => {
        const { container } = render(
            <ControlDelIdentificado aulaId={5} veredicto={{ veredicto: 'no_corresponde' }} />
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('el carnet solo admite número y complemento: lo demás no entra', async () => {
        montar({ veredicto: 'admitido' });
        fireEvent.click(screen.getByRole('button', { name: /Verificar carnet/ }));

        const campo = screen.getByLabelText(/Carnet que presenta/);
        fireEvent.change(campo, { target: { value: '5003748-1A#?' } });

        expect(campo).toHaveValue('5003748-1A');
    });
});

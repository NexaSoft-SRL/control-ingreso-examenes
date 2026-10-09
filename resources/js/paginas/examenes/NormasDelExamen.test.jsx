import PropTypes from 'prop-types';
import { useState } from 'react';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import NormasDelExamen from './NormasDelExamen';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

const PREDEFINIDAS = [
    { id: 1, texto: 'Documento de identidad a la vista', predefinida: true, propia: false },
    { id: 2, texto: 'Sin celular', predefinida: true, propia: false },
];
const PROPIA = { id: 7, texto: 'Hoja de fórmulas A4', predefinida: false, propia: true };

let plantillas;
const onAviso = vi.fn();

// El componente es controlado: aquí vive lo que marca el examen.
function Banco({ inicial = [], sueltas, guardadas }) {
    const [marcadas, setMarcadas] = useState(inicial);
    const [texto, setTexto] = useState('');
    const [conservadas, setConservadas] = useState((sueltas ?? []).map((n) => n.id));
    return (
        <>
            <NormasDelExamen
                marcadas={marcadas}
                onMarcadas={setMarcadas}
                texto={texto}
                onTexto={setTexto}
                sueltas={sueltas}
                conservadas={conservadas}
                onConservadas={setConservadas}
                guardadas={guardadas}
                onAviso={onAviso}
            />
            <output data-testid="marcadas">{marcadas.join(',')}</output>
            <output data-testid="conservadas">{conservadas.join(',')}</output>
        </>
    );
}

Banco.propTypes = {
    inicial: PropTypes.arrayOf(PropTypes.number),
    sueltas: PropTypes.array,
    guardadas: PropTypes.object,
};

const montar = (props = {}) => renderConSesion(<Banco {...props} />);
const casilla = (texto) => screen.findByRole('checkbox', { name: new RegExp(texto) });

beforeEach(() => {
    plantillas = [...PREDEFINIDAS, PROPIA];
    onAviso.mockReset();
    simularApi(api, {
        'GET /normas/plantillas': () => ({ data: plantillas }),
        'POST /normas/plantillas': ({ data }) => {
            const nueva = { id: 9, texto: data.texto, predefinida: false, propia: true };
            plantillas = [...plantillas, nueva];
            return { data: nueva, message: 'Plantilla creada.' };
        },
        'PUT /normas/plantillas/*': ({ url, data }) => {
            const id = Number(url.split('/').pop());
            plantillas = plantillas.map((p) => (p.id === id ? { ...p, texto: data.texto } : p));
            return { data: plantillas.find((p) => p.id === id), message: 'Plantilla guardada.' };
        },
        'DELETE /normas/plantillas/*': ({ url }) => {
            const id = Number(url.split('/').pop());
            plantillas = plantillas.filter((p) => p.id !== id);
            return '';
        },
    });
});

describe('NormasDelExamen', () => {
    it('muestra las predefinidas y las propias; solo las propias se editan y se quitan', async () => {
        montar();

        expect(await casilla('Documento de identidad a la vista')).not.toBeChecked();
        expect(await casilla('Sin celular')).toBeInTheDocument();
        expect(await casilla('Hoja de fórmulas A4')).toBeInTheDocument();

        expect(
            screen.getByRole('button', { name: 'Editar la plantilla Hoja de fórmulas A4' })
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Quitar la plantilla Hoja de fórmulas A4' })
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Editar la plantilla Sin celular' })
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Quitar la plantilla Sin celular' })
        ).not.toBeInTheDocument();
    });

    it('marca y desmarca; ninguna marcada es válido', async () => {
        montar();

        fireEvent.click(await casilla('Sin celular'));
        fireEvent.click(await casilla('Hoja de fórmulas A4'));
        expect(screen.getByTestId('marcadas')).toHaveTextContent('2,7');

        fireEvent.click(await casilla('Sin celular'));
        fireEvent.click(await casilla('Hoja de fórmulas A4'));
        expect(screen.getByTestId('marcadas')).toHaveTextContent('');
    });

    it('el texto libre lleva contador', async () => {
        montar();
        await casilla('Sin celular');

        const area = screen.getByLabelText('Otras normas');
        expect(screen.getByText('0/2000')).toBeInTheDocument();
        fireEvent.change(area, { target: { value: 'Mochilas al frente' } });
        expect(area).toHaveValue('Mochilas al frente');
        expect(screen.getByText('18/2000')).toBeInTheDocument();
    });

    it('crea una plantilla propia y la deja marcada', async () => {
        montar();
        await casilla('Sin celular');

        fireEvent.click(screen.getByRole('button', { name: /Nueva plantilla/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Nueva plantilla' });
        fireEvent.change(within(dialogo).getByLabelText(/Texto/), {
            target: { value: '  Práctica impresa  ' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(await casilla('Práctica impresa')).toBeChecked();
        expect(api.post).toHaveBeenCalledWith('/normas/plantillas', { texto: 'Práctica impresa' });
        expect(screen.getByTestId('marcadas')).toHaveTextContent('9');
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(onAviso).toHaveBeenCalledWith('Plantilla creada');
    });

    it('sin texto no la crea y señala el campo', async () => {
        montar();
        await casilla('Sin celular');

        fireEvent.click(screen.getByRole('button', { name: /Nueva plantilla/ }));
        const dialogo = screen.getByRole('dialog');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(within(dialogo).getByText('Obligatorio')).toBeInTheDocument();
        expect(within(dialogo).getByLabelText(/Texto/)).toHaveAttribute('aria-invalid', 'true');
        expect(api.post).not.toHaveBeenCalled();
    });

    it('con el texto de una que ya tiene, el 422 «Ya existe» va al campo', async () => {
        api.post.mockRejectedValueOnce(
            errorHttp(422, { message: 'Ya existe', errors: { texto: ['Ya existe'] } })
        );
        montar();
        await casilla('Sin celular');

        fireEvent.click(screen.getByRole('button', { name: /Nueva plantilla/ }));
        const dialogo = screen.getByRole('dialog');
        fireEvent.change(within(dialogo).getByLabelText(/Texto/), {
            target: { value: 'hoja de formulas a4' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(await within(dialogo).findByText('Ya existe')).toBeInTheDocument();
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByTestId('marcadas')).toHaveTextContent('');
    });

    it('edita una plantilla propia', async () => {
        montar();

        fireEvent.click(
            await screen.findByRole('button', { name: 'Editar la plantilla Hoja de fórmulas A4' })
        );
        const dialogo = screen.getByRole('dialog', { name: 'Editar plantilla' });
        const campo = within(dialogo).getByLabelText(/Texto/);
        expect(campo).toHaveValue('Hoja de fórmulas A4');
        fireEvent.change(campo, { target: { value: 'Hoja de fórmulas carta' } });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(await casilla('Hoja de fórmulas carta')).toBeInTheDocument();
        expect(api.put).toHaveBeenCalledWith('/normas/plantillas/7', {
            texto: 'Hoja de fórmulas carta',
        });
        expect(onAviso).toHaveBeenCalledWith('Plantilla guardada');
    });

    it('quita una plantilla propia con confirmación y la desmarca', async () => {
        montar({ inicial: [2, 7] });

        fireEvent.click(
            await screen.findByRole('button', { name: 'Quitar la plantilla Hoja de fórmulas A4' })
        );
        expect(api.delete).not.toHaveBeenCalled();
        const dialogo = screen.getByRole('dialog', { name: 'Quitar plantilla' });
        expect(within(dialogo).getByText('Hoja de fórmulas A4')).toBeInTheDocument();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Quitar' }));

        await waitFor(() =>
            expect(
                screen.queryByRole('checkbox', { name: /Hoja de fórmulas A4/ })
            ).not.toBeInTheDocument()
        );
        expect(api.delete).toHaveBeenCalledWith('/normas/plantillas/7');
        expect(screen.getByTestId('marcadas')).toHaveTextContent('2');
        expect(onAviso).toHaveBeenCalledWith('Plantilla quitada');
    });

    it('un rechazo del servidor queda en el diálogo', async () => {
        api.delete.mockRejectedValueOnce(
            errorHttp(403, { message: 'Esta plantilla no es tuya.', alcance: true })
        );
        montar();

        fireEvent.click(
            await screen.findByRole('button', { name: 'Quitar la plantilla Hoja de fórmulas A4' })
        );
        fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Quitar' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Esta plantilla no es tuya.');
        expect(await casilla('Hoja de fórmulas A4')).toBeInTheDocument();
    });

    it('las normas sin plantilla del examen se conservan o se quitan', async () => {
        montar({ sueltas: [{ id: 31, texto: 'Solo lápiz' }] });

        const suelta = await casilla('Solo lápiz');
        expect(suelta).toBeChecked();
        expect(screen.getByText('Sin plantilla')).toBeInTheDocument();
        fireEvent.click(suelta);
        expect(screen.getByTestId('conservadas')).toHaveTextContent('');
    });

    it('muestra el texto con que el examen guardó una plantilla que cambió', async () => {
        montar({ inicial: [7], guardadas: { 7: 'Hoja de fórmulas oficio', 2: 'Sin celular' } });

        expect(await casilla('Hoja de fórmulas A4')).toBeChecked();
        expect(screen.getByText('En el examen: Hoja de fórmulas oficio')).toBeInTheDocument();
        expect(screen.queryByText('En el examen: Sin celular')).not.toBeInTheDocument();
    });

    it('si no cargan las plantillas ofrece reintentar', async () => {
        api.get.mockRejectedValueOnce(errorHttp(500));
        montar();

        fireEvent.click(await screen.findByRole('button', { name: 'Reintentar' }));
        expect(await casilla('Sin celular')).toBeInTheDocument();
    });
});

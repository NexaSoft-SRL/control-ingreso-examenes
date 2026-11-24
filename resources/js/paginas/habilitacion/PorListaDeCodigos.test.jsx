import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PorListaDeCodigos from './PorListaDeCodigos';
import { api } from '../../api/cliente';
import { errorHttp, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

const RESPUESTA = {
    message: '2 inhabilitados. 1 código no es del examen.',
    afectados: 2,
    encontrados: 2,
    no_encontrados: ['199999999'],
};

function abrir(rutas = {}, alCambiar = vi.fn()) {
    simularApi(api, { 'POST /examenes/8/habilitaciones/por-codigos': RESPUESTA, ...rutas });
    render(<PorListaDeCodigos examenId={8} alCambiar={alCambiar} />);
    fireEvent.click(screen.getByRole('button', { name: /Por lista de códigos/ }));
    return alCambiar;
}

describe('PorListaDeCodigos', () => {
    it('pide los códigos y, al inhabilitar, el motivo', () => {
        abrir();

        fireEvent.click(screen.getByRole('button', { name: 'Aplicar' }));
        expect(screen.getByText('Pegue al menos un código')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();

        fireEvent.change(screen.getByLabelText(/Códigos SIS/), {
            target: { value: '202100417\n202101256' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Aplicar' }));
        expect(screen.getByText('Mínimo 5 caracteres')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('envía la lista con su motivo y muestra los códigos que no son del examen', async () => {
        const alCambiar = abrir();

        fireEvent.change(screen.getByLabelText(/Códigos SIS/), {
            target: { value: '202100417\n202101256\n199999999' },
        });
        fireEvent.change(screen.getByLabelText(/^Motivo/), {
            target: { value: 'No cumplió las prácticas' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Aplicar' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/examenes/8/habilitaciones/por-codigos', {
                habilitado: false,
                codigos: '202100417\n202101256\n199999999',
                motivo: 'No cumplió las prácticas',
            })
        );
        expect(
            await screen.findByText('2 inhabilitados. 1 código no es del examen.')
        ).toBeInTheDocument();
        expect(screen.getByText('199999999')).toBeInTheDocument();
        expect(alCambiar).toHaveBeenCalled();
    });

    it('el resultado queda en el diálogo con sus cifras hasta cerrarlo', async () => {
        abrir({
            'POST /examenes/8/habilitaciones/por-codigos': {
                message: '1 habilitado. 1 código no es del examen. 1 sin carnet no se habilitó.',
                afectados: 1,
                encontrados: 2,
                sin_carnet: 1,
                no_encontrados: ['199999999'],
            },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Habilitar' }));
        fireEvent.change(screen.getByLabelText(/Códigos SIS/), {
            target: { value: '202100417\n202101256\n199999999' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Aplicar' }));

        const resultado = within(await screen.findByRole('dialog')).getByRole('status');
        expect(resultado).toHaveTextContent('1 habilitado. 1 código no es del examen.');
        expect(resultado).toHaveTextContent('En el examen2');
        expect(resultado).toHaveTextContent('Habilitados1');
        expect(resultado).toHaveTextContent('Sin carnet · no se habilitaron1');
        expect(resultado).toHaveTextContent('No son del examen1');
        // No se cierra solo: queda hasta pulsar «Cerrar».
        expect(screen.getByRole('dialog', { name: 'Por lista de códigos' })).toBeInTheDocument();
        fireEvent.click(screen.getAllByRole('button', { name: 'Cerrar' }).at(-1));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('si no cambió a nadie lo dice con claridad', async () => {
        abrir({
            'POST /examenes/8/habilitaciones/por-codigos': {
                message: '0 habilitados. 1 sin carnet no se habilitó.',
                afectados: 0,
                encontrados: 1,
                sin_carnet: 1,
                no_encontrados: [],
            },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Habilitar' }));
        fireEvent.change(screen.getByLabelText(/Códigos SIS/), { target: { value: '202100417' } });
        fireEvent.click(screen.getByRole('button', { name: 'Aplicar' }));

        const resultado = within(await screen.findByRole('dialog')).getByRole('status');
        expect(resultado).toHaveTextContent('Ninguno cambió. 0 habilitados.');
        expect(resultado).toHaveTextContent('Sin carnet · no se habilitaron1');
        expect(resultado).not.toHaveTextContent('No son del examen');
    });

    it('habilitar no pide motivo', async () => {
        abrir({
            'POST /examenes/8/habilitaciones/por-codigos': {
                ...RESPUESTA,
                message: '2 habilitados.',
                no_encontrados: [],
            },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Habilitar' }));
        expect(screen.queryByLabelText(/^Motivo/)).not.toBeInTheDocument();
        fireEvent.change(screen.getByLabelText(/Códigos SIS/), { target: { value: '202100417' } });
        fireEvent.click(screen.getByRole('button', { name: 'Aplicar' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/examenes/8/habilitaciones/por-codigos', {
                habilitado: true,
                codigos: '202100417',
            })
        );
    });

    it('un rechazo del servidor se dice sin cerrar el diálogo', async () => {
        abrir({
            'POST /examenes/8/habilitaciones/por-codigos': errorHttp(422, {
                errors: { codigos: ['Ningún código de 5 a 10 dígitos'] },
            }),
        });

        fireEvent.change(screen.getByLabelText(/Códigos SIS/), { target: { value: 'abc' } });
        fireEvent.change(screen.getByLabelText(/^Motivo/), { target: { value: 'No cumplió' } });
        fireEvent.click(screen.getByRole('button', { name: 'Aplicar' }));

        expect(await screen.findByText('Ningún código de 5 a 10 dígitos')).toBeInTheDocument();
    });
});

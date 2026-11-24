import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { api } from '../../api/cliente';
import { errorHttp, simularApi } from '../../test/apoyo';
import ChoquesDeEstudiantes from './ChoquesDeEstudiantes';

vi.mock('../../api/cliente');

const DATOS = { grupos: [37, 200], fecha: '2026-12-08', hora: '08:15', duracion: 90 };

const OTRO = [{ examen: 'Física I · Primer parcial', inicio: '08:15', fin: '09:45' }];
const dos = {
    total: 2,
    estudiantes: [
        { nombre: 'Aguilar Rojas Cristian', codigo: '202100123', examenes: OTRO },
        { nombre: 'Vargas Arce Sergio', codigo: '202100456', examenes: OTRO },
    ],
};

const montar = (propiedades = {}, respuesta = dos) => {
    simularApi(api, { 'GET /examenes/opciones/choques': respuesta });
    return render(<ChoquesDeEstudiantes {...DATOS} {...propiedades} />);
};

describe('ChoquesDeEstudiantes', () => {
    it('avisa cuántos estudiantes tienen otro examen a esa hora', async () => {
        montar();

        expect(
            await screen.findByText('2 estudiantes tienen otro examen a esa hora')
        ).toBeInTheDocument();
        expect(screen.queryByText(/Aguilar Rojas/)).not.toBeInTheDocument();
    });

    it('en singular dice «tiene»', async () => {
        montar({}, { total: 1, estudiantes: [dos.estudiantes[0]] });

        expect(
            await screen.findByText('1 estudiante tiene otro examen a esa hora')
        ).toBeInTheDocument();
    });

    it('«Ver quiénes» muestra nombre, código y el otro examen con su horario', async () => {
        montar();
        fireEvent.click(await screen.findByRole('button', { name: /Ver quiénes/ }));

        expect(screen.getByText('Aguilar Rojas Cristian · 202100123')).toBeInTheDocument();
        expect(screen.getByText('Vargas Arce Sergio · 202100456')).toBeInTheDocument();
        expect(screen.getAllByText('Física I · Primer parcial · 08:15–09:45')).toHaveLength(2);

        fireEvent.click(screen.getByRole('button', { name: /Ocultar/ }));
        expect(screen.queryByText(/Aguilar Rojas/)).not.toBeInTheDocument();
    });

    it('si hay más de los que se listan, dice cuántos faltan', async () => {
        montar({}, { total: 55, estudiantes: dos.estudiantes });
        fireEvent.click(await screen.findByRole('button', { name: /Ver quiénes/ }));

        expect(screen.getByText('y 53 más')).toBeInTheDocument();
    });

    it('muestra la guía que le da quien lo usa', async () => {
        montar({ guia: 'Podrá inhabilitarlos con motivo en Habilitación' });

        expect(
            await screen.findByText('Podrá inhabilitarlos con motivo en Habilitación')
        ).toBeInTheDocument();
    });

    it('envía los grupos, la fecha, la hora, la duración y el examen que se edita', async () => {
        montar({ examenId: 5 });
        await screen.findByRole('status');

        expect(api.get).toHaveBeenCalledWith('/examenes/opciones/choques', {
            params: {
                grupos: [37, 200],
                fecha: '2026-12-08',
                hora_inicio: '08:15',
                duracion_minutos: 90,
                examen_id: 5,
            },
        });
    });

    it('sin choques no muestra nada', async () => {
        montar({}, { total: 0, estudiantes: [] });
        await vi.waitFor(() => expect(api.get).toHaveBeenCalled());

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('si la consulta falla no molesta: no muestra nada', async () => {
        simularApi(api, {
            'GET /examenes/opciones/choques': errorHttp(500, { message: 'Error' }),
        });
        render(<ChoquesDeEstudiantes {...DATOS} />);
        await vi.waitFor(() => expect(api.get).toHaveBeenCalled());

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it.each([
        ['sin grupos', { grupos: [] }],
        ['sin hora', { hora: '' }],
        ['con una duración fuera de rango', { duracion: 5 }],
        ['desactivado', { activa: false }],
    ])('no consulta %s', (_, cambios) => {
        montar(cambios);

        expect(api.get).not.toHaveBeenCalled();
    });
});

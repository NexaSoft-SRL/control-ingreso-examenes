import { fireEvent, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DetalleDeAula from './DetalleDeAula';
import { api } from '../../api/cliente';
import { renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const AULA = { id: 32, nombre: '691B', ubicacion: 'Edificio Académico 2 · 1° Piso' };

let agenda;

const montar = (props = {}) =>
    renderConSesion(
        <DetalleDeAula
            aula={AULA}
            fecha="2026-12-07"
            hora="08:15"
            duracion={90}
            elegida={false}
            onAlternar={vi.fn()}
            onCerrar={vi.fn()}
            {...props}
        />,
        { usuario: usuarioDePrueba('Docente'), ruta: '/examenes/nuevo' }
    );

beforeEach(() => {
    agenda = { clases: [], examenes: [] };
    simularApi(api, {
        'GET /calendario/aulas': () => ({ aulas: { 32: agenda } }),
    });
});

describe('DetalleDeAula', () => {
    it('un aula sin nada ese día está libre', async () => {
        montar();

        expect(await screen.findByText('Libre a esa hora')).toBeVisible();
        expect(screen.getByText('Sin clases ni exámenes ese día')).toBeVisible();
        expect(screen.getByText('Edificio Académico 2 · 1° Piso')).toBeVisible();
        expect(screen.getByText(/tu examen de 08:15 a 09:45/)).toBeVisible();
        expect(api.get).toHaveBeenCalledWith(
            '/calendario/aulas',
            expect.objectContaining({
                params: expect.objectContaining({
                    desde: '2026-12-07',
                    hasta: '2026-12-07',
                    aulas: [32],
                }),
            })
        );
    });

    it('lista las clases y los exámenes del día y marca los que coinciden', async () => {
        agenda = {
            clases: [
                {
                    fecha: '2026-12-07',
                    inicio: '07:00',
                    fin: '08:00',
                    asignatura: 'Cálculo I',
                    grupo: '1',
                    docente: 'Mendez Paz Luis',
                    auxiliatura: false,
                },
                {
                    fecha: '2026-12-07',
                    inicio: '09:00',
                    fin: '10:30',
                    asignatura: 'Física I',
                    grupo: '2',
                    docente: 'Ríos Vega Ana',
                    auxiliatura: true,
                },
            ],
            examenes: [
                {
                    id: null,
                    fecha: '2026-12-07',
                    inicio: '14:00',
                    fin: '15:30',
                    asignatura: 'Química General',
                    tipo: 'Primer parcial',
                    docente: 'Torrez Vaca Marco',
                    propio: false,
                },
            ],
        };
        montar();

        expect(await screen.findByText('Ocupada a esa hora')).toBeVisible();
        const lista = screen.getByRole('list', { name: 'Agenda del aula' });
        const filas = within(lista).getAllByRole('listitem');
        // En orden de hora.
        expect(filas.map((f) => f.textContent.slice(0, 11))).toEqual([
            '07:00–08:00',
            '09:00–10:30',
            '14:00–15:30',
        ]);
        expect(filas[0]).toHaveTextContent('Clase');
        expect(filas[0]).toHaveTextContent('Cálculo I');
        expect(filas[0]).toHaveTextContent('Grupo 1 · Mendez Paz Luis');
        expect(filas[0]).not.toHaveTextContent('Coincide');
        // La auxiliatura de 09:00 pisa el final del examen propuesto (08:15–09:45).
        expect(filas[1]).toHaveTextContent('Auxiliatura');
        expect(filas[1]).toHaveTextContent('Coincide 09:00–09:45 (45 min)');
        expect(filas[2]).toHaveTextContent('Examen');
        expect(filas[2]).toHaveTextContent('Primer parcial · Química General');
        expect(filas[2]).toHaveTextContent('Torrez Vaca Marco');
    });

    it('ofrece elegir o quitar el aula y ver su semana', async () => {
        const onAlternar = vi.fn();
        const onCerrar = vi.fn();
        montar({ onAlternar, onCerrar });

        expect(await screen.findByRole('link', { name: 'Ver la semana' })).toHaveAttribute(
            'href',
            '/calendario?aula=32'
        );
        fireEvent.click(screen.getByRole('button', { name: 'Elegir esta aula' }));
        expect(onAlternar).toHaveBeenCalledTimes(1);
        expect(onCerrar).toHaveBeenCalledTimes(1);
    });

    it('con el aula ya elegida dice «Quitar del examen»', async () => {
        montar({ elegida: true });

        expect(await screen.findByRole('button', { name: 'Quitar del examen' })).toBeVisible();
    });

    it('un aula fija no ofrece elegir ni quitar', async () => {
        montar({ onAlternar: undefined });

        await screen.findByText('Libre a esa hora');
        expect(
            screen.queryByRole('button', { name: /Elegir esta aula|Quitar del examen/ })
        ).toBeNull();
    });
});

import { screen } from '@testing-library/react';
import { ScanLine } from 'lucide-react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SinActividad from './SinActividad';
import { api } from '../api/cliente';
import { renderConSesion, simularApi, usuarioDePrueba } from '../test/apoyo';

vi.mock('../api/cliente');

const TALLER = { id: 53, nombre: 'Taller de Ingeniería de Software' };

beforeEach(() => {
    simularApi(api, {
        'GET /examenes': () => ({
            data: [
                {
                    id: 1,
                    asignatura: TALLER,
                    tipo_texto: 'Segundo parcial',
                    fecha: '2026-12-07',
                    hora: '08:15',
                },
                {
                    id: 2,
                    asignatura: TALLER,
                    tipo_texto: 'Primer parcial',
                    fecha: '2026-10-28',
                    hora: '08:15',
                },
                {
                    id: 3,
                    asignatura: TALLER,
                    tipo_texto: 'Examen pasado',
                    fecha: '2026-10-01',
                    hora: '08:15',
                    rendido: true,
                },
            ],
            meta: { hoy: '2026-10-27' },
        }),
    });
});

const montar = (usuario) =>
    renderConSesion(
        <SinActividad
            Icono={ScanLine}
            titulo="No hay exámenes hoy"
            detalle="Se abre el día del examen."
        />,
        { usuario }
    );

describe('SinActividad', () => {
    it('dice qué pasa, por qué y cuál es el próximo examen', async () => {
        montar(usuarioDePrueba('Docente'));

        expect(screen.getByText('No hay exámenes hoy')).toBeInTheDocument();
        expect(screen.getByText('Se abre el día del examen.')).toBeInTheDocument();
        expect(
            await screen.findByText(
                'Próximo: Primer parcial · Taller de Ingeniería de Software · miércoles 28 de octubre, 08:15'
            )
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Ver exámenes' })).toHaveAttribute(
            'href',
            '/examenes'
        );
    });

    it('quien no registra exámenes no ve el próximo ni el enlace', () => {
        montar(usuarioDePrueba('Auxiliar'));

        expect(screen.getByText('No hay exámenes hoy')).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Ver exámenes' })).not.toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalled();
    });
});

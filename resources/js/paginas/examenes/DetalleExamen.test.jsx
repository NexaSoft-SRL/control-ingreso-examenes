import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DetalleExamen from './DetalleExamen';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const TALLER = { id: 53, codigo: '2010024', nombre: 'Taller de Ingeniería de Software' };

function examen(cambios) {
    return {
        id: 24,
        asignatura: TALLER,
        tipo: 'PRIMER_PARCIAL',
        tipo_texto: 'Primer parcial',
        fecha: '2026-10-28',
        hora: '08:15',
        duracion: 90,
        grupos: ['1'],
        inscritos: 6,
        aulas: ['690B', '690C'],
        auxiliares: 1,
        habilitados: 6,
        no_habilitados: 0,
        qr_emitidos: 0,
        avance: { grupos: 'ok', aulas: 'ok', auxiliares: 'ok', habilitacion: 'ok', qr: 'ahora' },
        estado: 'Faltan códigos QR',
        accion: { clave: 'emitir_qr', paso: null },
        propio: true,
        registrado_por: 'Flores Villarroel Corina',
        junto_con: 0,
        es_hoy: false,
        rendido: false,
        ...cambios,
    };
}

let examenes;

const montar = (ruta = '/examenes/detalle?examen=24', usuario = usuarioDePrueba('Docente')) =>
    renderConSesion(<DetalleExamen />, { usuario, ruta });
const pestana = (nombre) => screen.findByRole('tab', { name: nombre });

beforeEach(() => {
    examenes = [examen()];
    simularApi(api, {
        'GET /examenes': () => ({ data: examenes, meta: { periodo: '2/2026' } }),
        'GET /examenes/*/habilitaciones': () => ({
            data: [],
            meta: { total: 0, cifras: { inscritos: 6, habilitados: 6 } },
        }),
        'GET /examenes/*/codigos-qr': () => ({
            data: [],
            meta: { total: 0, cifras: { habilitados: 6, emitidos: 0 } },
        }),
        'DELETE /examenes/*': () => ({ message: 'Examen eliminado.' }),
    });
});

describe('DetalleExamen', () => {
    it('reúne el examen: título, estado, pestañas con sus cifras y el paso siguiente', async () => {
        montar();

        expect(
            await screen.findByRole('heading', {
                name: /Primer parcial · Taller de Ingeniería de Software/,
            })
        ).toBeInTheDocument();
        expect(screen.getByText('Faltan códigos QR')).toBeInTheDocument();
        expect(await pestana(/^Resumen/)).toHaveAttribute('aria-selected', 'true');
        expect(await pestana(/^Habilitación/)).toHaveTextContent('6/6');
        expect(await pestana(/^Códigos QR/)).toHaveTextContent('0/6');
        expect(screen.getByRole('link', { name: /Emitir códigos QR/ })).toHaveAttribute(
            'href',
            '/examenes/detalle?examen=24&pestana=qr'
        );
        // Los pasos llevan a su lugar: el registro o la pestaña.
        expect(screen.getByRole('link', { name: /Aulas/ })).toHaveAttribute(
            'href',
            '/examenes/nuevo?examen=24&paso=2'
        );
        expect(screen.getByRole('link', { name: /^Habilitación/ })).toHaveAttribute(
            'href',
            '/examenes/detalle?examen=24&pestana=habilitacion'
        );
        expect(screen.getByRole('link', { name: /Editar/ })).toHaveAttribute(
            'href',
            '/examenes/nuevo?examen=24'
        );
    });

    it('cambia de pestaña sin salir del examen', async () => {
        montar();
        fireEvent.click(await pestana(/^Habilitación/));

        expect(await pestana(/^Habilitación/)).toHaveAttribute('aria-selected', 'true');
        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith('/examenes/24/habilitaciones', expect.anything())
        );

        fireEvent.click(await pestana(/^Códigos QR/));
        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith('/examenes/24/codigos-qr', expect.anything())
        );
    });

    it('abre la pestaña pedida en la dirección', async () => {
        montar('/examenes/detalle?examen=24&pestana=habilitacion');

        expect(await pestana(/^Habilitación/)).toHaveAttribute('aria-selected', 'true');
    });

    it('sin aulas, habilitación y códigos QR esperan y se queda en el resumen', async () => {
        examenes = [
            examen({ aulas: [], habilitados: 0, accion: { clave: 'elegir_aulas', paso: 2 } }),
        ];
        montar('/examenes/detalle?examen=24&pestana=qr');

        expect(await pestana(/^Resumen/)).toHaveAttribute('aria-selected', 'true');
        expect(await pestana(/^Habilitación/)).toBeDisabled();
        expect(await pestana(/^Códigos QR/)).toBeDisabled();
    });

    it('sin el permiso de códigos QR esa pestaña no se abre', async () => {
        montar(
            '/examenes/detalle?examen=24',
            usuarioDePrueba('Docente', { permisos: ['examenes', 'habilitacion'] })
        );

        expect(await pestana(/^Códigos QR/)).toBeDisabled();
        expect(await pestana(/^Habilitación/)).toBeEnabled();
    });

    it('un examen ya tomado lleva a su reporte', async () => {
        examenes = [examen({ rendido: true })];
        montar(
            '/examenes/detalle?examen=24',
            usuarioDePrueba('Docente', { permisos: ['examenes', 'reportes_examenes'] })
        );

        expect(await screen.findByRole('link', { name: /Reporte/ })).toHaveAttribute(
            'href',
            '/reportes?examen=24'
        );
        expect(screen.queryByRole('link', { name: /En curso/ })).not.toBeInTheDocument();
    });

    it('el examen de hoy con aulas ofrece «En curso»', async () => {
        examenes = [examen({ es_hoy: true })];
        montar();

        expect(await screen.findByRole('link', { name: /En curso/ })).toHaveAttribute(
            'href',
            '/en-curso?examen=24'
        );
        expect(screen.queryByRole('link', { name: /Reporte/ })).not.toBeInTheDocument();
    });

    it('con el permiso de reportes enlaza la bitácora del examen', async () => {
        montar(
            '/examenes/detalle?examen=24',
            usuarioDePrueba('Docente', { permisos: ['examenes', 'reportes_examenes'] })
        );

        expect(await screen.findByRole('link', { name: /Bitácora/ })).toHaveAttribute(
            'href',
            '/bitacora-examen?examen=24'
        );
    });

    it('un examen que no está en la lista no se encuentra', async () => {
        montar('/examenes/detalle?examen=999');

        expect(await screen.findByText('Examen no encontrado')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Exámenes/ })).toHaveAttribute('href', '/examenes');
    });
});

describe('DetalleExamen · editar y eliminar', () => {
    const titulo = () =>
        screen.findByRole('heading', { name: /Primer parcial · Taller de Ingeniería de Software/ });
    const pedirEliminar = async () => {
        await titulo();
        fireEvent.click(screen.getByRole('button', { name: /Eliminar/ }));
        return screen.getByRole('dialog', { name: 'Eliminar examen' });
    };

    it('elimina con confirmación y nombra lo que se pierde', async () => {
        examenes = [examen({ codigos_aula: 2 })];
        montar();
        const dialogo = await pedirEliminar();

        expect(api.delete).not.toHaveBeenCalled();
        expect(dialogo).toHaveTextContent('miércoles 28 de octubre, 08:15');
        expect(dialogo).toHaveTextContent('2 aulas · 6 habilitaciones · 2 códigos de aula');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Eliminar' }));

        await waitFor(() => expect(api.delete).toHaveBeenCalledWith('/examenes/24'));
    });

    it('cancelar no elimina', async () => {
        montar();
        const dialogo = await pedirEliminar();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cancelar' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(api.delete).not.toHaveBeenCalled();
    });

    it('si el servidor lo rechaza muestra el motivo y el examen sigue', async () => {
        api.delete.mockRejectedValueOnce(
            errorHttp(409, {
                message: 'El examen ya tiene ingresos registrados: no se puede eliminar.',
                codigo: 'EXAMEN_CON_INGRESOS',
            })
        );
        montar();
        const dialogo = await pedirEliminar();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Eliminar' }));

        expect(await within(dialogo).findByRole('alert')).toHaveTextContent(
            'El examen ya tiene ingresos registrados: no se puede eliminar.'
        );
    });

    it('con ingresos o incidencias se edita pero no se elimina', async () => {
        examenes = [examen({ con_incidencias: true })];
        montar();
        await titulo();

        expect(screen.getByRole('link', { name: /Editar/ })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Eliminar/ })).not.toBeInTheDocument();
    });

    it('solo quien lo registró lo edita o lo elimina', async () => {
        examenes = [examen({ propio: false, registrado_por: 'Rodriguez Bilbao Erika Patricia' })];
        montar();
        await titulo();

        expect(screen.queryByRole('link', { name: /Editar/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Eliminar/ })).not.toBeInTheDocument();
    });
});

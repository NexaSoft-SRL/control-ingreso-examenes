import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
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
        // Quien registró un examen que aún no se toma lo prepara en el asistente;
        // esta página es para los ya tomados y para los de otro docente.
        rendido: true,
        ...cambios,
    };
}

let examenes;
// GET /examenes/opciones/choques: los estudiantes con otro examen a esa hora.
let choques;

const montar = (ruta = '/examenes/detalle?examen=24', usuario = usuarioDePrueba('Docente')) =>
    renderConSesion(<DetalleExamen />, { usuario, ruta });
const pestana = (nombre) => screen.findByRole('tab', { name: nombre });

beforeEach(() => {
    examenes = [examen()];
    choques = { total: 0, estudiantes: [] };
    simularApi(api, {
        'GET /examenes': () => ({ data: examenes, meta: { periodo: '2/2026' } }),
        'GET /examenes/24': () => ({ data: examen({ grupos_detalle: [{ id: 5 }, { id: 6 }] }) }),
        'GET /examenes/opciones/choques': () => choques,
        'GET /examenes/*/habilitaciones': () => ({
            data: [],
            meta: { total: 0, cifras: { inscritos: 6, habilitados: 6 } },
        }),
        'GET /examenes/*/codigos-qr': () => ({
            data: [],
            meta: { total: 0, cifras: { habilitados: 6, emitidos: 0 } },
        }),
        'DELETE /examenes/*': () => ({ message: 'Examen eliminado.' }),
        'POST /examenes/*/salir': () => ({ message: 'Salió del examen.' }),
    });
});

describe('DetalleExamen', () => {
    it('avisa de los estudiantes con otro examen a esa hora y lleva a Habilitación', async () => {
        choques = {
            total: 3,
            estudiantes: [
                {
                    nombre: 'Aguilar Rojas Cristian',
                    codigo: '202100123',
                    examenes: [
                        { examen: 'Física I · Primer parcial', inicio: '08:15', fin: '09:45' },
                    ],
                },
            ],
        };
        // Un colaborador de un examen que aún no se toma.
        examenes = [examen({ propio: false, rendido: false })];
        montar();

        expect(
            await screen.findByText('3 estudiantes tienen otro examen a esa hora')
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Inhabilitarlos con motivo en Habilitación' })
        ).toHaveAttribute('href', '/examenes/detalle?examen=24&pestana=habilitacion');
        expect(api.get).toHaveBeenCalledWith(
            '/examenes/opciones/choques',
            expect.objectContaining({
                params: expect.objectContaining({ grupos: [5, 6], examen_id: 24 }),
            })
        );
    });

    it('reúne el examen: título, estado, pestañas con sus cifras y el paso siguiente', async () => {
        examenes = [examen({ propio: false, rendido: false })];
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
        // Los pasos del registro solo llevan a quien lo registró.
        expect(screen.queryByRole('link', { name: /Aulas/ })).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: /^Habilitación/ })).toHaveAttribute(
            'href',
            '/examenes/detalle?examen=24&pestana=habilitacion'
        );
        // Quien no lo registró no lo edita.
        expect(screen.queryByRole('link', { name: /Editar/ })).not.toBeInTheDocument();
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
            '/mis-reportes?examen=24'
        );
    });

    it('un examen que no está en la lista no se encuentra', async () => {
        montar('/examenes/detalle?examen=999');

        expect(await screen.findByText('Examen no encontrado')).toBeInTheDocument();
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

    it('un examen ya rendido dice «Rendido» y lleva a su reporte', async () => {
        examenes = [examen({ rendido: true })];
        montar();

        expect(await screen.findByText('Rendido')).toBeInTheDocument();
        expect(screen.queryByText('Faltan códigos QR')).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Ver reporte/ })).toHaveAttribute(
            'href',
            '/mis-reportes?examen=24'
        );
    });

    it('las pestañas se desplazan en el teléfono en vez de pisarse', async () => {
        montar();

        const lista = await screen.findByRole('tablist', { name: 'Secciones del examen' });
        expect(lista).toHaveClass('overflow-x-auto');
        expect(await pestana(/^Resumen/)).toHaveClass('shrink-0');
    });
});

describe('DetalleExamen · colaboración', () => {
    it('quien colabora con un grupo suyo puede salirse, con confirmación', async () => {
        examenes = [examen({ propio: false })];
        montar();

        expect(screen.queryByRole('button', { name: /Eliminar/ })).not.toBeInTheDocument();
        fireEvent.click(await screen.findByRole('button', { name: /Salir del examen/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Salir del examen' });
        expect(api.post).not.toHaveBeenCalledWith('/examenes/24/salir');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Salir' }));

        await waitFor(() => expect(api.post).toHaveBeenCalledWith('/examenes/24/salir'));
    });

    it('con ingresos registrados ya no se puede salir, y quien lo registró no ve el botón', async () => {
        examenes = [examen({ propio: false, con_ingresos: true })];
        montar();
        await screen.findByRole('heading', { name: /Primer parcial · Taller/ });
        expect(screen.queryByRole('button', { name: /Salir del examen/ })).not.toBeInTheDocument();
    });

    it('quien registró el examen ve cuántas invitaciones siguen pendientes', async () => {
        examenes = [examen({ invitados_pendientes: 2 })];
        montar();

        expect(await screen.findByText(/2 invitaciones pendientes a otros docentes/)).toBeVisible();
        expect(screen.queryByRole('button', { name: /Salir del examen/ })).not.toBeInTheDocument();
    });
});

describe('DetalleExamen · el asistente prepara los exámenes que aún no se toman', () => {
    function Aqui() {
        const { pathname, search } = useLocation();
        return <p>Estoy en {pathname + search}</p>;
    }
    const montarConRutas = (ruta) =>
        renderConSesion(
            <Routes>
                <Route path="/examenes/detalle" element={<DetalleExamen />} />
                <Route path="/examenes/nuevo" element={<Aqui />} />
            </Routes>,
            { usuario: usuarioDePrueba('Docente'), ruta }
        );

    it('abre en el primer paso que falta', async () => {
        // Los códigos QR son lo que falta.
        examenes = [examen({ rendido: false })];
        montarConRutas('/examenes/detalle?examen=24');

        expect(await screen.findByText('Estoy en /examenes/nuevo?examen=24&paso=qr')).toBeVisible();
    });

    it('con todo listo abre en «Listo»', async () => {
        examenes = [
            examen({
                rendido: false,
                avance: {
                    grupos: 'ok',
                    aulas: 'ok',
                    auxiliares: 'ok',
                    habilitacion: 'ok',
                    qr: 'ok',
                },
            }),
        ];
        montarConRutas('/examenes/detalle?examen=24');

        expect(await screen.findByText('Estoy en /examenes/nuevo?examen=24&paso=4')).toBeVisible();
    });

    it('las pestañas de habilitación y códigos QR llevan a su paso', async () => {
        examenes = [examen({ rendido: false })];
        montarConRutas('/examenes/detalle?examen=24&pestana=habilitacion');

        expect(
            await screen.findByText('Estoy en /examenes/nuevo?examen=24&paso=habilitar')
        ).toBeVisible();
    });

    it('la bitácora y lo de sin conexión también son pasos del asistente', async () => {
        examenes = [examen({ rendido: false })];
        const { unmount } = montarConRutas('/examenes/detalle?examen=24&pestana=bitacora');
        expect(
            await screen.findByText('Estoy en /examenes/nuevo?examen=24&paso=bitacora')
        ).toBeVisible();
        unmount();

        montarConRutas('/examenes/detalle?examen=24&pestana=sin-conexion');
        expect(
            await screen.findByText('Estoy en /examenes/nuevo?examen=24&paso=sin-conexion')
        ).toBeVisible();
    });

    it('los exámenes ya tomados se quedan en esta página', async () => {
        examenes = [examen({ rendido: true })];
        montarConRutas('/examenes/detalle?examen=24');
        expect(
            await screen.findByRole('heading', { name: /Primer parcial · Taller/ })
        ).toBeInTheDocument();
    });

    it('quien colabora sigue viendo la página con sus pestañas', async () => {
        examenes = [examen({ propio: false, rendido: false })];
        montarConRutas('/examenes/detalle?examen=24');

        expect(
            await screen.findByRole('heading', { name: /Primer parcial · Taller/ })
        ).toBeInTheDocument();
    });
});

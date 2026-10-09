import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Bitacora from './Bitacora';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const operaciones = [
    {
        id: 2,
        usuario: { id: 7, name: 'Jofre Ticona', email: 'jofre@umss.edu.bo' },
        operacion: 'asignatura.eliminar',
        tabla_afectada: 'asignaturas',
        registro_id: 12,
        descripcion: null,
        fecha_operacion: '2026-09-17 16:57:00',
    },
    {
        id: 1,
        usuario: null,
        operacion: 'asignatura.registrar',
        tabla_afectada: 'asignaturas',
        registro_id: 12,
        descripcion: null,
        fecha_operacion: '2026-09-16 09:05:00',
    },
];

const montar = () =>
    renderConSesion(<Bitacora />, { usuario: usuarioDePrueba('Administrador'), ruta: '/bitacora' });

beforeEach(() => {
    simularApi(api, { 'GET /bitacora': { data: operaciones } });
});

describe('Bitacora', () => {
    it('carga las operaciones desde la API', async () => {
        montar();

        expect(await screen.findByText('2 eventos')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/bitacora', { params: {} });
        expect(screen.getByText('17/Sep/2026 16:57')).toBeInTheDocument();
        expect(screen.getAllByText('Asignatura #12')).toHaveLength(2);
        expect(screen.getByText('Usuario eliminado')).toBeInTheDocument();
    });

    it('envía los filtros al backend', async () => {
        montar();
        await screen.findByText('2 eventos');

        fireEvent.change(screen.getByLabelText('Usuario'), { target: { value: '7' } });
        fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-09-17' } });
        fireEvent.change(screen.getByLabelText('Hasta'), { target: { value: '2026-09-20' } });
        fireEvent.change(screen.getByLabelText('Operación'), {
            target: { value: 'asignatura.eliminar' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Filtrar' }));

        await waitFor(() =>
            expect(api.get).toHaveBeenLastCalledWith('/bitacora', {
                params: {
                    usuario_id: '7',
                    desde: '2026-09-17',
                    hasta: '2026-09-20',
                    operacion: 'asignatura.eliminar',
                },
            })
        );
    });

    it('muestra el estado vacío', async () => {
        simularApi(api, { 'GET /bitacora': { data: [] } });
        montar();

        expect(await screen.findByText('Sin eventos')).toBeInTheDocument();
        expect(screen.queryByText(/^\d+ eventos?$/)).toBeNull();
    });

    it('muestra el esqueleto mientras carga', () => {
        montar();
        expect(screen.getByRole('status', { name: 'Cargando' })).toBeInTheDocument();
    });

    it('si falla la carga ofrece reintentar con los mismos filtros', async () => {
        simularApi(api, { 'GET /bitacora': errorHttp(500) });
        montar();

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar');

        simularApi(api, { 'GET /bitacora': { data: operaciones } });
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(await screen.findByText('2 eventos')).toBeInTheDocument();
    });

    it('marca los filtros que el servidor rechaza', async () => {
        simularApi(api, { 'GET /bitacora': errorHttp(422, { errors: { desde: ['x'] } }) });
        montar();

        expect(await screen.findByRole('alert')).toHaveTextContent('Filtros no válidos');
    });

    it('nombra las operaciones y el intento sin identificar', async () => {
        simularApi(api, {
            'GET /bitacora': {
                data: [
                    {
                        id: 3,
                        usuario: null,
                        operacion: 'sesion.fallida',
                        tabla_afectada: 'usuarios',
                        registro_id: null,
                        descripcion: 'Intento fallido con el identificador ana@umss.edu.bo.',
                        fecha_operacion: '2026-09-28 08:10:00',
                    },
                    {
                        id: 4,
                        usuario: { id: 7, nombre: 'Jofre Ticona', correo: 'jofre@umss.edu.bo' },
                        operacion: 'rol.crear',
                        tabla_afectada: 'roles',
                        registro_id: 15,
                        descripcion: null,
                        fecha_operacion: '2026-09-28 08:12:00',
                    },
                    {
                        id: 5,
                        usuario: { id: 7, name: 'Jofre Ticona' },
                        operacion: 'periodo.archivar',
                        tabla_afectada: 'periodos',
                        registro_id: 2,
                        descripcion: null,
                        fecha_operacion: '2026-09-28 08:15:00',
                    },
                ],
            },
        });

        montar();

        expect(await screen.findByText('3 eventos')).toBeInTheDocument();
        expect(screen.getByText('Sin identificar')).toBeInTheDocument();
        expect(screen.getAllByText('Creación de rol')).not.toHaveLength(0);
        expect(screen.getByText('Rol #15')).toBeInTheDocument();
        expect(screen.getAllByText('Jofre Ticona').length).toBeGreaterThanOrEqual(2);
        // Una operación sin etiqueta se muestra con su código.
        expect(screen.getAllByText('periodo.archivar')).not.toHaveLength(0);
        expect(screen.getByText('Período #2')).toBeInTheDocument();
    });

    it('no trae menú ni cierre de sesión propios: son del armazón', async () => {
        montar();
        await screen.findByText('2 eventos');

        expect(screen.getByRole('heading', { level: 1, name: 'Bitácora' })).toBeInTheDocument();
        expect(screen.queryByRole('complementary')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Cerrar sesión' })).toBeNull();
    });
});

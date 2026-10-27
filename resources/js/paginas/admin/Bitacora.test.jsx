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

    it('nombra al colaborador que operó con código de aula', async () => {
        simularApi(api, {
            'GET /bitacora': {
                data: [
                    {
                        id: 4,
                        usuario: null,
                        actor: 'Marta Colaboradora (código de aula)',
                        operacion: 'INGRESO_ADMITIDO',
                        tabla_afectada: 'ingresos',
                        registro_id: 9,
                        descripcion: 'Ingreso registrado.',
                        fecha_operacion: '2026-10-26 08:39:00',
                    },
                ],
            },
        });
        montar();

        expect(await screen.findByText('Marta Colaboradora (código de aula)')).toBeInTheDocument();
        expect(screen.queryByText('Usuario eliminado')).not.toBeInTheDocument();
    });

    it('traduce la atención de incidencia y muestra su aula y examen', async () => {
        simularApi(api, {
            'GET /bitacora': {
                data: [
                    {
                        id: 3,
                        usuario: { id: 7, name: 'Blanco Coca Leticia' },
                        operacion: 'INCIDENCIA_AULA_ATENDIDA',
                        tabla_afectada: 'incidencias_aula',
                        registro_id: 15,
                        descripcion: 'Incidencia de 691A, examen 1.',
                        fecha_operacion: '2026-10-19 10:59:00',
                    },
                ],
            },
        });

        montar();

        expect(await screen.findByText('1 evento')).toBeInTheDocument();
        expect(screen.getAllByText('Atención de incidencia')).toHaveLength(2);
        expect(screen.getByText('Incidencia de 691A, examen 1.')).toBeInTheDocument();
        expect(screen.getByRole('option', { name: 'Atención de incidencia' })).toHaveValue(
            'INCIDENCIA_AULA_ATENDIDA'
        );
    });

    it('nombra las operaciones de las incidencias unificadas', async () => {
        simularApi(api, {
            'GET /bitacora': {
                data: [
                    {
                        id: 4,
                        usuario: { id: 7, name: 'Blanco Coca Leticia' },
                        operacion: 'incidencia.corregir',
                        tabla_afectada: 'incidencias',
                        registro_id: 15,
                        descripcion: 'Corrigió: Material no permitido · Apuntes.',
                        fecha_operacion: '2026-10-19 10:59:00',
                    },
                    {
                        id: 5,
                        usuario: { id: 7, name: 'Blanco Coca Leticia' },
                        operacion: 'incidencia.atender',
                        tabla_afectada: 'incidencias',
                        registro_id: 15,
                        descripcion: 'Atendió: Material no permitido en Cálculo I, aula 691A.',
                        fecha_operacion: '2026-10-19 11:00:00',
                    },
                ],
            },
        });

        montar();

        expect(await screen.findByText('2 eventos')).toBeInTheDocument();
        expect(screen.getAllByText('Corrección de incidencia').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Incidencia atendida').length).toBeGreaterThan(0);
    });

    it('BUG-15 y N2: nombra las operaciones de la puerta', async () => {
        simularApi(api, {
            'GET /bitacora': {
                data: [
                    {
                        id: 4,
                        usuario: { id: 7, name: 'Blanco Coca Leticia' },
                        operacion: 'INGRESO_ADMITIDO',
                        tabla_afectada: 'ingresos',
                        registro_id: 30,
                        descripcion:
                            '202210098 Vega Escóbar Silvia · Aula 691A · Introducción a la Programación',
                        fecha_operacion: '2026-10-26 08:39:00',
                    },
                    {
                        id: 5,
                        usuario: { id: 7, name: 'Blanco Coca Leticia' },
                        operacion: 'puerta.expulsion',
                        tabla_afectada: 'ingresos',
                        registro_id: 30,
                        descripcion: null,
                        fecha_operacion: '2026-10-26 09:10:00',
                    },
                ],
            },
        });

        montar();

        expect(await screen.findByText('2 eventos')).toBeInTheDocument();
        expect(screen.getAllByText('Ingreso al examen').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Expulsión de estudiante').length).toBeGreaterThan(0);
        expect(
            screen.getByText(
                '202210098 Vega Escóbar Silvia · Aula 691A · Introducción a la Programación'
            )
        ).toBeInTheDocument();
        expect(screen.getByText('Ingreso #30')).toBeInTheDocument();
        expect(screen.queryByText('INGRESO_ADMITIDO')).not.toBeInTheDocument();
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

    it('separa en el filtro la solicitud del enlace y el restablecimiento de contraseña', async () => {
        simularApi(api, {
            'GET /bitacora': {
                data: [
                    {
                        id: 3,
                        usuario: { id: 11, name: 'Leticia Blanco' },
                        operacion: 'contrasena.solicitar_restablecimiento',
                        tabla_afectada: 'usuarios',
                        registro_id: 11,
                        descripcion: 'Se solicitó un enlace de recuperación de contraseña.',
                        fecha_operacion: '2026-09-28 10:00:00',
                    },
                    {
                        id: 2,
                        usuario: { id: 11, name: 'Leticia Blanco' },
                        operacion: 'contrasena.restablecer_por_enlace',
                        tabla_afectada: 'usuarios',
                        registro_id: 11,
                        descripcion: 'Contraseña restablecida mediante enlace de recuperación.',
                        fecha_operacion: '2026-09-28 10:05:00',
                    },
                    {
                        id: 1,
                        usuario: { id: 11, name: 'Leticia Blanco' },
                        operacion: 'usuario.restablecer_temporal',
                        tabla_afectada: 'usuarios',
                        registro_id: 11,
                        descripcion: 'Contraseña temporal nueva para la cuenta.',
                        fecha_operacion: '2026-09-28 10:10:00',
                    },
                ],
            },
        });

        montar();
        expect(await screen.findByText('3 eventos')).toBeInTheDocument();
        expect(screen.getByLabelText('Usuario')).toHaveDisplayValue('Todos los usuarios');

        const opcionesRestablecimiento = screen
            .getByLabelText('Operación')
            .querySelectorAll('option[value="contrasena.restablecimiento"]');
        expect(opcionesRestablecimiento).toHaveLength(1);
        const opcionesSolicitud = screen
            .getByLabelText('Operación')
            .querySelectorAll('option[value="contrasena.solicitar_restablecimiento"]');
        expect(opcionesSolicitud).toHaveLength(1);
        expect(
            screen
                .getByLabelText('Operación')
                .querySelectorAll('option[value="contrasena.restablecer_por_enlace"]')
        ).toHaveLength(0);

        fireEvent.change(screen.getByLabelText('Operación'), {
            target: { value: 'contrasena.restablecimiento' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Filtrar' }));

        await waitFor(() =>
            expect(api.get).toHaveBeenLastCalledWith('/bitacora', {
                params: { operacion: 'contrasena.restablecimiento' },
            })
        );
        expect(screen.getAllByText('Leticia Blanco')).not.toHaveLength(0);
        // Opción del filtro y fila.
        expect(screen.getAllByText('Solicitud de enlace de recuperación')).toHaveLength(2);
        expect(screen.getAllByText('Restablecimiento de contraseña')).toHaveLength(3);
    });

    it('nombra el cambio de contraseña y muestra la cuenta por su usuario', async () => {
        simularApi(api, {
            'GET /bitacora': {
                data: [
                    {
                        id: 9,
                        usuario: { id: 221, name: 'Corina Flores' },
                        operacion: 'contrasena.cambiar',
                        tabla_afectada: 'usuarios',
                        registro_id: 221,
                        registro_etiqueta: 'corina.flores',
                        descripcion: null,
                        fecha_operacion: '2026-10-19 10:54:00',
                    },
                ],
            },
        });

        montar();

        expect(await screen.findByText('1 evento')).toBeInTheDocument();
        // Opción del filtro y fila.
        expect(screen.getAllByText('Cambio de contraseña')).toHaveLength(2);
        expect(screen.queryByText('contrasena.cambiar')).not.toBeInTheDocument();
        expect(screen.getByText('Usuario corina.flores')).toBeInTheDocument();
        expect(screen.queryByText('Usuario #221')).not.toBeInTheDocument();
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

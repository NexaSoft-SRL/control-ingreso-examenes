import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    agregarEvento,
    aulasPreparadas,
    guardarInstantanea,
    guardarSesion,
    olvidarSesion,
    pendientesPorAula,
    pendientesTotales,
    sesionGuardada,
} from './almacen';
import { puertaSinConexion, sincronizar, sincronizarTodo } from './flujo';

// 24 de noviembre de 2026, 12:00 en La Paz (UTC-4).
const AHORA = Date.parse('2026-11-24T12:00:00-04:00');

const instantanea = (aula = { id: 78, nombre: '606' }) => ({
    dispositivo: 'puesto-abc',
    instantanea_en: '2026-11-24T11:30:00-04:00',
    servidor_ahora: '2026-11-24T12:00:00-04:00',
    aula,
    examenes: [
        {
            id: 16,
            asignatura: 'Introducción a la Programación',
            tipo: 'Segundo parcial',
            fecha: '2026-11-24',
            hora: '12:30',
            abre: '2026-11-24T11:30:00-04:00',
            cierra: '2026-11-24T13:30:00-04:00',
        },
    ],
    estudiantes: [],
    claves: {},
});

const evento = (uid) => ({ uid, resultado: 'ADMITIDO', medio: 'CODIGO', examen_id: 16 });

describe('lo pendiente y lo preparado en este navegador', () => {
    beforeEach(() => window.localStorage.clear());

    it('cuenta las lecturas por enviar de todas las aulas', () => {
        expect(pendientesTotales()).toBe(0);

        agregarEvento(78, evento('a'));
        agregarEvento(78, evento('b'));
        agregarEvento(12, evento('c'));

        expect(pendientesTotales()).toBe(3);
        expect(pendientesPorAula()).toEqual(
            expect.arrayContaining([
                { aulaId: 78, cantidad: 2 },
                { aulaId: 12, cantidad: 1 },
            ])
        );
    });

    it('lista las aulas preparadas para hoy, con su nombre, y no las de otro día', () => {
        guardarInstantanea(78, instantanea(), AHORA);
        guardarInstantanea(
            12,
            {
                ...instantanea({ id: 12, nombre: '617' }),
                servidor_ahora: '2026-11-20T12:00:00-04:00',
            },
            Date.parse('2026-11-20T12:00:00-04:00')
        );

        expect(aulasPreparadas(AHORA)).toEqual([{ aula_id: 78, nombre: '606', abierta: true }]);
    });

    it('la puerta sin conexión muestra el aula y sus exámenes, sin cifras de ingresos', () => {
        guardarInstantanea(78, instantanea(), AHORA);

        const puerta = puertaSinConexion(78, AHORA);

        expect(puerta).toMatchObject({
            aula: { id: 78, nombre: '606' },
            abierta: true,
            sin_cifras: true,
            total_ingresos: 0,
        });
        expect(puerta.examenes).toEqual([
            {
                id: 16,
                asignatura: 'Introducción a la Programación',
                tipo: 'Segundo parcial',
                hora: '12:30',
                ingresados: null,
                faltan: null,
            },
        ]);
        // La hora va como la escribe el servidor: hora de pared y zona.
        expect(puerta.hora_servidor).toBe('2026-11-24T12:00:00-04:00');
    });

    it('sin lista preparada, o de otro día, no hay puerta sin conexión', () => {
        expect(puertaSinConexion(78, AHORA)).toBeNull();

        guardarInstantanea(78, instantanea(), AHORA);
        expect(puertaSinConexion(78, AHORA + 24 * 3600 * 1000)).toBeNull();
    });
});

describe('la sesión de la puerta para abrir sin internet', () => {
    beforeEach(() => window.localStorage.clear());

    const auxiliar = { id: 6, nombre: 'Mamani Diego', permisos: ['punto_control'] };
    const administrador = { id: 1, nombre: 'Admin', permisos: ['usuarios_roles'] };

    it('guarda solo la cuenta de quien controla el ingreso', () => {
        guardarSesion(administrador, AHORA);
        expect(sesionGuardada(AHORA)).toBeNull();

        guardarSesion(auxiliar, AHORA);
        expect(sesionGuardada(AHORA)).toEqual(auxiliar);
    });

    it('al guardar otra cuenta que no es de la puerta, se borra la anterior', () => {
        guardarSesion(auxiliar, AHORA);
        guardarSesion(administrador, AHORA);

        expect(sesionGuardada(AHORA)).toBeNull();
    });

    it('vale un día y se puede olvidar', () => {
        guardarSesion(auxiliar, AHORA);

        expect(sesionGuardada(AHORA + 23 * 3600 * 1000)).toEqual(auxiliar);
        expect(sesionGuardada(AHORA + 25 * 3600 * 1000)).toBeNull();

        olvidarSesion();
        expect(sesionGuardada(AHORA)).toBeNull();
    });
});

describe('enviar lo pendiente de todas las aulas', () => {
    beforeEach(() => window.localStorage.clear());

    const respuesta = (aplicados) => ({
        data: { recibidos: aplicados, aplicados, repetidos: 0, ingresos: aplicados },
    });

    it('envía cada aula con pendientes y suma lo aplicado', async () => {
        guardarInstantanea(78, instantanea(), AHORA);
        guardarInstantanea(12, instantanea({ id: 12, nombre: '617' }), AHORA);
        agregarEvento(78, evento('a'));
        agregarEvento(12, evento('b'));
        agregarEvento(12, evento('c'));
        const api = {
            post: vi.fn((ruta) => Promise.resolve(respuesta(ruta.includes('/78/') ? 1 : 2))),
        };

        expect(await sincronizarTodo(api)).toBe(3);
        expect(api.post).toHaveBeenCalledTimes(2);
        expect(pendientesTotales()).toBe(0);
    });

    it('lo que no se puede enviar queda guardado', async () => {
        guardarInstantanea(78, instantanea(), AHORA);
        agregarEvento(78, evento('a'));
        const api = { post: vi.fn().mockRejectedValue(new Error('Network Error')) };

        expect(await sincronizarTodo(api)).toBe(0);
        expect(pendientesTotales()).toBe(1);
    });

    it('si dos pantallas piden enviar a la vez, se manda una sola vez', async () => {
        guardarInstantanea(78, instantanea(), AHORA);
        agregarEvento(78, evento('a'));
        let liberar;
        const api = {
            post: vi.fn(
                () =>
                    new Promise((resolver) => {
                        liberar = () => resolver(respuesta(1));
                    })
            ),
        };

        const primero = sincronizar(api, 78);
        const segundo = sincronizar(api, 78);
        liberar();

        expect(await primero).toMatchObject({ aplicados: 1 });
        expect(await segundo).toMatchObject({ aplicados: 1 });
        expect(api.post).toHaveBeenCalledTimes(1);
    });
});

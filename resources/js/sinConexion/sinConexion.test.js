import { beforeEach, describe, expect, it, vi } from 'vitest';
import sha256 from '../utiles/sha256';
import decidir from './decidir';
import {
    agregarEvento,
    cola,
    dispositivo,
    esDeHoy,
    guardarInstantanea,
    horaCorregida,
    instantaneaDe,
} from './almacen';
import {
    decidirSinConexion,
    esFalloDeRed,
    estadoSinConexion,
    prepararSinConexion,
    sincronizar,
} from './flujo';

// 12 de octubre de 2026, 08:20 en La Paz (UTC-4); el examen es a las 08:15.
const AHORA = Date.parse('2026-10-12T08:20:00-04:00');
const TOKEN = 'Zk3Q9xLmPw7RtYv2NcB5HdGfJsAe8UoIq1WzX4Ty6MbVnKlC';

function est(id, codigo, nombre, condicion, ingreso = null) {
    return { id, codigo, nombre, examenes: [{ examen_id: 3, condicion, ingreso }] };
}

function instantanea(cambios = {}) {
    return {
        dispositivo: 'puesto-abc',
        instantanea_en: '2026-10-12T08:00:00-04:00',
        servidor_ahora: '2026-10-12T08:00:00-04:00',
        aula: { id: 5, nombre: '691A' },
        examenes: [
            {
                id: 3,
                asignatura: 'Introducción a la Programación',
                tipo: 'Primer parcial',
                fecha: '2026-10-12',
                hora: '08:15',
                abre: '2026-10-12T07:15:00-04:00',
                cierra: '2026-10-12T09:45:00-04:00',
            },
        ],
        estudiantes: [
            est(1, '202100417', 'Mamani Diego', {
                habilitado: true,
                motivo: null,
                aula_id: 5,
                aula: '691A',
            }),
            est(2, '202101256', 'Peredo Lucía', {
                habilitado: false,
                motivo: 'Debe la práctica 3',
                aula_id: null,
                aula: null,
            }),
            est(3, '202000982', 'Rojas Andrea', null),
            est(4, '201900731', 'Vargas Sergio', {
                habilitado: true,
                motivo: null,
                aula_id: 9,
                aula: '617',
            }),
            est(
                5,
                '202203333',
                'Quiroga Marcelo',
                { habilitado: true, motivo: null, aula_id: 5, aula: '691A' },
                { aula_id: 9, aula: '617', hora: '08:10:00', expulsado: false }
            ),
        ],
        claves: { [sha256(TOKEN)]: { examen_id: 3, estudiante_id: 1 } },
        ...cambios,
    };
}

const decidirCon = (peticion, { cola: c = [], ahoraMs = AHORA, inst = instantanea() } = {}) =>
    decidir({ instantanea: inst, cola: c, ahoraMs, peticion });

describe('decidir sin conexión (las reglas de la puerta)', () => {
    it('admite a un habilitado por código y encola el evento', () => {
        const { veredicto, evento } = decidirCon({ identificador: '202100417' });

        expect(veredicto.veredicto).toBe('admitido');
        expect(veredicto.estudiante.nombre).toBe('Mamani Diego');
        expect(veredicto.examen.id).toBe(3);
        expect(veredicto.dato).toMatch(/^Registrado a las /);
        expect(evento).toMatchObject({
            resultado: 'ADMITIDO',
            medio: 'CODIGO',
            examen_id: 3,
            estudiante_id: 1,
        });
        expect(evento.uid.length).toBeGreaterThanOrEqual(8);
    });

    it('admite por QR comparando solo su hash', () => {
        const { veredicto, evento } = decidirCon({ token: TOKEN });

        expect(veredicto.veredicto).toBe('admitido');
        expect(evento.medio).toBe('QR');
        // El QR en claro no viaja en un evento admitido.
        expect(JSON.stringify(evento)).not.toContain(TOKEN);
    });

    it('un QR que no está en la instantánea se rechaza y viaja para que el servidor lo resuelva', () => {
        const desconocido = 'otro-token-que-no-se-conoce-0123456789';
        const { veredicto, evento } = decidirCon({ token: desconocido });

        expect(veredicto).toMatchObject({
            veredicto: 'no_corresponde',
            dato: 'Código QR no válido',
        });
        expect(evento).toMatchObject({ resultado: 'CODIGO_INVALIDO', token: desconocido });
    });

    it('rechaza al inhabilitado con su motivo y al sin revisar', () => {
        const inhabilitado = decidirCon({ identificador: '202101256' });
        expect(inhabilitado.veredicto).toMatchObject({
            veredicto: 'no_habilitado',
            dato: 'Debe la práctica 3',
        });
        expect(inhabilitado.evento).toMatchObject({
            resultado: 'NO_HABILITADO',
            detalle: 'Debe la práctica 3',
        });

        const sinRevisar = decidirCon({ identificador: '202000982' });
        expect(sinRevisar.veredicto).toMatchObject({
            veredicto: 'no_habilitado',
            dato: 'Sin revisar',
        });
    });

    it('un segundo rechazo del mismo estudiante en diez minutos no suma otro evento', () => {
        const primero = decidirCon({ identificador: '202101256' });
        const segundo = decidirCon(
            { identificador: '202101256' },
            { cola: [primero.evento], ahoraMs: AHORA + 60000 }
        );

        expect(segundo.veredicto.veredicto).toBe('no_habilitado');
        expect(segundo.evento).toBeNull();
    });

    it('avisa el aula sugerida cuando entra por otra puerta', () => {
        const { veredicto } = decidirCon({ identificador: '201900731' });

        expect(veredicto.veredicto).toBe('admitido');
        expect(veredicto.dato).toContain('su aula sugerida era la 617');
    });

    it('quien ya ingresó, según la instantánea, figura con su aula y hora', () => {
        const { veredicto, evento } = decidirCon({ identificador: '202203333' });

        expect(veredicto).toMatchObject({
            veredicto: 'ya_ingreso',
            dato: 'Ingresó a las 08:10 en el aula 617',
        });
        expect(evento.resultado).toBe('YA_INGRESO');
    });

    it('quien este puesto admitió sin conexión queda como ya ingresado', () => {
        const admitido = decidirCon({ identificador: '202100417' });
        const otra = decidirCon(
            { identificador: '202100417' },
            { cola: [admitido.evento], ahoraMs: AHORA + 5 * 60000 }
        );

        expect(otra.veredicto.veredicto).toBe('ya_ingreso');
        expect(otra.veredicto.dato).toContain('en esta aula');
        expect(otra.evento.resultado).toBe('YA_INGRESO');
    });

    it('una relectura inmediata no asienta incidencia', () => {
        const admitido = decidirCon({ identificador: '202100417' });
        const relectura = decidirCon(
            { identificador: '202100417' },
            { cola: [admitido.evento], ahoraMs: AHORA + 30000 }
        );

        expect(relectura.veredicto.veredicto).toBe('ya_ingreso');
        expect(relectura.evento).toBeNull();
    });

    it('un estudiante de otro examen no corresponde a esta aula', () => {
        const inst = instantanea();
        inst.estudiantes.push({
            id: 9,
            codigo: '202299999',
            nombre: 'Ajeno Alan',
            examenes: [{ examen_id: 77, condicion: null, ingreso: null }],
        });
        const { veredicto, evento } = decidirCon({ identificador: '202299999' }, { inst });

        expect(veredicto.veredicto).toBe('no_corresponde');
        expect(veredicto.dato).toBe('No inscrito en los exámenes del aula 691A');
        expect(evento.resultado).toBe('NO_CORRESPONDE');
    });

    it('un código que nadie tiene no es un veredicto', () => {
        expect(decidirCon({ identificador: '999999999' })).toEqual({ sinRegistro: true });
    });

    it('sin un examen abierto a esta hora no decide', () => {
        const tarde = Date.parse('2026-10-12T14:00:00-04:00');
        expect(decidirCon({ identificador: '202100417' }, { ahoraMs: tarde })).toEqual({
            sinExamen: true,
        });
    });

    it('un expulsado lo sigue estando', () => {
        const inst = instantanea();
        inst.estudiantes[4].examenes[0].ingreso.expulsado = true;
        const { veredicto, evento } = decidirCon({ identificador: '202203333' }, { inst });

        expect(veredicto.veredicto).toBe('expulsado');
        expect(evento).toBeNull();
    });

    it('admite a un habilitado por C.I. usando el hash de documentos y registra medio DOCUMENTO', () => {
        const inst = instantanea({
            documentos: {
                [sha256('5003748')]: 1,
                [sha256('79281941A')]: 2,
                [sha256('7928194')]: 2,
            },
        });

        const { veredicto, evento } = decidirCon({ identificador: '5003748' }, { inst });

        expect(veredicto.veredicto).toBe('admitido');
        expect(veredicto.estudiante.id).toBe(1);
        expect(evento).toMatchObject({
            resultado: 'ADMITIDO',
            medio: 'DOCUMENTO',
            examen_id: 3,
            estudiante_id: 1,
        });

        const conGuion = decidirCon({ identificador: '7928194-1a' }, { inst });
        expect(conGuion.veredicto.veredicto).toBe('no_habilitado');
        expect(conGuion.evento.medio).toBe('DOCUMENTO');
    });

    it('da prioridad al código SIS cuando coincide antes de buscar en documentos', () => {
        const inst = instantanea({
            documentos: {
                [sha256('202100417')]: 2,
            },
        });

        const { veredicto, evento } = decidirCon({ identificador: '202100417' }, { inst });

        expect(veredicto.estudiante.id).toBe(1);
        expect(evento.medio).toBe('CODIGO');
    });
});

describe('almacén y flujo del puesto', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    it('identifica al dispositivo y lo recuerda', () => {
        const uno = dispositivo();
        expect(uno.length).toBeGreaterThanOrEqual(8);
        expect(dispositivo()).toBe(uno);
    });

    it('corrige el reloj del celular con la diferencia medida contra el servidor', () => {
        const celular = AHORA - 20 * 60000; // el celular atrasa veinte minutos
        guardarInstantanea(
            5,
            instantanea({ servidor_ahora: '2026-10-12T08:20:00-04:00' }),
            celular
        );
        const guardada = instantaneaDe(5);

        expect(horaCorregida(guardada, celular + 60000)).toBe(AHORA + 60000);
    });

    it('la instantánea sirve el día en que se descargó y no otro', () => {
        guardarInstantanea(5, instantanea(), AHORA);
        const guardada = instantaneaDe(5);

        expect(esDeHoy(guardada, AHORA + 3600000)).toBe(true);
        expect(esDeHoy(guardada, AHORA + 24 * 3600000)).toBe(false);
        expect(esDeHoy(null, AHORA)).toBe(false);
    });

    it('decide sin conexión, encola y sobrevive a una recarga', () => {
        guardarInstantanea(5, instantanea(), AHORA);

        const r = decidirSinConexion(5, { identificador: '202100417' }, AHORA);

        expect(r.veredicto).toMatchObject({ veredicto: 'admitido', sin_conexion: true });
        expect(cola(5)).toHaveLength(1);
        expect(estadoSinConexion(5, AHORA)).toMatchObject({
            preparado: true,
            vigente: true,
            pendientes: 1,
            huboUso: true,
        });
    });

    it('explica por qué no puede decidir', () => {
        expect(decidirSinConexion(5, { identificador: '202100417' }, AHORA)).toEqual({
            error: 'SIN_INSTANTANEA',
        });

        guardarInstantanea(5, instantanea(), AHORA);
        expect(decidirSinConexion(5, { identificador: '202100417' }, AHORA + 30 * 3600000)).toEqual(
            { error: 'OTRO_DIA' }
        );
        expect(decidirSinConexion(5, { identificador: '999999999' }, AHORA)).toEqual({
            error: 'SIN_REGISTRO',
        });
        expect(
            decidirSinConexion(
                5,
                { identificador: '202100417' },
                Date.parse('2026-10-12T14:00:00-04:00')
            )
        ).toEqual({ error: 'SIN_EXAMEN' });
    });

    it('reconoce una falla de red pero no un rechazo del servidor', () => {
        expect(esFalloDeRed(new Error('Network Error'))).toBe(true);
        expect(esFalloDeRed({ response: { status: 503 } })).toBe(true);
        expect(esFalloDeRed({ response: { status: 422 } })).toBe(false);
        expect(esFalloDeRed({ response: { status: 409 } })).toBe(false);
    });

    it('prepara con conexión: descarga y guarda, y no pisa lo que no se envió', async () => {
        const api = { get: vi.fn().mockResolvedValue({ data: instantanea() }) };

        const hecho = await prepararSinConexion(api, 5);
        expect(hecho).toMatchObject({ guardada: true, estudiantes: 5, examenes: 1 });
        expect(api.get).toHaveBeenCalledWith('/puerta/aulas/5/instantanea', {
            params: { dispositivo: dispositivo() },
        });

        agregarEvento(5, { uid: 'evento-0001-a', resultado: 'ADMITIDO' });
        const otra = await prepararSinConexion(api, 5);
        expect(otra).toMatchObject({ guardada: false, motivo: 'PENDIENTES', pendientes: 1 });
    });

    it('sincroniza por lotes, vacía la cola y cierra; reenviar el cierre no hace nada', async () => {
        guardarInstantanea(5, instantanea(), AHORA);
        decidirSinConexion(5, { identificador: '202100417' }, AHORA);
        decidirSinConexion(5, { identificador: '202101256' }, AHORA);
        const api = {
            post: vi.fn().mockResolvedValue({
                data: {
                    recibidos: 2,
                    aplicados: 2,
                    repetidos: 0,
                    ingresos: 1,
                    incidencias: 1,
                    irregularidades: 0,
                },
            }),
        };

        const resumen = await sincronizar(api, 5);

        expect(resumen).toMatchObject({ aplicados: 2, ingresos: 1, incidencias: 1 });
        expect(api.post).toHaveBeenCalledTimes(1);
        const [ruta, cuerpo] = api.post.mock.calls[0];
        expect(ruta).toBe('/puerta/aulas/5/sincronizacion');
        expect(cuerpo).toMatchObject({
            dispositivo: dispositivo(),
            instantanea_en: '2026-10-12T08:00:00-04:00',
        });
        expect(cuerpo.eventos).toHaveLength(2);
        expect(cola(5)).toHaveLength(0);
        expect(await sincronizar(api, 5)).toBeNull();
        expect(api.post).toHaveBeenCalledTimes(1);
    });

    it('sin eventos no envía nada, salvo que se pida cerrar, y el cierre se manda una sola vez', async () => {
        guardarInstantanea(5, instantanea(), AHORA);
        const api = { post: vi.fn().mockResolvedValue({ data: { recibidos: 0, aplicados: 0 } }) };

        // Recién descargada no se cierra: si el celular se apaga, el servidor lo nota.
        expect(await sincronizar(api, 5)).toBeNull();
        expect(api.post).not.toHaveBeenCalled();

        await sincronizar(api, 5, { cerrar: true });
        await sincronizar(api, 5, { cerrar: true });

        expect(api.post).toHaveBeenCalledTimes(1);
        expect(api.post.mock.calls[0][1].eventos).toEqual([]);
    });

    it('si el envío falla conserva la cola para reintentar', async () => {
        guardarInstantanea(5, instantanea(), AHORA);
        decidirSinConexion(5, { identificador: '202100417' }, AHORA);
        const api = { post: vi.fn().mockRejectedValue(new Error('Network Error')) };

        await expect(sincronizar(api, 5)).rejects.toThrow('Network Error');

        expect(cola(5)).toHaveLength(1);
    });
});

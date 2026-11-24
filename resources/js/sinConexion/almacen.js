// Lo que el puesto guarda en el navegador para seguir controlando sin
// conexión: un identificador del dispositivo, la instantánea del aula y la
// cola de lo decidido. Persiste al recargar la página o cerrar el navegador.
const PREFIJO = 'sinconexion:';

function leer(clave) {
    try {
        const crudo = window.localStorage.getItem(PREFIJO + clave);
        return crudo === null ? null : JSON.parse(crudo);
    } catch {
        return null;
    }
}

// Falso si el navegador no deja guardar (almacenamiento lleno o bloqueado).
function escribir(clave, valor) {
    try {
        window.localStorage.setItem(PREFIJO + clave, JSON.stringify(valor));
        return true;
    } catch {
        return false;
    }
}

function borrar(clave) {
    try {
        window.localStorage.removeItem(PREFIJO + clave);
    } catch {
        // Sin almacenamiento no hay nada que borrar.
    }
}

const aleatorio = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return Array.from({ length: 32 }, () => Math.floor(Math.random() * 16).toString(16)).join('');
};

// Identifica a este navegador ante el servidor: el docente ve qué puesto
// preparó el modo y cuál no envió su cierre.
export function dispositivo() {
    const guardado = leer('dispositivo');
    if (typeof guardado === 'string' && guardado.length >= 8) return guardado;
    const nuevo = `puesto-${aleatorio()}`.slice(0, 64);
    escribir('dispositivo', nuevo);
    return nuevo;
}

export function nuevoUid() {
    return aleatorio();
}

// La instantánea con la diferencia entre el reloj del servidor y el de este
// celular, medida al descargarla.
export function guardarInstantanea(aulaId, instantanea, ahoraMs = Date.now()) {
    const servidorMs = Date.parse(instantanea.servidor_ahora);
    const coincide = /([+-]\d{2}:\d{2}|Z)$/.exec(instantanea.servidor_ahora);
    return escribir(`instantanea:${aulaId}`, {
        instantanea,
        desfase_ms: Number.isNaN(servidorMs) ? 0 : servidorMs - ahoraMs,
        zona: coincide ? coincide[1] : 'Z',
        descargada_ms: ahoraMs,
    });
}

export function instantaneaDe(aulaId) {
    const guardada = leer(`instantanea:${aulaId}`);
    return guardada?.instantanea ? guardada : null;
}

// La hora del servidor según este celular, ya corregida.
export function horaCorregida(guardada, ahoraMs = Date.now()) {
    return ahoraMs + (guardada?.desfase_ms ?? 0);
}

// Minutos que la zona del servidor se adelanta (o atrasa) de UTC.
function minutosDeZona(zona) {
    if (!zona || zona === 'Z') return 0;
    const signo = zona.startsWith('-') ? -1 : 1;
    const [h, m] = zona.slice(1).split(':').map(Number);
    return signo * (h * 60 + m);
}

// La fecha (AAAA-MM-DD) del servidor en ese instante.
export function fechaDelServidor(guardada, ms) {
    const minutos = minutosDeZona(guardada?.zona);
    return new Date(ms + minutos * 60000).toISOString().slice(0, 10);
}

// El instante como lo escribe el servidor (hora de pared + zona):
// «2026-11-24T12:30:00-04:00».
export function horaDelServidor(guardada, ms) {
    const zona = guardada?.zona ?? 'Z';
    const pared = new Date(ms + minutosDeZona(zona) * 60000).toISOString().slice(0, 19);
    return `${pared}${zona}`;
}

// La instantánea sirve el día en que se descargó: otro día hay que preparar
// de nuevo con conexión.
export function esDeHoy(guardada, ahoraMs = Date.now()) {
    if (!guardada) return false;
    const servidor = fechaDelServidor(guardada, Date.parse(guardada.instantanea.servidor_ahora));
    return fechaDelServidor(guardada, horaCorregida(guardada, ahoraMs)) === servidor;
}

export function cola(aulaId) {
    const eventos = leer(`cola:${aulaId}`);
    return Array.isArray(eventos) ? eventos : [];
}

// Quién tenía la sesión abierta, para volver a abrir la puerta sin internet
// (recargar la página o abrir la aplicación instalada). Solo se guarda de
// las cuentas que controlan el ingreso y vale un día: el servidor sigue
// siendo quien manda; sin red no hay con quién verificar y todas las demás
// peticiones fallan igual. Se borra al cerrar sesión o cuando el servidor
// dice que ya no hay sesión.
const VIGENCIA_SESION_MS = 24 * 60 * 60 * 1000;

export function guardarSesion(cuenta, ahoraMs = Date.now()) {
    if (cuenta?.permisos?.includes('punto_control')) {
        escribir('sesion', { cuenta, guardada_ms: ahoraMs });
    } else {
        borrar('sesion');
    }
}

export function sesionGuardada(ahoraMs = Date.now()) {
    const guardado = leer('sesion');
    if (!guardado?.cuenta || ahoraMs - guardado.guardada_ms > VIGENCIA_SESION_MS) return null;
    return guardado.cuenta;
}

export function olvidarSesion() {
    borrar('sesion');
}

// Los ids de las aulas con algo guardado en este navegador, según el prefijo
// de la clave (`instantanea` o `cola`).
function aulasCon(tipo) {
    const buscado = `${PREFIJO}${tipo}:`;
    const ids = [];
    try {
        for (let i = 0; i < window.localStorage.length; i += 1) {
            const clave = window.localStorage.key(i);
            if (clave?.startsWith(buscado)) {
                const id = Number(clave.slice(buscado.length));
                if (Number.isInteger(id) && id > 0) ids.push(id);
            }
        }
    } catch {
        return [];
    }
    return ids;
}

// Las aulas con lecturas decididas sin conexión que aún no llegaron al
// servidor: `[{ aulaId, cantidad }]`.
export function pendientesPorAula() {
    return aulasCon('cola')
        .map((aulaId) => ({ aulaId, cantidad: cola(aulaId).length }))
        .filter((p) => p.cantidad > 0);
}

// Cuántas lecturas esperan en este navegador, de todas las aulas.
export function pendientesTotales() {
    return pendientesPorAula().reduce((suma, p) => suma + p.cantidad, 0);
}

// Las aulas que este navegador dejó listas para hoy: con ellas el puesto
// puede abrir la puerta aunque el servidor no responda.
export function aulasPreparadas(ahoraMs = Date.now()) {
    return aulasCon('instantanea')
        .map((aulaId) => ({ aulaId, guardada: instantaneaDe(aulaId) }))
        .filter(({ guardada }) => guardada && esDeHoy(guardada, ahoraMs))
        .map(({ aulaId, guardada }) => ({
            aula_id: aulaId,
            nombre: guardada.instantanea.aula?.nombre ?? `Aula ${aulaId}`,
            abierta: true,
        }));
}

export function agregarEvento(aulaId, evento) {
    return escribir(`cola:${aulaId}`, [...cola(aulaId), evento]);
}

export function quitarEventos(aulaId, uids) {
    const enviados = new Set(uids);
    const restantes = cola(aulaId).filter((evento) => !enviados.has(evento.uid));
    if (restantes.length === 0) borrar(`cola:${aulaId}`);
    else escribir(`cola:${aulaId}`, restantes);
}

// Si este puesto llegó a decidir algo sin conexión con esta instantánea.
export function marcarUso(aulaId) {
    const estado = leer(`cierre:${aulaId}`) ?? {};
    escribir(`cierre:${aulaId}`, { ...estado, uso: true });
}

export function huboUso(aulaId) {
    return Boolean(leer(`cierre:${aulaId}`)?.uso);
}

// Se envió el cierre de esta instantánea (con o sin eventos).
export function marcarCerrada(aulaId, instantaneaEn) {
    const estado = leer(`cierre:${aulaId}`) ?? {};
    escribir(`cierre:${aulaId}`, { ...estado, cerrada: instantaneaEn });
}

export function estaCerrada(aulaId, instantaneaEn) {
    return leer(`cierre:${aulaId}`)?.cerrada === instantaneaEn;
}

// Una instantánea nueva empieza sin cierre ni uso.
export function reiniciarCierre(aulaId) {
    borrar(`cierre:${aulaId}`);
}

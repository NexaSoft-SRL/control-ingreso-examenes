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

// La fecha (AAAA-MM-DD) del servidor en ese instante.
export function fechaDelServidor(guardada, ms) {
    const zona = guardada?.zona ?? 'Z';
    let minutos = 0;
    if (zona !== 'Z') {
        const signo = zona.startsWith('-') ? -1 : 1;
        const [h, m] = zona.slice(1).split(':').map(Number);
        minutos = signo * (h * 60 + m);
    }
    return new Date(ms + minutos * 60000).toISOString().slice(0, 10);
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

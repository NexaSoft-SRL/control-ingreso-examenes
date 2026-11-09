// Señal del veredicto en la puerta: un tono agudo y corto si entra, dos
// graves si no, y una vibración que se distingue igual con el teléfono en
// silencio. Si el navegador no tiene audio o vibración, no hace nada.
const TONOS = {
    exito: { notas: [880], vibracion: [80] },
    advertencia: { notas: [660, 660], vibracion: [80, 60, 80] },
    peligro: { notas: [220, 220], vibracion: [200, 80, 200] },
};

let contexto = null;

function audio() {
    const Contexto = globalThis.AudioContext ?? globalThis.webkitAudioContext;
    if (!Contexto) return null;
    contexto ??= new Contexto();
    return contexto;
}

export function senal(tono) {
    const forma = TONOS[tono];
    if (!forma) return;
    try {
        globalThis.navigator?.vibrate?.(forma.vibracion);
    } catch {
        // Sin vibración.
    }
    try {
        const ctx = audio();
        if (!ctx) return;
        forma.notas.forEach((frecuencia, i) => {
            const inicio = ctx.currentTime + i * 0.18;
            const oscilador = ctx.createOscillator();
            const volumen = ctx.createGain();
            oscilador.frequency.value = frecuencia;
            volumen.gain.setValueAtTime(0.2, inicio);
            volumen.gain.exponentialRampToValueAtTime(0.001, inicio + 0.15);
            oscilador.connect(volumen).connect(ctx.destination);
            oscilador.start(inicio);
            oscilador.stop(inicio + 0.15);
        });
    } catch {
        // Sin audio.
    }
}

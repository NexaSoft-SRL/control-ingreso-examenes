// La página de un examen (`/examenes/detalle`), en una de sus pestañas:
// 'resumen' (la de entrada), 'habilitacion' o 'qr'.
export default function rutaDelExamen(id, pestana = 'resumen') {
    return `/examenes/detalle?examen=${id}${pestana === 'resumen' ? '' : `&pestana=${pestana}`}`;
}

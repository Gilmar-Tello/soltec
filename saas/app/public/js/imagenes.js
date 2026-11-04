// ============================================================
// Compresion de imagenes en el navegador — Alquileres SOLTEC
//
// La foto se reduce ANTES de salir del telefono: se suben 200 KB
// en vez de 4 MB. Menos datos para el cliente, menos carga para
// el hosting y el formulario no se queda pensando.
//
// Uso en un formulario:
//   <form ... data-comprimir-imagenes>
//     <input type="file" accept="image/*" name="foto">
//   </form>
// Eso es todo: el script intercepta el envio, cambia los archivos
// por sus versiones reducidas y manda el formulario.
// ============================================================

const SOLTEC_IMG_MAX_LADO = 1280;
const SOLTEC_IMG_CALIDAD  = 0.72;

function soltecCargarImagen(archivo) {
    return new Promise((resolver, rechazar) => {
        const url = URL.createObjectURL(archivo);
        const img = new Image();
        img.onload  = () => { URL.revokeObjectURL(url); resolver(img); };
        img.onerror = () => { URL.revokeObjectURL(url); rechazar(new Error('no se pudo leer')); };
        img.src = url;
    });
}

async function soltecComprimirImagen(archivo) {
    if (!archivo || !archivo.type || archivo.type.indexOf('image/') !== 0) return archivo;
    // Los HEIC de iPhone no los dibuja el canvas: van tal cual y
    // el servidor se encarga.
    if (archivo.type.indexOf('heic') !== -1 || archivo.type.indexOf('heif') !== -1) return archivo;

    try {
        const img = await soltecCargarImagen(archivo);
        const lado = Math.max(img.width, img.height);
        const escala = lado > SOLTEC_IMG_MAX_LADO ? SOLTEC_IMG_MAX_LADO / lado : 1;

        const lienzo = document.createElement('canvas');
        lienzo.width  = Math.max(1, Math.round(img.width  * escala));
        lienzo.height = Math.max(1, Math.round(img.height * escala));

        const ctx = lienzo.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, lienzo.width, lienzo.height);
        ctx.drawImage(img, 0, 0, lienzo.width, lienzo.height);

        const blob = await new Promise(r => lienzo.toBlob(r, 'image/jpeg', SOLTEC_IMG_CALIDAD));
        if (!blob || blob.size >= archivo.size) return archivo;  // si no mejora, la original

        const nombre = (archivo.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], nombre, { type: 'image/jpeg', lastModified: Date.now() });
    } catch (e) {
        return archivo;   // ante cualquier problema, se sube la original
    }
}

async function soltecComprimirCampo(input) {
    if (!input.files || !input.files.length) return;
    const transferencia = new DataTransfer();
    for (const archivo of Array.from(input.files)) {
        transferencia.items.add(await soltecComprimirImagen(archivo));
    }
    input.files = transferencia.files;
}

function soltecPeso(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

// Vista previa inmediata al elegir la foto.
function soltecVistaPrevia(input, contenedorId) {
    const caja = document.getElementById(contenedorId);
    if (!caja) return;
    caja.innerHTML = '';
    Array.from(input.files || []).forEach(archivo => {
        if (!archivo.type || archivo.type.indexOf('image/') !== 0) return;
        const url = URL.createObjectURL(archivo);
        const fig = document.createElement('div');
        fig.className = 'text-center';
        fig.innerHTML =
            '<img src="' + url + '" class="w-20 h-20 object-cover rounded-xl border-2 border-slate-200">' +
            '<span class="block text-[10px] text-slate-400 mt-0.5">' + soltecPeso(archivo.size) + '</span>';
        caja.appendChild(fig);
    });
}

// Intercepta el envio de todo formulario marcado con data-comprimir-imagenes
document.addEventListener('submit', async function (e) {
    const form = e.target;
    if (!form.matches('[data-comprimir-imagenes]') || form.dataset.comprimido === 'listo') return;

    const campos = form.querySelectorAll('input[type="file"]');
    if (!campos.length) return;

    e.preventDefault();

    const boton = form.querySelector('[type="submit"]');
    const textoOriginal = boton ? boton.innerHTML : '';
    if (boton) { boton.disabled = true; boton.innerHTML = 'Preparando fotos...'; }

    for (const campo of campos) await soltecComprimirCampo(campo);

    form.dataset.comprimido = 'listo';
    if (boton) { boton.innerHTML = textoOriginal || 'Guardando...'; }
    form.submit();
});

// ── Modal para ver una foto en grande ──────────────────────
function soltecAbrirFoto(url, titulo) {
    let modal = document.getElementById('soltecModalFoto');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'soltecModalFoto';
        modal.className = 'hidden fixed inset-0 z-[80] bg-black/80 items-center justify-center p-4';
        modal.innerHTML =
            '<div class="max-w-3xl w-full text-center">' +
            '  <div class="text-white font-bold mb-2" id="soltecModalTitulo"></div>' +
            '  <img id="soltecModalImg" src="" alt="" class="max-h-[80vh] w-auto mx-auto rounded-2xl shadow-2xl bg-white">' +
            '  <button type="button" class="mt-4 bg-white/90 hover:bg-white text-slate-800 font-bold px-6 py-2 rounded-xl">Cerrar</button>' +
            '</div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', () => soltecCerrarFoto());
    }
    document.getElementById('soltecModalTitulo').textContent = titulo || '';
    document.getElementById('soltecModalImg').src = url;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function soltecCerrarFoto() {
    const modal = document.getElementById('soltecModalFoto');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.getElementById('soltecModalImg').src = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') soltecCerrarFoto(); });

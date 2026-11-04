<?php
// ============================================================
// IMAGENES — Alquileres SOLTEC
//
// Toda foto que entra al sistema se vuelve a dibujar con GD en
// un JPG de maximo 1280px y calidad 72. Una foto de celular de
// 4 MB termina pesando entre 120 y 250 KB: el hosting no se
// llena y el inventario carga rapido desde el telefono.
//
// El navegador ya manda la foto reducida (app/public/js/imagenes.js).
// Esto es la segunda red de seguridad: si el usuario entra desde
// un navegador viejo, o alguien manda el archivo a mano, aqui se
// vuelve a comprimir igual.
// ============================================================

const IMG_MAX_BYTES  = 12582912;   // 12 MB de entrada como tope duro
const IMG_MAX_LADO   = 1280;       // lado mayor del archivo guardado
const IMG_CALIDAD    = 72;
const IMG_TIPOS      = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];

// Carpeta fisica de subidas. Vive en app/public, que el .htaccess
// ya tiene prohibido ejecutar como PHP.
function carpetaImagenes(string $sub): string {
    $ruta = RAIZ . '/app/public/' . $sub;
    if (!is_dir($ruta)) @mkdir($ruta, 0755, true);
    return $ruta;
}

// Ruta relativa guardada en la base -> URL publica
function urlImagen(?string $relativa): string {
    if (!$relativa) return '';
    return URL_BASE . '/' . ltrim($relativa, '/');
}

function existeImagen(?string $relativa): bool {
    return $relativa && is_file(RAIZ . '/' . ltrim($relativa, '/'));
}

function borrarImagen(?string $relativa): void {
    // Solo dentro de app/public: nunca se borra nada fuera de ahi.
    if (!$relativa || !str_starts_with($relativa, 'app/public/')) return;
    $archivo = RAIZ . '/' . $relativa;
    if (is_file($archivo)) @unlink($archivo);
}

// $_FILES con un solo archivo o con varios llega con formas distintas.
// Esto lo deja siempre como una lista de archivos individuales.
function archivosNormalizados($campo): array {
    if (!is_array($campo) || !isset($campo['name'])) return [];
    if (!is_array($campo['name'])) {
        return $campo['error'] === UPLOAD_ERR_NO_FILE ? [] : [$campo];
    }
    $lista = [];
    foreach (array_keys($campo['name']) as $i) {
        if (($campo['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $lista[] = [
            'name'     => $campo['name'][$i],
            'type'     => $campo['type'][$i],
            'tmp_name' => $campo['tmp_name'][$i],
            'error'    => $campo['error'][$i],
            'size'     => $campo['size'][$i],
        ];
    }
    return $lista;
}

// Guarda una imagen subida. Devuelve la ruta relativa
// ('app/public/productos/prod_...jpg') o null si algo falla.
// $error queda con el motivo para poder avisarle al usuario.
function guardarImagenSubida(array $archivo, string $sub, string $prefijo, ?string &$error = null): ?string {
    $error = null;

    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $error = 'La imagen no se pudo subir (codigo ' . $archivo['error'] . '). Puede pesar mas de lo que permite el servidor.';
        return null;
    }

    if (!is_uploaded_file($archivo['tmp_name'])) {
        $error = 'Archivo invalido.';
        return null;
    }

    if ($archivo['size'] > IMG_MAX_BYTES) {
        $error = 'La imagen pesa demasiado. Maximo 12 MB.';
        return null;
    }

    $info = @getimagesize($archivo['tmp_name']);
    if (!$info || !in_array($info[2], IMG_TIPOS, true)) {
        $error = 'El archivo no es una imagen valida (JPG, PNG o WEBP).';
        return null;
    }

    $nombre  = $prefijo . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $destino = carpetaImagenes($sub) . '/' . $nombre;

    if (!comprimirImagen($archivo['tmp_name'], $destino, $info)) {
        $error = 'No se pudo procesar la imagen.';
        return null;
    }

    return 'app/public/' . $sub . '/' . $nombre;
}

// Redibuja la imagen como JPG reducido. Si GD no esta disponible
// en el hosting, al menos mueve el archivo tal cual para no
// dejar al usuario sin foto.
function comprimirImagen(string $origen, string $destino, array $info): bool {
    if (!function_exists('imagecreatefromstring')) {
        return @move_uploaded_file($origen, $destino) || @copy($origen, $destino);
    }

    $contenido = @file_get_contents($origen);
    if ($contenido === false) return false;

    $img = @imagecreatefromstring($contenido);
    if (!$img) return false;

    $img = corregirOrientacion($img, $origen, $info[2]);

    $ancho = imagesx($img);
    $alto  = imagesy($img);
    $lado  = max($ancho, $alto);

    if ($lado > IMG_MAX_LADO) {
        $escala      = IMG_MAX_LADO / $lado;
        $nuevoAncho  = max(1, (int) round($ancho * $escala));
        $nuevoAlto   = max(1, (int) round($alto  * $escala));
        $reducida    = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        // Fondo blanco: los PNG con transparencia no salen negros.
        imagefill($reducida, 0, 0, imagecolorallocate($reducida, 255, 255, 255));
        imagecopyresampled($reducida, $img, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($img);
        $img = $reducida;
    } else {
        $plano = imagecreatetruecolor($ancho, $alto);
        imagefill($plano, 0, 0, imagecolorallocate($plano, 255, 255, 255));
        imagecopy($plano, $img, 0, 0, 0, 0, $ancho, $alto);
        imagedestroy($img);
        $img = $plano;
    }

    $ok = imagejpeg($img, $destino, IMG_CALIDAD);
    imagedestroy($img);

    if ($ok) @chmod($destino, 0644);
    return (bool) $ok;
}

// Las fotos de celular traen la rotacion en los datos EXIF, no en
// los pixeles. Sin esto, las fotos verticales salen acostadas.
function corregirOrientacion($img, string $origen, int $tipo) {
    if ($tipo !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) return $img;

    $exif = @exif_read_data($origen);
    $orientacion = (int) ($exif['Orientation'] ?? 0);

    if ($orientacion === 3) return imagerotate($img, 180, 0);
    if ($orientacion === 6) return imagerotate($img, -90, 0);
    if ($orientacion === 8) return imagerotate($img,  90, 0);

    return $img;
}

<?php
// ============================================================
// SEGURIDAD — POS GT
// Funciones compartidas: cookies de sesión, protección CSRF,
// límite de intentos de login y manejo seguro de errores.
// ============================================================

// ── Entorno: en producción NO se muestran errores en pantalla ──
// Cambiá a true solo en tu XAMPP local para depurar.
define('MODO_DESARROLLO', false);

function configurarErrores(): void {
    error_reporting(E_ALL);
    ini_set('display_errors', MODO_DESARROLLO ? '1' : '0');
    ini_set('log_errors', '1');
}

// ── Cookie de sesión segura ────────────────────────────────
// httponly: JavaScript no puede leerla.
// secure:   solo viaja por HTTPS.
// samesite: Lax evita que otro sitio envíe formularios con tu sesión.
function iniciarSesionSegura(): void {
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    // Nombre propio: evita que los 4 sistemas de SOLTEC se pisen la sesion
    session_name('SOLTEC_POS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $esHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// ── Protección CSRF por origen de la petición ──────────────
// Rechaza peticiones que cambian datos si vienen desde otro sitio.
// No requiere tocar los formularios: usa las cabeceras que el
// navegador agrega solo (Sec-Fetch-Site, Origin, Referer).
function peticionEsDelMismoSitio(): bool {
    $sitio = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($sitio !== '') {
        // same-origin: desde el POS. none: el usuario escribió la URL o usó un marcador.
        return in_array($sitio, ['same-origin', 'none'], true);
    }

    // Navegadores viejos sin Sec-Fetch-Site: comparar Origin o Referer
    $hostPropio = parse_url(URL_BASE, PHP_URL_HOST);
    $origen = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($origen === 'null') {
        return false; // Origin "null": iframe aislado o pagina local; nunca es el propio sistema
    }
    if ($origen === '') {
        // Sin ninguna cabecera: se acepta solo en POST (algunos proxies las quitan).
        // Un enlace GET que modifica datos y no trae origen ya NO pasa.
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }
    return parse_url($origen, PHP_URL_HOST) === $hostPropio;
}

function exigirMismoSitio(): void {
    if (peticionEsDelMismoSitio()) return;
    http_response_code(403);
    $esJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
           || ($_GET['url'] ?? '') === 'pos/procesarVentaOffline';
    if ($esJson) {
        header('Content-Type: application/json');
        echo json_encode(['exito' => false, 'mensaje' => 'Petición bloqueada por seguridad.']);
    } else {
        echo '<div style="font-family:sans-serif;padding:20px;">
                <h2>⛔ Petición bloqueada</h2>
                <p>Esta acción solo puede hacerse desde el propio sistema.</p>
                <a href="' . URL_BASE . '">← Volver al inicio</a>
              </div>';
    }
    exit;
}

// ── Límite de intentos de login ────────────────────────────
// 5 intentos fallidos por usuario+IP → bloqueo de 15 minutos.
// Se guarda en archivos temporales para no necesitar tablas nuevas.
const LOGIN_MAX_INTENTOS = 5;
const LOGIN_BLOQUEO_SEG  = 900;

function archivoIntentos(string $usuario): string {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'sin-ip';
    $dir = sys_get_temp_dir() . '/posgt_login';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . '/' . hash('sha256', strtolower($usuario) . '|' . $ip) . '.json';
}

function leerIntentos(string $usuario): array {
    $f = archivoIntentos($usuario);
    $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
    if (!is_array($d) || (time() - ($d['desde'] ?? 0)) > LOGIN_BLOQUEO_SEG) {
        return ['intentos' => 0, 'desde' => time()];
    }
    return $d;
}

function loginBloqueadoUsuario(string $usuario): int {
    $d = leerIntentos($usuario);
    if ($d['intentos'] < LOGIN_MAX_INTENTOS) return 0;
    return max(1, (int) ceil((LOGIN_BLOQUEO_SEG - (time() - $d['desde'])) / 60));
}

function registrarIntentoFallidoUsuario(string $usuario): void {
    $d = leerIntentos($usuario);
    $d['intentos']++;
    @file_put_contents(archivoIntentos($usuario), json_encode($d), LOCK_EX);
}

function limpiarIntentos(string $usuario): void {
    @unlink(archivoIntentos($usuario));
}

// ── Errores: el detalle va al log, el usuario ve un mensaje simple ──
function mensajeError(Throwable $e, string $mensajeUsuario = 'Ocurrió un error inesperado. Intentá de nuevo.'): string {
    error_log('[POS GT] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    return MODO_DESARROLLO ? $mensajeUsuario . ' (' . $e->getMessage() . ')' : $mensajeUsuario;
}

// ── Validación de negocio que sí se puede mostrar al usuario ──
class ErrorValidacion extends RuntimeException {}

// ── JSON seguro para incrustar dentro de <script> ─────────────
// json_encode normal deja pasar "</script>" y comillas: un nombre de producto o de
// cliente malicioso podria cerrar el script y ejecutar codigo (XSS almacenado).
// Estas banderas convierten < > & ' " en \uXXXX, que JavaScript lee igual.
function jsonJs($valor): string {
    return json_encode($valor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

// ── Fechas que llegan por la URL ───────────────────────────
// Solo se aceptan fechas reales en formato AAAA-MM-DD; cualquier otra cosa
// (incluido codigo HTML/JS) se reemplaza por el valor por defecto. Evita XSS reflejado.
function fechaGet(string $clave, string $porDefecto): string {
    $v = $_GET[$clave] ?? '';
    if (!is_string($v)) return $porDefecto;
    $f = DateTime::createFromFormat('!Y-m-d', $v);
    return ($f && $f->format('Y-m-d') === $v) ? $v : $porDefecto;
}

// ── Politica de contrasenas (se valida SIEMPRE en el servidor) ─────────
// Minimo 8 caracteres, maximo 72 (bcrypt ignora lo que pase de 72 bytes), nada de
// claves de lista comun, ni repetir el usuario/correo, ni todo el mismo caracter.
// Devuelve el mensaje de error, o '' si la contrasena es aceptable.
// $contexto: usuario(s) / correo(s) / nombre del negocio contra los que se compara.
function validarContrasenaNueva(string $clave, array $contexto = []): string {
    if (mb_strlen($clave) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (strlen($clave) > 72)   return 'La contraseña no puede pasar de 72 caracteres.';
    if (trim($clave) === '')   return 'La contraseña no puede ser solo espacios.';
    if (preg_match('/^(.)\1+$/us', $clave)) return 'La contraseña no puede ser un solo carácter repetido.';

    $minus = mb_strtolower($clave);
    static $comunes = [
        '12345678','123456789','1234567890','123123123','11111111','00000000','87654321','12341234',
        'password','password1','password123','passw0rd','contrasena','contraseña','contrasena1','contraseña1',
        'qwertyui','qwerty123','qwertyuiop','abcd1234','abc12345','abcdefgh','1q2w3e4r','1q2w3e4r5t',
        'admin123','admin1234','administrador','adminadmin','soltec123','soltec2026','soltec1234','soltecsoltec',
        'guatemala','guatemala1','guatemala123','guate123','bienvenido','bienvenido1','temporal1','cambiar123',
        'iloveyou','letmein1','welcome1','farmacia123','farmacia1','alquiler123','alquileres','brix1234','pos12345',
    ];
    if (in_array($minus, $comunes, true)) return 'Esa contraseña es demasiado común. Elige una más difícil de adivinar.';

    // No debe contener (ni ser) el usuario, el correo o el nombre del negocio
    foreach ($contexto as $dato) {
        $dato = mb_strtolower(trim((string) $dato));
        if ($dato === '') continue;
        $local = explode('@', $dato)[0];
        foreach (array_unique([$dato, $local]) as $d) {
            if (mb_strlen($d) >= 3 && str_contains($minus, $d)) {
                return 'La contraseña no puede contener el nombre de usuario, el correo ni el nombre del negocio.';
            }
        }
    }

    // Solo digitos consecutivos (12345678, 23456789...) o solo letras iguales/secuencia
    if (ctype_digit($clave)) {
        $consecutivos = true;
        for ($i = 1, $n = strlen($clave); $i < $n; $i++) {
            if ((int) $clave[$i] !== ((int) $clave[$i - 1] + 1) % 10) { $consecutivos = false; break; }
        }
        if ($consecutivos) return 'La contraseña no puede ser una secuencia de números.';
    }
    return '';
}

// ── Cierre de sesion por inactividad ───────────────────────
// 12 horas sin tocar el sistema: cubre un turno completo sin molestar al cajero.
// pos/ping y la sincronizacion de ventas offline las dispara el navegador solo (no
// es una persona usando el POS): NO renuevan el contador, pero tampoco se les bloquea
// la venta: si la sesion ya vencio, la venta offline queda en cola y se sincroniza
// cuando la persona vuelva a entrar.
const SESION_INACTIVIDAD_SEG = 43200;

function exigirSesionNoCaducada(): void {
    if (empty($_SESSION['usuario_id'])) return;

    $ultima = (int) ($_SESSION['ultima_actividad'] ?? 0);
    if ($ultima > 0 && (time() - $ultima) > SESION_INACTIVIDAD_SEG) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        iniciarSesionSegura();
        $_SESSION['error'] = 'Tu sesión expiró por inactividad. Ingresá de nuevo.';
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }

    $pasiva = in_array($_GET['url'] ?? '', ['pos/ping', 'pos/procesarVentaOffline'], true);
    if (!$pasiva) $_SESSION['ultima_actividad'] = time();
}

// ── Cabeceras de seguridad del navegador ───────────────────
// CSP (Content-Security-Policy) en modo SOLO-REPORTE: no bloquea nada todavia. El navegador
// anota en la consola (F12) lo que bloquearia. Cuando la consola salga limpia en todas las
// pantallas, cambia CSP_MODO a 'bloquear'.
// 'unsafe-inline' sigue en script-src porque las vistas usan mucho JavaScript en linea y
// onclick="..."; aun asi la politica ya impide cargar scripts de otros sitios, incrustar
// el sistema en otra pagina, cambiar la base de los enlaces o enviar formularios fuera.
// HSTS: el navegador recuerda usar solo HTTPS (1 dia al empezar; subelo por etapas).
const CSP_MODO = 'reporte';   // 'reporte' | 'bloquear'

function enviarCabecerasSeguridad(): void {
    $politica = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "img-src 'self' data: blob:",
        "media-src 'self' blob: data:",
        "connect-src 'self'",
        "frame-src 'self' blob:",
        "worker-src 'self' blob:",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ]);
    header((CSP_MODO === 'bloquear' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only') . ': ' . $politica);
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=(), usb=()');

    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($esHttps) header('Strict-Transport-Security: max-age=86400');
}

// ── Limite por IP (ademas del limite por usuario) ──────────
// El limite por usuario+IP no frena a quien prueba UNA clave en muchos usuarios distintos
// desde la misma IP. Este cuenta TODOS los fallos de una IP: 25 en 15 minutos = bloqueo de 15 minutos.
// (Una tienda con varios cajeros detras de la misma IP tiene margen de sobra para equivocarse.)
const LOGIN_MAX_POR_IP = 25;

function archivoIntentosIp(): string {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'sin-ip';
    $dir = dirname(archivoIntentos(''));
    return $dir . '/ip_' . hash('sha256', $ip) . '.json';
}

function leerIntentosIp(): array {
    $f = archivoIntentosIp();
    $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
    if (!is_array($d) || (time() - ($d['desde'] ?? 0)) > LOGIN_BLOQUEO_SEG) {
        return ['intentos' => 0, 'desde' => time()];
    }
    return $d;
}

// Minutos que le faltan a la IP bloqueada (0 si puede intentar)
function bloqueoPorIp(): int {
    $d = leerIntentosIp();
    if ($d['intentos'] < LOGIN_MAX_POR_IP) return 0;
    return max(1, (int) ceil((LOGIN_BLOQUEO_SEG - (time() - $d['desde'])) / 60));
}

// Los dos limites juntos: manda el que bloquee mas tiempo
function loginBloqueado(string $usuario): int {
    return max(loginBloqueadoUsuario($usuario), bloqueoPorIp());
}

function registrarIntentoFallido(string $usuario): void {
    registrarIntentoFallidoUsuario($usuario);
    $d = leerIntentosIp();
    $d['intentos']++;
    @file_put_contents(archivoIntentosIp(), json_encode($d), LOCK_EX);
}

// ── Logos subidos: se vuelven a dibujar con GD ─────────────
// getimagesize() solo mira la cabecera: un archivo puede ser una imagen valida Y llevar codigo
// PHP pegado al final. Al redibujarla, solo sobreviven los pixeles; todo lo demas se descarta.
// $mimeSalida: image/jpeg | image/png | image/webp. Reduce a $maxLado px si es mas grande.
// Devuelve true si el archivo quedo guardado en $destino.
function guardarLogoSeguro(string $origen, string $destino, string $mimeSalida, int $maxLado = 800): bool {
    // Hosting sin GD: se guarda tal cual (las carpetas de logos ya no ejecutan PHP por su .htaccess)
    if (!function_exists('imagecreatefromstring')) {
        return @move_uploaded_file($origen, $destino);
    }
    $datos = @file_get_contents($origen);
    if ($datos === false) return false;
    $img = @imagecreatefromstring($datos);
    if (!$img) return false;   // no es una imagen real: se rechaza, no se guarda

    $ancho = imagesx($img);
    $alto  = imagesy($img);
    $lado  = max($ancho, $alto);
    $conAlfa = ($mimeSalida !== 'image/jpeg');

    $nw = $ancho; $nh = $alto;
    if ($lado > $maxLado) {
        $e  = $maxLado / $lado;
        $nw = max(1, (int) round($ancho * $e));
        $nh = max(1, (int) round($alto * $e));
    }
    $lienzo = imagecreatetruecolor($nw, $nh);
    if ($conAlfa) {
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
    } else {
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
    }
    imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $ancho, $alto);
    imagedestroy($img);

    switch ($mimeSalida) {
        case 'image/png':  $ok = imagepng($lienzo, $destino, 6); break;
        case 'image/webp': $ok = function_exists('imagewebp') && imagewebp($lienzo, $destino, 85); break;
        default:           $ok = imagejpeg($lienzo, $destino, 88);
    }
    imagedestroy($lienzo);
    if ($ok) @chmod($destino, 0644);
    return (bool) $ok;
}

// ── Funciones opcionales por negocio ───────────────────────
// Las enciende el panel maestro (tabla negocio_funciones). Todo viene APAGADO por defecto:
// sin fila, sin tabla (antes de la migracion) o con cualquier error, la respuesta es false.
// Una consulta por peticion, y solo si algun codigo pregunta por una funcion.
function funcionActiva(string $clave): bool {
    static $activas = null;
    if ($activas === null) {
        $activas = [];
        $tid = (int) ($_SESSION['tenant_id'] ?? 0);
        if ($tid > 0) {
            try {
                $st = obtenerConexion()->prepare(
                    "SELECT funcion FROM negocio_funciones WHERE tenant_id = :t AND activo = 1");
                $st->execute([':t' => $tid]);
                $activas = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
            } catch (Throwable $e) {
                error_log('[SOLTEC pos] negocio_funciones no disponible: ' . $e->getMessage());
            }
        }
    }
    return isset($activas[$clave]);
}

// Corta la peticion si el negocio no tiene encendida la funcion (para pantallas/acciones que dependen de ella)
function requerirFuncion(string $clave): void {
    if (!funcionActiva($clave)) {
        $_SESSION['error'] = 'Esta función no está habilitada para tu negocio.';
        header('Location: ' . URL_BASE . '/?url=pos/index');
        exit;
    }
}

// ── Fotos de productos (funcion opcional "fotos_productos") ─────────
// En la base solo se guarda el NOMBRE del archivo (24 caracteres hex + .jpg). La carpeta sale
// del negocio de la sesion: app/public/productos/tenant_N/. Todas las fotos se redibujan con GD
// (JPG, maximo 900 px), asi que nada de lo que venga dentro del archivo original se conserva.
function urlFotoProducto($archivo, int $tid): ?string {
    if (!is_string($archivo) || !preg_match('/^[a-f0-9]{24}\.jpg$/', $archivo)) return null;
    return URL_BASE . '/app/public/productos/tenant_' . $tid . '/' . $archivo;
}

// Procesa $_FILES['foto']. Devuelve [nombreDeArchivo|null, mensajeDeError|null]; [null, null] si no se subio nada.
function procesarFotoProducto(int $tid): array {
    if (!isset($_FILES['foto']) || (int) ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    $f = $_FILES['foto'];
    if ((int) $f['error'] !== UPLOAD_ERR_OK)  return [null, 'No se pudo subir la foto. Intenta de nuevo.'];
    if ((int) $f['size'] > 12 * 1024 * 1024)  return [null, 'La foto no debe pasar de 12 MB.'];
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return [null, 'La foto debe ser JPG, PNG o WEBP.'];
    }
    $dir = RAIZ . '/app/public/productos/tenant_' . $tid;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return [null, 'No se pudo crear la carpeta de fotos.'];
    $nombre = bin2hex(random_bytes(12)) . '.jpg';
    if (!guardarLogoSeguro($f['tmp_name'], $dir . '/' . $nombre, 'image/jpeg', 900)) {
        return [null, 'No se pudo procesar la foto. Prueba con otra imagen.'];
    }
    return [$nombre, null];
}

function borrarFotoProducto($archivo, int $tid): void {
    if (!is_string($archivo) || !preg_match('/^[a-f0-9]{24}\.jpg$/', $archivo)) return;
    $ruta = RAIZ . '/app/public/productos/tenant_' . $tid . '/' . $archivo;
    if (is_file($ruta)) @unlink($ruta);
}

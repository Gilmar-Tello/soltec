<?php
// ============================================================
// TOTP (RFC 6238) — verificación en dos pasos del Panel Maestro
// Sin librerías externas: compatible con Google Authenticator,
// Microsoft Authenticator, Authy, 1Password, Aegis, etc.
// ============================================================

const TOTP_DIGITOS = 6;
const TOTP_PERIODO = 30;      // segundos que dura cada código
const TOTP_ALFABETO_B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32Codificar(string $binario): string {
    $bits = '';
    foreach (str_split($binario) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $alfabeto = TOTP_ALFABETO_B32;
    $salida = '';
    foreach (str_split($bits, 5) as $trozo) {
        $salida .= $alfabeto[bindec(str_pad($trozo, 5, '0', STR_PAD_RIGHT))];
    }
    return $salida;
}

function base32Decodificar(string $texto): string {
    $texto = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $texto));
    $bits = '';
    foreach (str_split($texto) as $c) {
        $pos = strpos(TOTP_ALFABETO_B32, $c);
        if ($pos === false) continue;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $binario = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $binario .= chr(bindec($byte));
    }
    return $binario;
}

// Secreto nuevo: 20 bytes aleatorios -> 32 caracteres en base32
function totpGenerarSecreto(): string {
    return base32Codificar(random_bytes(20));
}

// Código de 6 dígitos para un "paso" de 30 s (HMAC-SHA1, truncado dinámico)
function totpCodigo(string $secretoBinario, int $paso): string {
    $mensaje = pack('N2', 0, $paso);   // contador de 8 bytes, big-endian
    $hash = hash_hmac('sha1', $mensaje, $secretoBinario, true);
    $desp = ord($hash[19]) & 0x0F;
    $num  = ((ord($hash[$desp]) & 0x7F) << 24)
          | (ord($hash[$desp + 1]) << 16)
          | (ord($hash[$desp + 2]) << 8)
          |  ord($hash[$desp + 3]);
    return str_pad((string) ($num % (10 ** TOTP_DIGITOS)), TOTP_DIGITOS, '0', STR_PAD_LEFT);
}

// Devuelve el paso aceptado, o null si el código no vale.
// Tolera un paso de diferencia de reloj (±30 s) y rechaza reutilizar un paso ya usado
// ($ultimoPaso): un código espiado no sirve una segunda vez.
function totpVerificar(string $secretoB32, string $codigo, int $ultimoPaso = 0): ?int {
    $codigo = preg_replace('/\s+/', '', $codigo);
    if (!preg_match('/^\d{6}$/', $codigo)) return null;
    $secreto = base32Decodificar($secretoB32);
    if ($secreto === '') return null;
    $actual = intdiv(time(), TOTP_PERIODO);
    for ($d = -1; $d <= 1; $d++) {
        $paso = $actual + $d;
        if ($paso <= $ultimoPaso) continue;
        if (hash_equals(totpCodigo($secreto, $paso), $codigo)) return $paso;
    }
    return null;
}

// Dirección que entienden las apps al escanear un QR o importar a mano
function totpUri(string $secretoB32, string $cuenta, string $emisor = 'SOLTEC Panel'): string {
    return 'otpauth://totp/' . rawurlencode($emisor . ':' . $cuenta)
         . '?secret=' . $secretoB32
         . '&issuer=' . rawurlencode($emisor)
         . '&algorithm=SHA1&digits=' . TOTP_DIGITOS . '&period=' . TOTP_PERIODO;
}

// ── Códigos de recuperación (de un solo uso) ───────────────
function generarCodigosRecuperacion(int $cantidad = 8): array {
    $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';   // sin 0/O, 1/I/L
    $max = strlen($alfabeto) - 1;
    $codigos = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $c = '';
        for ($j = 0; $j < 10; $j++) $c .= $alfabeto[random_int(0, $max)];
        $codigos[] = substr($c, 0, 5) . '-' . substr($c, 5);
    }
    return $codigos;
}

function normalizarCodigoRecuperacion(string $codigo): string {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
}

// true si el código existe, no estaba usado, y queda marcado como usado
function usarCodigoRecuperacion(PDO $db, int $adminId, string $codigo): bool {
    $codigo = normalizarCodigoRecuperacion($codigo);
    if (strlen($codigo) !== 10) return false;
    $st = $db->prepare("SELECT id, codigo_hash FROM panel_recuperacion WHERE admin_id = :a AND usado_en IS NULL");
    $st->execute([':a' => $adminId]);
    foreach ($st->fetchAll() as $fila) {
        if (password_verify($codigo, $fila['codigo_hash'])) {
            $up = $db->prepare("UPDATE panel_recuperacion SET usado_en = NOW() WHERE id = :id AND usado_en IS NULL");
            $up->execute([':id' => $fila['id']]);
            return $up->rowCount() === 1;
        }
    }
    return false;
}

// Reemplaza todos los códigos del administrador por 8 nuevos; devuelve los códigos en claro (se muestran UNA vez)
function reemplazarCodigosRecuperacion(PDO $db, int $adminId): array {
    $codigos = generarCodigosRecuperacion();
    $db->prepare("DELETE FROM panel_recuperacion WHERE admin_id = :a")->execute([':a' => $adminId]);
    $ins = $db->prepare("INSERT INTO panel_recuperacion (admin_id, codigo_hash) VALUES (:a, :h)");
    foreach ($codigos as $c) {
        $ins->execute([':a' => $adminId, ':h' => password_hash(normalizarCodigoRecuperacion($c), PASSWORD_DEFAULT)]);
    }
    return $codigos;
}

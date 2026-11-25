<?php
// Helpers privados de autenticação e e-mails transacionais Brevo.
// Este arquivo não pode ser acessado diretamente no navegador.
if (!defined('FROTA_API_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

function requireAuthMigration(): void {
    global $db;
    static $done = false;
    if ($done) return;
    try {
        $db->query('SELECT Email_Verificado_Em, Email_Verificacao_Hash, Email_Verificacao_Expira,
                    Email_Verificacao_Enviado_Em, Reset_Senha_Hash, Reset_Senha_Expira,
                    Reset_Senha_Enviado_Em FROM Usuarios LIMIT 0');
    } catch (PDOException $e) {
        error_log('[Frota] Migration de e-mail pendente: ' . $e->getMessage());
        respond(['erro'=>'A atualização de autenticação ainda não foi aplicada ao banco. Execute atualizar_usuarios_email.sql no phpMyAdmin.'], 503);
    }
    $done = true;
}

function requireBrevoConfig(): void {
    foreach (['BREVO_API_KEY', 'BREVO_SENDER_EMAIL', 'APP_BASE_URL'] as $setting) {
        if (!defined($setting) || trim((string)constant($setting)) === ''
            || strpos((string)constant($setting), 'PREENCHA_') === 0) {
            throw new RuntimeException('Configure as credenciais da Brevo e APP_BASE_URL no config.php.');
        }
    }
    if (!filter_var(BREVO_SENDER_EMAIL, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('BREVO_SENDER_EMAIL deve ser um e-mail válido e verificado na Brevo.');
    }
    if (!preg_match('~^https://[a-z0-9.-]+(?::443)?(?:/[a-z0-9/_-]*)?$~i', rtrim((string)APP_BASE_URL, '/'))) {
        throw new RuntimeException('APP_BASE_URL deve ser um endereço HTTPS público, sem parâmetros (ex.: https://seusite.infinityfreeapp.com).');
    }
}

function appLink(string $page, string $token): string {
    requireBrevoConfig();
    return rtrim((string)APP_BASE_URL, '/') . '/' . $page . '?token=' . rawurlencode($token);
}

function sendBrevoEmail(string $to, string $name, string $subject, string $message, string $url): void {
    requireBrevoConfig();
    if (!function_exists('curl_init')) throw new RuntimeException('cURL não está disponível no PHP da hospedagem.');
    $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<!doctype html><html><body style="font:16px Arial,sans-serif;color:#24313c">'
          . '<h2>Controle de Frota</h2><p>' . nl2br($safeMessage) . '</p>'
          . '<p><a href="' . $safeUrl . '" style="background:#246b8a;color:white;padding:12px 18px;border-radius:7px;text-decoration:none">Abrir link seguro</a></p>'
          . '<p>Se o botão não funcionar, copie o link:</p><p>' . $safeUrl . '</p>'
          . '<p>Se você não fez esta solicitação, ignore esta mensagem.</p></body></html>';
    $payload = [
        'sender' => ['name' => defined('BREVO_SENDER_NAME') ? (string)BREVO_SENDER_NAME : 'Controle de Frota',
                     'email' => (string)BREVO_SENDER_EMAIL],
        'to' => [['email' => $to, 'name' => $name]],
        'subject' => $subject,
        // A API documenta apenas um formato de corpo por requisição.
        'htmlContent' => $html
    ];
    $c = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($c === false) throw new RuntimeException('Não foi possível iniciar a conexão com a Brevo.');
    try {
        curl_setopt_array($c, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'api-key: ' . (string)BREVO_API_KEY,
                'accept: application/json',
                'content-type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($c);
        $status = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        if ($response === false || $status < 200 || $status >= 300) {
            // Diagnóstico seguro: nunca registrar resposta, chave, destinatário ou URL/token.
            $category = 'provider_error';
            if ($response === false || $status === 0) {
                $category = 'transport_error';
            } elseif ($status === 400) {
                $category = 'request_rejected';
            } elseif ($status === 401) {
                $category = 'authentication_failed';
            } elseif ($status === 403) {
                $category = 'sender_or_account_forbidden';
            } elseif ($status === 429) {
                $category = 'rate_limited';
            } elseif ($status >= 500) {
                $category = 'provider_error';
            }
            $errorCode = '';
            if (is_string($response) && $response !== '') {
                $decoded = json_decode($response, true);
                if (is_array($decoded) && isset($decoded['code']) && is_string($decoded['code'])
                    && preg_match('/^[A-Za-z0-9_.-]{1,80}$/D', $decoded['code'])) {
                    $errorCode = '; api_code=' . $decoded['code'];
                }
            }
            error_log('[Frota] Brevo envio falhou; categoria=' . $category
                . '; HTTP=' . $status . '; curl_errno=' . curl_errno($c) . $errorCode);
            throw new RuntimeException('Falha ao enviar e-mail pela Brevo; diagnóstico=' . $category
                . ', HTTP=' . $status . ', cURL=' . curl_errno($c) . '.');
        }
    } finally {
        curl_close($c);
    }
}

function issueEmailVerification(int $userId, string $email, string $name): bool {
    global $db;
    requireBrevoConfig();
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $link = appLink('confirmar_email.html', $token);
    // Atualização condicional atômica: evita múltiplos envios por tentativas simultâneas.
    $st = $db->prepare('UPDATE Usuarios SET Email_Verificacao_Hash=?,
                       Email_Verificacao_Expira=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY),
                       Email_Verificacao_Enviado_Em=UTC_TIMESTAMP()
                       WHERE Id=? AND Email_Verificado_Em IS NULL
                       AND (Email_Verificacao_Enviado_Em IS NULL OR Email_Verificacao_Enviado_Em <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 SECOND))');
    $st->execute([$hash, $userId]);
    if (!$st->rowCount()) return false;
    try {
        sendBrevoEmail($email, $name, 'Confirme seu e-mail — Controle de Frota',
            'Você está acessando sua conta pela primeira vez. Confirme seu endereço de e-mail pelo link abaixo. Ele vence em 24 horas.',
            $link);
    } catch (Throwable $e) {
        // Caso falhe, permite reenviar imediatamente em vez de manter bloqueio falso.
        $st = $db->prepare('UPDATE Usuarios SET Email_Verificacao_Hash=NULL, Email_Verificacao_Expira=NULL,
                            Email_Verificacao_Enviado_Em=NULL WHERE Id=? AND Email_Verificacao_Hash=?');
        $st->execute([$userId, $hash]);
        throw $e;
    }
    return true;
}

function issuePasswordReset(int $userId, string $email, string $name): void {
    global $db;
    requireBrevoConfig();
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $link = appLink('redefinir_senha.html', $token);
    $st = $db->prepare('UPDATE Usuarios SET Reset_Senha_Hash=?,
                       Reset_Senha_Expira=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE),
                       Reset_Senha_Enviado_Em=UTC_TIMESTAMP()
                       WHERE Id=? AND (Reset_Senha_Enviado_Em IS NULL OR
                       Reset_Senha_Enviado_Em <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 SECOND))');
    $st->execute([$hash, $userId]);
    if (!$st->rowCount()) return;
    try {
        sendBrevoEmail($email, $name, 'Redefinir senha — Controle de Frota',
            'Recebemos um pedido para redefinir a senha da sua conta. O link abaixo vence em 30 minutos e pode ser usado apenas uma vez.', $link);
    } catch (Throwable $e) {
        $st = $db->prepare('UPDATE Usuarios SET Reset_Senha_Hash=NULL, Reset_Senha_Expira=NULL,
                            Reset_Senha_Enviado_Em=NULL WHERE Id=? AND Reset_Senha_Hash=?');
        $st->execute([$userId, $hash]);
        throw $e;
    }
}

function enforceNewPassword($password): string {
    $password = (string)$password;
    if (strlen($password) < 12 || strlen($password) > 200) {
        respond(['erro'=>'Use uma senha com 12 a 200 caracteres.'], 400);
    }
    return $password;
}

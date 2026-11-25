<?php
declare(strict_types=1);

// API do Controle de Frota. Compatível com o esquema fornecido pelo usuário.
// Erros técnicos ficam no log do servidor; o navegador recebe sempre JSON.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[Controle de Frota] Erro fatal: ' . $error['message']);
        if (ob_get_level() > 0) ob_clean();
        respond(['erro' => 'Erro interno no PHP. Verifique o arquivo api.php e os logs da hospedagem.'], 500);
    }
});
ob_start();

try {
    require __DIR__ . '/config.php';
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('frota_sessao');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    if (!session_start()) throw new RuntimeException('Não foi possível iniciar a sessão.');

    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $required) {
        if (!defined($required) || strpos((string)constant($required), 'PREENCHA_') === 0) {
            respond(['erro' => 'Configure DB_HOST, DB_NAME, DB_USER e DB_PASS em config.php com os dados do InfinityFree.'], 500);
        }
    }
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    error_log('[Controle de Frota] Inicialização: ' . $e->getMessage());
    respond(['erro' => 'Não foi possível conectar ao MySQL. Confira Hostname, Nome do banco, Usuário e Senha no painel InfinityFree.'], 500);
}

define('FROTA_API_BOOTSTRAP', true);
require_once __DIR__ . '/auth_email.php';

function selectRows(string $sql, array $params = []): array {
    global $db;
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}
function executeSql(string $sql, array $params = []): void {
    global $db;
    $statement = $db->prepare($sql);
    $statement->execute($params);
}
function roleName(string $role): string { return strtoupper($role); }

// Tabela, PK, campos do front => colunas do MySQL, permissões de leitura/escrita.
$tables = [
    'funcionarios' => ['Funcionario', 'Id_Funcionario', [
        'cpf'=>'CPF', 'nome'=>'Nome', 'nascimento'=>'Data_Nascimento',
        'funcao'=>'Funcao', 'telefone'=>'Telefone', 'salario'=>'Salario'
    ], ['ADMIN','GERENTE','RH'], ['ADMIN','RH']],
    'veiculos' => ['Veiculos', 'Id_Veiculo', [
        'placa'=>'Placa','modelo'=>'Modelo','marca'=>'Marca','ano'=>'Ano','capacidade'=>'Capacidade_Carga'
    ], ['ADMIN','GERENTE','USUARIO'], ['ADMIN','GERENTE']],
    'cargas' => ['Carga', 'Id_Carga', [
        'descricao'=>'Descricao','peso'=>'Peso','origem'=>'Origem','destino'=>'Destino','envio'=>'Data_Envio'
    ], ['ADMIN','GERENTE','USUARIO'], ['ADMIN','GERENTE']],
    'viagens' => ['Viagens', 'Id_Viagem', [
        'veiculo'=>'Id_Veiculo','func'=>'Id_Funcionario','carga'=>'Id_Carga',
        'saida'=>'Data_Saida','chegada'=>'Data_Chegada','origem'=>'Origem','destino'=>'Destino'
    ], ['ADMIN','GERENTE','USUARIO','MOTORISTA'], ['ADMIN','GERENTE']],
    'abastecimentos' => ['Abastecimentos', 'Id_Abastecimento', [
        'veiculo'=>'Id_Veiculo','data'=>'Data_Abastecimento','litros'=>'Litros',
        'valor'=>'Valor_Total','posto'=>'Posto'
    ], ['ADMIN','GERENTE','MOTORISTA'], ['ADMIN','GERENTE','MOTORISTA']],
    'manutencoes' => ['Manutencoes', 'Id_Manutencao', [
        'veiculo'=>'Id_Veiculo','tipo'=>'Tipo','descricao'=>'Descricao',
        'data'=>'Data_Manutencao','custo'=>'Custo'
    ], ['ADMIN','GERENTE'], ['ADMIN','GERENTE']],
    'multas' => ['Multas', 'Id_Multa', [
        'veiculo'=>'Id_Veiculo','func'=>'Id_Funcionario','data'=>'Data_Multa',
        'valor'=>'Valor','motivo'=>'Motivo'
    ], ['ADMIN','GERENTE','RH'], ['ADMIN','GERENTE']],
    'banco_horas' => ['Banco_de_horas', 'Id_Banco', [
        'func'=>'Id_Funcionario','horas'=>'Horas','data'=>'Data_Registro'
    ], ['ADMIN','RH'], ['ADMIN','RH']],
    'usuarios' => ['Usuarios', 'Id', [
        'func'=>'Id_Funcionario','email'=>'Email',
        'nivel'=>'Nivel_Permissao','senha'=>'Senha'
    ], ['ADMIN'], ['ADMIN']]
];

$resource = (string)($_GET['r'] ?? '');
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$raw = file_get_contents('php://input');
$body = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : [];
if (!is_array($body)) respond(['erro'=>'JSON enviado é inválido.'], 400);

try {
    if ($resource === 'status') {
        if ($method !== 'GET') respond(['erro'=>'Método não permitido.'], 405);
        $db->query('SELECT 1');
        $emailSchema = true;
        try {
            $db->query('SELECT Email_Verificado_Em FROM Usuarios LIMIT 0');
        } catch (Throwable $ignored) {
            $emailSchema = false;
        }
        $brevoConfigured = defined('BREVO_API_KEY')
            && defined('BREVO_SENDER_EMAIL')
            && defined('APP_BASE_URL')
            && strpos((string)BREVO_API_KEY, 'PREENCHA_') !== 0
            && trim((string)BREVO_API_KEY) !== ''
            && filter_var((string)BREVO_SENDER_EMAIL,FILTER_VALIDATE_EMAIL) !== false;
        respond(['ok'=>true,'api'=>'online','banco'=>'conectado',
                 'campos_email'=>$emailSchema,'brevo_configurado'=>$brevoConfigured,
                 'versao'=>'20261121h']);
    }

    // Endpoints públicos: a sessão autenticada só nasce após confirmar o e-mail.
    if ($resource === 'login') {
        if ($method !== 'POST') respond(['erro'=>'Método não permitido.'], 405);
        requireAuthMigration();
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $password = (string)($body['senha'] ?? '');
        $users = selectRows(
            'SELECT f.Id_Funcionario, f.Nome, u.Id AS Id_Usuario, u.Email,
                    u.Senha, u.Nivel_Permissao, u.Email_Verificado_Em
             FROM Usuarios u INNER JOIN Funcionario f ON f.Id_Funcionario = u.Id_Funcionario
             WHERE u.Email = ? LIMIT 1', [$email]
        );
        $u = $users[0] ?? null;
        if (!$u || !password_verify($password, (string)$u['Senha'])) {
            respond(['erro'=>'E-mail ou senha incorretos.'], 401);
        }
        if ($u['Email_Verificado_Em'] === null) {
            // Se a conta antiga ainda estava logada, revogue qualquer privilégio.
            unset($_SESSION['u'], $_SESSION['pwd_signature'], $_SESSION['csrf']);
            try {
                $sent = issueEmailVerification((int)$u['Id_Usuario'], (string)$u['Email'], (string)$u['Nome']);
            } catch (Throwable $e) {
                // Mostra apenas códigos seguros após a senha correta; nunca expõe resposta bruta da API.
                $diagnostic = 'configuracao_local';
                $http = 0;
                $curl = 0;
                if (preg_match('/diagnóstico=([a-z_]+), HTTP=(\d{1,3}), cURL=(\d{1,3})\./', $e->getMessage(), $matches)) {
                    $diagnostic = $matches[1];
                    $http = (int)$matches[2];
                    $curl = (int)$matches[3];
                }
                error_log('[Frota] E-mail de primeiro acesso falhou; categoria=' . $diagnostic
                    . '; HTTP=' . $http . '; curl_errno=' . $curl);
                $labels = [
                    'transport_error'=>'falha de conexão/TLS',
                    'request_rejected'=>'requisição rejeitada pela Brevo',
                    'authentication_failed'=>'chave API recusada',
                    'sender_or_account_forbidden'=>'remetente ou conta sem autorização',
                    'rate_limited'=>'limite de envio atingido',
                    'provider_error'=>'erro temporário da Brevo',
                    'configuracao_local'=>'configuração local incompleta'
                ];
                $detail = ' Diagnóstico: ' . ($labels[$diagnostic] ?? 'falha de configuração');
                if ($http > 0) $detail .= ' (HTTP ' . $http . ')';
                if ($curl > 0) $detail .= ' (cURL ' . $curl . ')';
                respond(['erro'=>'Não foi possível enviar a confirmação.' . $detail . '.'],503);
            }
            respond(['verification_required'=>true,
                     'mensagem'=>$sent
                         ? 'Enviamos um link de confirmação para seu e-mail. Abra o link e depois faça login.'
                         : 'Seu e-mail ainda não foi confirmado. Se solicitou outro link recentemente, aguarde 90 segundos e tente entrar novamente.']);
        }
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['pwd_signature'] = hash('sha256', (string)$u['Senha']);
        $_SESSION['u'] = ['id'=>(int)$u['Id_Funcionario'],
                          'uid'=>(int)$u['Id_Usuario'],
                          'nome'=>(string)$u['Nome'],
                          'nivel'=>roleName((string)$u['Nivel_Permissao'])];
        respond($_SESSION['u'] + ['csrf'=>$_SESSION['csrf']]);
    }

    if ($resource === 'confirmar_email') {
        if ($method !== 'POST') respond(['erro'=>'Método não permitido.'],405);
        requireAuthMigration();
        $token = (string)($body['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) respond(['erro'=>'Link inválido ou incompleto.'],400);
        $hash = hash('sha256', $token);
        $st = $db->prepare('UPDATE Usuarios SET Email_Verificado_Em=UTC_TIMESTAMP(),
                            Email_Verificacao_Hash=NULL, Email_Verificacao_Expira=NULL, Email_Verificacao_Enviado_Em=NULL
                            WHERE Email_Verificacao_Hash=? AND Email_Verificado_Em IS NULL
                            AND Email_Verificacao_Expira>UTC_TIMESTAMP()');
        $st->execute([$hash]);
        if (!$st->rowCount()) respond(['erro'=>'Este link expirou ou já foi utilizado. Entre novamente para solicitar um novo.'],400);
        respond(['ok'=>true,'mensagem'=>'E-mail confirmado! Agora você pode fazer login.']);
    }

    if ($resource === 'esqueci_senha') {
        if ($method !== 'POST') respond(['erro'=>'Método não permitido.'],405);
        requireAuthMigration();
        $email = strtolower(trim((string)($body['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>100) {
            respond(['erro'=>'Informe um endereço de e-mail válido.'],400);
        }
        // Resposta genérica: não revela quais contas existem no sistema.
        $result = ['ok'=>true,'mensagem'=>'Se o e-mail estiver cadastrado, você receberá instruções para redefinir sua senha.'];
        $st = $db->prepare('SELECT u.Id, u.Email, f.Nome
                            FROM Usuarios u INNER JOIN Funcionario f ON f.Id_Funcionario=u.Id_Funcionario
                            WHERE u.Email=? LIMIT 1');
        $st->execute([$email]);
        $target=$st->fetch();
        if ($target) {
            try {
                issuePasswordReset((int)$target['Id'], (string)$target['Email'], (string)$target['Nome']);
            } catch (Throwable $e) {
                // Não revelamos falha específica a quem não está autenticado.
                error_log('[Frota] Redefinição por e-mail falhou: ' . $e->getMessage());
            }
        }
        respond($result);
    }

    if ($resource === 'redefinir_senha') {
        if ($method !== 'POST') respond(['erro'=>'Método não permitido.'],405);
        requireAuthMigration();
        $token=(string)($body['token']??'');
        $password=enforceNewPassword($body['nova_senha']??'');
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) respond(['erro'=>'Link inválido.'],400);
        $hash=hash('sha256',$token);
        $newHash=password_hash($password,PASSWORD_DEFAULT);
        $st=$db->prepare('UPDATE Usuarios SET Senha=?, Reset_Senha_Hash=NULL,
                          Reset_Senha_Expira=NULL, Reset_Senha_Enviado_Em=NULL
                          WHERE Reset_Senha_Hash=? AND Reset_Senha_Expira>UTC_TIMESTAMP()');
        $st->execute([$newHash,$hash]);
        if (!$st->rowCount()) respond(['erro'=>'Link expirado ou já utilizado. Solicite outro link.'],400);
        // Senha alterada; toda sessão anterior é recusada pelo fingerprint.
        unset($_SESSION['u'], $_SESSION['pwd_signature'], $_SESSION['csrf']);
        respond(['ok'=>true,'mensagem'=>'Senha redefinida. Faça login novamente.']);
    }

    if ($resource === 'logout') {
        if ($method !== 'POST') respond(['erro'=>'Método não permitido.'], 405);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'],
                      $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        respond(['ok'=>true]);
    }

    requireAuthMigration();
    $user = $_SESSION['u'] ?? null;
    if (!$user) respond(['erro'=>'Sessão expirada. Entre novamente.'], 401);
    // Revalida o cargo a cada requisição: revoga acesso mesmo se houver outra aba aberta.
    $idUser = (int)($user['uid'] ?? 0);
    $sessionUser = selectRows(
        'SELECT u.Id, u.Id_Funcionario, u.Nivel_Permissao, u.Email_Verificado_Em, u.Senha, f.Nome
         FROM Usuarios u JOIN Funcionario f ON f.Id_Funcionario = u.Id_Funcionario
         WHERE u.Id = ? AND u.Id_Funcionario = ? LIMIT 1',
        [$idUser, (int)($user['id'] ?? 0)]
    );
    if (!$sessionUser) {
        $_SESSION = [];
        session_destroy();
        respond(['erro'=>'Sessão expirada. Entre novamente.'], 401);
    }
    $u = $sessionUser[0];
    $sig = hash('sha256', (string)$u['Senha']);
    if ($u['Email_Verificado_Em'] === null || !isset($_SESSION['pwd_signature'])
        || !hash_equals((string)$_SESSION['pwd_signature'], $sig)) {
        $_SESSION = [];
        session_destroy();
        respond(['erro'=>'Sua conta precisa confirmar o e-mail ou fazer login novamente após troca de senha.'],401);
    }
    $user = ['id'=>(int)$u['Id_Funcionario'], 'uid'=>(int)$u['Id'],
             'nome'=>(string)$u['Nome'], 'nivel'=>roleName((string)$u['Nivel_Permissao'])];
    $_SESSION['u'] = $user;
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    if ($resource === 'me') respond($user + ['csrf'=>$_SESSION['csrf']]);

    // Restringe escritas à própria origem e token de sessão.
    if (!in_array($method, ['GET','HEAD'], true)) {
        $sentToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals($_SESSION['csrf'], $sentToken)) respond(['erro'=>'Sessão de segurança inválida. Recarregue a página.'], 403);
    }


    if ($resource === 'alterar_senha') {
        if ($method !== 'POST') respond(['erro'=>'Método não permitido.'],405);
        $current = (string)($body['senha_atual'] ?? '');
        $next = enforceNewPassword($body['nova_senha'] ?? '');
        if (!password_verify($current, (string)$u['Senha'])) respond(['erro'=>'Senha atual incorreta.'],400);
        if (password_verify($next, (string)$u['Senha'])) respond(['erro'=>'Escolha uma senha diferente da atual.'],400);
        $newHash = password_hash($next, PASSWORD_DEFAULT);
        $st = $db->prepare('UPDATE Usuarios SET Senha=?, Reset_Senha_Hash=NULL,
                            Reset_Senha_Expira=NULL, Reset_Senha_Enviado_Em=NULL
                            WHERE Id=? AND Senha=?');
        $st->execute([$newHash, $user['uid'], $u['Senha']]);
        if (!$st->rowCount()) respond(['erro'=>'Sua senha foi atualizada em outra sessão. Entre novamente.'],409);
        session_regenerate_id(true);
        $_SESSION['pwd_signature'] = hash('sha256', $newHash);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        respond(['ok'=>true,'csrf'=>$_SESSION['csrf'],'mensagem'=>'Senha alterada com sucesso.']);
    }

    if ($resource === 'lookup') {
        if ($user['nivel'] === 'MOTORISTA') {
            respond([
                'veiculos' => selectRows('SELECT DISTINCT v.Id_Veiculo AS id, v.Placa AS placa, v.Modelo AS modelo
                                          FROM Veiculos v JOIN Viagens vi ON vi.Id_Veiculo=v.Id_Veiculo
                                          WHERE vi.Id_Funcionario=? ORDER BY v.Placa', [$user['id']]),
                'funcionarios' => selectRows('SELECT Id_Funcionario AS id, Nome AS nome FROM Funcionario WHERE Id_Funcionario=?', [$user['id']]),
                'cargas' => selectRows('SELECT DISTINCT c.Id_Carga AS id, c.Descricao AS descricao
                                       FROM Carga c JOIN Viagens vi ON vi.Id_Carga=c.Id_Carga
                                       WHERE vi.Id_Funcionario=? ORDER BY c.Id_Carga DESC', [$user['id']])
            ]);
        }
        $canSeeEmployees = in_array($user['nivel'], ['ADMIN','GERENTE','RH'], true);
        respond([
            'veiculos' => selectRows('SELECT Id_Veiculo AS id, Placa AS placa, Modelo AS modelo FROM Veiculos ORDER BY Placa'),
            'funcionarios' => $canSeeEmployees
              ? selectRows('SELECT Id_Funcionario AS id, Nome AS nome, CPF AS cpf FROM Funcionario ORDER BY Nome')
              : selectRows('SELECT Id_Funcionario AS id, Nome AS nome FROM Funcionario ORDER BY Nome'),
            'cargas' => selectRows('SELECT Id_Carga AS id, Descricao AS descricao FROM Carga ORDER BY Id_Carga DESC')
        ]);
    }

    if ($resource === 'minhas_viagens') {
        if ($method !== 'GET') respond(['erro'=>'Método não permitido.'],405);
        if ($user['nivel'] !== 'MOTORISTA') respond(['erro'=>'Sem permissão.'],403);
        respond(selectRows(
            'SELECT CONCAT(v.Placa, " · ", v.Modelo) AS veiculo,
                    vi.Origem AS origem, vi.Destino AS destino,
                    DATE_FORMAT(vi.Data_Saida, "%Y-%m-%dT%H:%i") AS saida,
                    DATE_FORMAT(vi.Data_Chegada, "%Y-%m-%dT%H:%i") AS chegada
             FROM Viagens vi JOIN Veiculos v ON v.Id_Veiculo=vi.Id_Veiculo
             WHERE vi.Id_Funcionario=? ORDER BY vi.Data_Saida DESC, vi.Id_Viagem DESC', [$user['id']]
        ));
    }

    if ($resource === 'pedido_manutencao') {
        if ($method !== 'POST') respond(['erro'=>'Método não permitido.'],405);
        if ($user['nivel'] !== 'MOTORISTA') respond(['erro'=>'Sem permissão.'],403);
        $vehicle = filter_var($body['veiculo'] ?? null, FILTER_VALIDATE_INT);
        $description = trim((string)($body['descricao'] ?? ''));
        if (!$vehicle || $vehicle < 1 || strlen($description) < 10 || strlen($description) > 2000) {
            respond(['erro'=>'Selecione um veículo e descreva o problema com 10 a 2000 caracteres.'],400);
        }
        $assigned = selectRows('SELECT 1 FROM Viagens WHERE Id_Funcionario=? AND Id_Veiculo=? LIMIT 1', [$user['id'],$vehicle]);
        if (!$assigned) respond(['erro'=>'Veículo não associado às suas viagens.'],403);
        executeSql('INSERT INTO Manutencoes (Id_Veiculo,Tipo,Descricao,Data_Manutencao,Custo)
                    VALUES (?, ?, ?, CURDATE(), NULL)', [$vehicle,'Solicitação',$description]);
        respond(['ok'=>true]);
    }

    if ($resource === 'checklist_diario') {
        if ($method !== 'POST') respond(['erro'=>'Método não permitido.'],405);
        if ($user['nivel'] !== 'MOTORISTA') respond(['erro'=>'Sem permissão.'],403);
        $vehicle = filter_var($body['veiculo'] ?? null, FILTER_VALIDATE_INT);
        $statuses = ['OK', 'ATENCAO'];
        $checks = ['pneus'=>'Pneus', 'luzes'=>'Luzes', 'oleo'=>'Óleo', 'freios'=>'Freios'];
        $details = [];
        foreach ($checks as $key=>$label) {
            $value = (string)($body[$key] ?? '');
            if (!in_array($value,$statuses,true)) respond(['erro'=>'Preencha todos os itens do checklist.'],400);
            $details[] = $label . ': ' . ($value==='OK' ? 'OK' : 'Precisa de atenção');
        }
        $note = trim((string)($body['observacoes'] ?? ''));
        if (strlen($note) > 1000) respond(['erro'=>'Observação muito longa.'],400);
        $assigned = $vehicle ? selectRows('SELECT 1 FROM Viagens WHERE Id_Funcionario=? AND Id_Veiculo=? LIMIT 1', [$user['id'],$vehicle]) : [];
        if (!$assigned) respond(['erro'=>'Veículo não associado às suas viagens.'],403);
        $description = "Checklist do motorista (ID " . $user['id'] . ") em " . date('Y-m-d H:i') . "\n"
                      . implode("\n",$details) . ($note!=='' ? "\nObservações: " . $note : '');
        executeSql('INSERT INTO Manutencoes (Id_Veiculo,Tipo,Descricao,Data_Manutencao,Custo)
                    VALUES (?, ?, ?, CURDATE(), NULL)', [$vehicle,'Checklist diário',$description]);
        respond(['ok'=>true]);
    }

    if ($resource === 'relatorios') {
        if ($method !== 'GET') respond(['erro'=>'Método não permitido.'],405);
        if ($user['nivel'] !== 'ADMIN') respond(['erro'=>'Somente administradores podem acessar relatórios financeiros.'],403);
        $month = (string)($_GET['mes'] ?? '');
        if ($month !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) respond(['erro'=>'Mês inválido.'],400);
        $period = '';
        $params = [];
        if ($month !== '') {
            $start = $month . '-01';
            $end = date('Y-m-d', strtotime($start . ' +1 month'));
            $period = ' AND %s >= ? AND %s < ?';
            $params = [$start,$end,$start,$end,$start,$end];
        }
        $fuel = $month ? sprintf($period,'a.Data_Abastecimento','a.Data_Abastecimento') : '';
        $service = $month ? sprintf($period,'m.Data_Manutencao','m.Data_Manutencao') : '';
        $fine = $month ? sprintf($period,'mu.Data_Multa','mu.Data_Multa') : '';
        $sql = 'SELECT v.Id_Veiculo AS id, v.Placa AS placa, v.Modelo AS modelo,
               COALESCE((SELECT SUM(a.Valor_Total) FROM Abastecimentos a WHERE a.Id_Veiculo=v.Id_Veiculo'.$fuel.'),0) AS combustivel,
               COALESCE((SELECT SUM(m.Custo) FROM Manutencoes m WHERE m.Id_Veiculo=v.Id_Veiculo'.$service.'),0) AS manutencao,
               COALESCE((SELECT SUM(mu.Valor) FROM Multas mu WHERE mu.Id_Veiculo=v.Id_Veiculo'.$fine.'),0) AS multas
               FROM Veiculos v ORDER BY v.Placa';
        $rows = selectRows($sql,$params);
        foreach ($rows as &$row) {
            $row['total'] = (float)$row['combustivel'] + (float)$row['manutencao'] + (float)$row['multas'];
        }
        unset($row);
        respond(['linhas'=>$rows,'mes'=>$month]);
    }

    if ($resource === 'resumo') {
        $cards = [];
        foreach ([
            ['Veículos','veiculos','SELECT COUNT(*) FROM Veiculos',0,0],
            ['Funcionários','funcionarios','SELECT COUNT(*) FROM Funcionario',0,0],
            ['Viagens em andamento','viagens','SELECT COUNT(*) FROM Viagens WHERE Data_Chegada IS NULL',0,1],
            ['Combustível','abastecimentos','SELECT COALESCE(SUM(Valor_Total),0) FROM Abastecimentos',1,0],
            ['Manutenção','manutencoes','SELECT COALESCE(SUM(Custo),0) FROM Manutencoes',1,0],
            ['Multas','multas','SELECT COALESCE(SUM(Valor),0) FROM Multas',1,1]
        ] as [$title,$page,$query,$money,$warning]) {
            if (in_array($user['nivel'], $tables[$page][3], true)) {
                $cards[] = ['t'=>$title,'v'=>(float)$db->query($query)->fetchColumn(),'m'=>$money,'w'=>$warning];
            }
        }
        $open = in_array($user['nivel'], $tables['viagens'][3], true)
            ? selectRows("SELECT CONCAT(v.Placa, ' · ', v.Modelo) AS veiculo, f.Nome AS func,
                                vi.Origem AS origem, vi.Destino AS destino,
                                DATE_FORMAT(vi.Data_Saida,'%Y-%m-%dT%H:%i') AS saida
                         FROM Viagens vi
                         INNER JOIN Veiculos v ON v.Id_Veiculo = vi.Id_Veiculo
                         INNER JOIN Funcionario f ON f.Id_Funcionario = vi.Id_Funcionario
                         WHERE vi.Data_Chegada IS NULL ORDER BY vi.Data_Saida")
            : null;
        respond(['cards'=>$cards,'abertas'=>$open]);
    }

    if (!isset($tables[$resource])) respond(['erro'=>'Recurso inexistente.'], 404);
    [$table,$primaryKey,$columns,$readRoles,$writeRoles] = $tables[$resource];
    $allowed = ($method === 'GET') ? $readRoles : $writeRoles;
    if (!in_array($user['nivel'], $allowed, true)) respond(['erro'=>'Sem permissão para esta ação.'], 403);
    if ($resource === 'abastecimentos' && $user['nivel'] === 'MOTORISTA' && $method !== 'GET' && $method !== 'POST') {
        respond(['erro'=>'Motoristas só podem registrar novos abastecimentos.'],403);
    }

    if ($method === 'GET') {
        $selection = ["$primaryKey AS id"];
        foreach ($columns as $name=>$column) {
            if ($name !== 'senha') $selection[] = "$column AS $name";
        }
        $sql = 'SELECT '.implode(',', $selection)." FROM $table";
        $params = [];
        if ($user['nivel'] === 'MOTORISTA' && $resource === 'viagens') {
            $sql .= ' WHERE Id_Funcionario=?';
            $params[] = $user['id'];
        }
        if ($user['nivel'] === 'MOTORISTA' && $resource === 'abastecimentos') {
            $sql .= ' WHERE Id_Veiculo IN (SELECT DISTINCT Id_Veiculo FROM Viagens WHERE Id_Funcionario=?)';
            $params[] = $user['id'];
        }
        $rows = selectRows($sql." ORDER BY $primaryKey DESC",$params);
        foreach ($rows as &$row) {
            foreach ($row as &$value) {
                if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $value)) {
                    $value = substr($value, 0, 10).'T'.substr($value, 11, 5);
                }
            }
            unset($value);
            if ($resource === 'usuarios') {
                $row['nivel'] = roleName((string)$row['nivel']);
                $row['senha'] = '••••••';
            }
        }
        unset($row);
        respond($rows);
    }

    if ($method === 'DELETE') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) respond(['erro'=>'ID inválido.'], 400);
        if ($resource === 'usuarios' && $id === (int)$user['uid']) {
            respond(['erro'=>'Não é possível excluir a própria conta de administrador.'],403);
        }
        executeSql("DELETE FROM $table WHERE $primaryKey = ?", [$id]);
        respond(['ok'=>true]);
    }

    if (!in_array($method, ['POST','PUT'], true)) respond(['erro'=>'Método não permitido.'], 405);
    $data = [];
    foreach ($columns as $name=>$column) {
        if (array_key_exists($name,$body)) {
            $value = $body[$name];
            $data[$column] = ($value === '' || $value === null) ? null : $value;
        }
    }
    if ($resource === 'usuarios') {
        $email = trim((string)($data['Email'] ?? ''));
        if (array_key_exists('Email', $data)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['erro'=>'Informe um e-mail válido.'], 400);
            $data['Email'] = $email;
        } elseif ($method === 'POST') {
            respond(['erro'=>'Informe o e-mail do usuário.'], 400);
        }
        if (isset($data['Nivel_Permissao'])) {
            $role = roleName((string)$data['Nivel_Permissao']);
            if (!in_array($role, ['ADMIN','GERENTE','USUARIO','MOTORISTA','RH'], true)) {
                respond(['erro'=>'Nível de permissão inválido.'], 400);
            }
            $data['Nivel_Permissao'] = ($role === 'MOTORISTA') ? 'Motorista' : $role;
        }
        if (!empty($body['senha'])) {
            if (strlen((string)$body['senha']) < 8) respond(['erro'=>'A senha deve ter pelo menos 8 caracteres.'], 400);
            $data['Senha'] = password_hash((string)$body['senha'], PASSWORD_DEFAULT);
        } else {
            unset($data['Senha']);
            if ($method === 'POST') respond(['erro'=>'Informe uma senha.'], 400);
        }
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $existing = selectRows(
            'SELECT Id FROM Usuarios WHERE (Email = ? OR Id_Funcionario = ?) '.($method === 'PUT' ? 'AND Id <> ?' : '').' LIMIT 1',
            $method === 'PUT' ? [$data['Email'] ?? '', $data['Id_Funcionario'] ?? 0, $id ?: 0] : [$data['Email'] ?? '', $data['Id_Funcionario'] ?? 0]
        );
        if ($existing) respond(['erro'=>'Este e-mail ou funcionário já possui uma conta.'], 400);
    }

    if ($resource === 'usuarios' && $method === 'PUT' && isset($data['Email'])) {
        $targetId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$targetId) respond(['erro'=>'ID inválido.'],400);
        $old = selectRows('SELECT Email FROM Usuarios WHERE Id=?', [$targetId]);
        if ($old && strcasecmp(trim((string)$old[0]['Email']),trim((string)$data['Email'])) !== 0) {
            $data['Email_Verificado_Em'] = null;
            $data['Email_Verificacao_Hash'] = null;
            $data['Email_Verificacao_Expira'] = null;
            $data['Email_Verificacao_Enviado_Em'] = null;
            $data['Reset_Senha_Hash'] = null;
            $data['Reset_Senha_Expira'] = null;
            $data['Reset_Senha_Enviado_Em'] = null;
        }
    }

    if ($method === 'POST') {
        if (!$data) respond(['erro'=>'Nenhum dado informado.'], 400);
        if ($resource === 'abastecimentos' && $user['nivel'] === 'MOTORISTA') {
            $vehicle = filter_var($data['Id_Veiculo'] ?? null, FILTER_VALIDATE_INT);
            $assigned = $vehicle ? selectRows('SELECT 1 FROM Viagens WHERE Id_Funcionario=? AND Id_Veiculo=? LIMIT 1',[$user['id'],$vehicle]) : [];
            if (!$assigned) respond(['erro'=>'Veículo não associado às suas viagens.'],403);
        }
        $fields = array_keys($data);
        $marks = array_fill(0, count($fields), '?');
        executeSql("INSERT INTO $table (".implode(',',$fields).") VALUES (".implode(',',$marks).")",array_values($data));
    } else {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) respond(['erro'=>'ID inválido.'], 400);
        if ($resource === 'usuarios' && $id === (int)$user['uid']
            && isset($data['Nivel_Permissao']) && roleName((string)$data['Nivel_Permissao']) !== 'ADMIN') {
            respond(['erro'=>'Você não pode retirar seu próprio acesso de administrador.'],403);
        }
        if (!$data) respond(['erro'=>'Nada para atualizar.'], 400);
        unset($data[$primaryKey]);
        $sets = [];
        foreach (array_keys($data) as $column) $sets[] = "$column = ?";
        executeSql("UPDATE $table SET ".implode(',',$sets)." WHERE $primaryKey = ?", [...array_values($data), $id]);
    }
    respond(['ok'=>true]);
} catch (PDOException $e) {
    error_log('[Controle de Frota] Erro SQL: ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        respond(['erro'=>'Registro duplicado ou vinculado a outros dados. Confira os IDs informados.'], 400);
    }
    respond(['erro'=>'Erro no banco de dados durante a operação. Confira os campos e a estrutura das tabelas.'], 500);
} catch (Throwable $e) {
    error_log('[Controle de Frota] Erro PHP: ' . $e->getMessage());
    respond(['erro'=>'Erro interno no servidor. Confira os logs de erro do PHP.'], 500);
}

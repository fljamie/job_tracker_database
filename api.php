<?php
/**
 * Job Application Tracker API  v2.12
 * - Suppresses PHP deprecation warnings so they don't break JSON output
 * - Uses flat JSON files for "local" mode (no SQLite extension needed)
 * - curl_close() removed (deprecated in PHP 8.5)
 * - v2.12: logo restored, cover letter generation + DOCX save
 */

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$config = $input['config'] ?? null;

function respond($success, $data = null, $error = null) {
    echo json_encode(['success' => $success, 'data' => $data, 'error' => $error]);
    exit;
}

// ── Data directory ────────────────────────────────────────────────────────────
// Supports optional custom path from config['dataDir']
function dataDir($config = null) {
    if ($config && !empty($config['dataDir']) && is_string($config['dataDir'])) {
        $dir = rtrim($config['dataDir'], '/\\');
    } else {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

// ── JSON file path ─────────────────────────────────────────────────────────────
function jsonPath($config) {
    // Accept either a full config array or a bare filename string (legacy)
    if (is_string($config)) {
        $name    = basename($config);
        $cfgArr  = null;
    } else {
        $name    = basename($config['filename'] ?? 'jobtracker');
        $cfgArr  = $config;
    }
    $name = preg_replace('/\.(db|json|sqlite)$/i', '', $name);
    return dataDir($cfgArr) . DIRECTORY_SEPARATOR . $name . '.json';
}

// ── Load / save JSON store ─────────────────────────────────────────────────────
function loadStore($path) {
    if (!file_exists($path)) {
        return ['jobs' => [], 'settings' => [], 'next_id' => 1];
    }
    $data = json_decode(file_get_contents($path), true);
    if (!$data) {
        return ['jobs' => [], 'settings' => [], 'next_id' => 1];
    }
    if (empty($data['next_id'])) {
        $maxId = 0;
        foreach ($data['jobs'] ?? [] as $j) {
            if (($j['id'] ?? 0) > $maxId) $maxId = $j['id'];
        }
        $data['next_id'] = $maxId + 1;
    }
    return $data;
}

function saveStore($path, $store) {
    return file_put_contents($path, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

// ── MySQL helpers ──────────────────────────────────────────────────────────────
function getConnection($c) {
    try {
        return new PDO(
            "mysql:host={$c['host']};port={$c['port']};charset=utf8mb4",
            $c['user'], $c['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) { return null; }
}

function getDBConnection($c) {
    try {
        return new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",
            $c['user'], $c['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) { return null; }
}

// ── Router ─────────────────────────────────────────────────────────────────────
switch ($action) {
    case 'getServerInfo':         getServerInfo();                                       break;
    case 'testMysqlConnection':   testMysqlConn($config);                                break;
    case 'checkConnection':       checkConnection($config);                              break;
    case 'initDatabase':          initDatabase($config);                                 break;
    case 'getJobs':               getJobs($config);                                      break;
    case 'saveJob':               saveJob($config, $input['job'] ?? []);                 break;
    case 'deleteJob':             deleteJob($config, $input['jobId'] ?? 0);              break;
    case 'saveResume':            saveResume($config, $input['resume'] ?? []);           break;
    case 'getResume':             getResume($config);                                    break;
    case 'exportData':            exportData($config);                                   break;
    case 'importData':            importData($config, $input['data'] ?? []);             break;
    case 'aiProxy':               aiProxy($input['apiKey'] ?? '', $input['payload'] ?? []); break;
    case 'shutdown':              shutdownServer();                                       break;
    default:                      respond(false, null, 'Invalid action');
}

// ═════════════════════════════════════════════════════════════════════════════
//  SERVER INFO
// ═════════════════════════════════════════════════════════════════════════════
function getServerInfo() {
    $defaultDataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    respond(true, [
        'defaultDataDir' => str_replace('\\', '/', $defaultDataDir),
        'defaultDataDirNative' => $defaultDataDir,
        'phpVersion'    => PHP_VERSION,
        'os'            => PHP_OS,
    ]);
}

// ═════════════════════════════════════════════════════════════════════════════
//  CONNECTION / INIT
// ═════════════════════════════════════════════════════════════════════════════
function testMysqlConn($c) {
    if (!$c) { respond(false, null, 'No config provided'); return; }
    $pdo = getConnection($c);
    if ($pdo) respond(true, ['message' => 'MySQL connection successful!']);
    else      respond(false, null, 'Unable to connect. Check host, port, username and password.');
}

function checkConnection($c) {
    if (!$c) { respond(false, null, 'No config provided'); return; }
    $mode = $c['mode'] ?? 'local';

    if ($mode === 'local') {
        $path = jsonPath($c);
        $dir  = dataDir($c);
        if (!is_writable($dir)) {
            respond(false, null, "Data directory is not writable: $dir"); return;
        }
        if (!file_exists($path)) {
            $store = ['jobs' => [], 'settings' => [], 'next_id' => 1];
            if (!saveStore($path, $store)) {
                respond(false, null, "Cannot create database file: $path"); return;
            }
        }
        respond(true);
        return;
    }

    $pdo = getDBConnection($c);
    if (!$pdo) { respond(false, null, 'Unable to connect to MySQL. Check credentials.'); return; }
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'jobs'");
        if ($stmt->fetch()) { runMigrations($pdo); respond(true); }
        else respond(false, null, 'Database not initialized');
    } catch (PDOException $e) { respond(false, null, $e->getMessage()); }
}

function initDatabase($c) {
    $mode = $c['mode'] ?? 'local';

    if ($mode === 'local') {
        $path  = jsonPath($c);
        $store = ['jobs' => [], 'settings' => [], 'next_id' => 1];
        if (saveStore($path, $store)) respond(true, ['message' => 'Local database created: ' . basename($path)]);
        else respond(false, null, 'Cannot write to: ' . dataDir($c));
        return;
    }

    $pdo = getConnection($c);
    if (!$pdo) { respond(false, null, 'Cannot connect to MySQL'); return; }
    try {
        $db = $c['database'];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db}`");
        createMySQLTables($pdo);
        respond(true, ['message' => 'MySQL database initialized']);
    } catch (PDOException $e) { respond(false, null, $e->getMessage()); }
}

function createMySQLTables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        url TEXT, company_website TEXT,
        status ENUM('Submitted','In Progress','Interviewing','Rejected','Closed') DEFAULT 'Submitted',
        interview_round ENUM('','HR Screen','Managers','Peers','Presentation/Demo','Executive Team') DEFAULT '',
        application_date DATE,
        contacts JSON, documents JSON, communications JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status(status), INDEX idx_created(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    runMigrations($pdo);
}

function runMigrations($pdo) {
    try {
        $stmt = $pdo->query("DESCRIBE jobs");
        $cols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $add  = [
            'company_website'  => "ALTER TABLE jobs ADD COLUMN company_website TEXT AFTER url",
            'communications'   => "ALTER TABLE jobs ADD COLUMN communications JSON AFTER documents",
            'application_date' => "ALTER TABLE jobs ADD COLUMN application_date DATE AFTER interview_round"
        ];
        foreach ($add as $col => $sql) {
            if (!in_array($col, $cols)) {
                try { $pdo->exec($sql); } catch (PDOException $e) {}
            }
        }
    } catch (PDOException $e) {}
}

// ═════════════════════════════════════════════════════════════════════════════
//  JOBS CRUD
// ═════════════════════════════════════════════════════════════════════════════
function getJobs($c) {
    $mode = $c['mode'] ?? 'local';
    if ($mode === 'local') {
        $store = loadStore(jsonPath($c));
        $jobs  = $store['jobs'];
        usort($jobs, fn($a,$b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        respond(true, $jobs); return;
    }
    $pdo = getDBConnection($c);
    if (!$pdo) { respond(false, null, 'Database connection failed'); return; }
    try {
        respond(true, $pdo->query("SELECT * FROM jobs ORDER BY created_at DESC")->fetchAll());
    } catch (PDOException $e) { respond(false, null, $e->getMessage()); }
}

function saveJob($c, $job) {
    $mode = $c['mode'] ?? 'local';
    if ($mode === 'local') {
        $path  = jsonPath($c);
        $store = loadStore($path);
        $now   = date('Y-m-d H:i:s');
        $appDate = !empty($job['application_date']) ? $job['application_date'] : date('Y-m-d');
        if (!empty($job['id'])) {
            foreach ($store['jobs'] as &$j) {
                if ($j['id'] == $job['id']) {
                    $j['company']         = $job['company'];
                    $j['title']           = $job['title'];
                    $j['url']             = $job['url'] ?? '';
                    $j['company_website'] = $job['company_website'] ?? '';
                    $j['status']          = $job['status'];
                    $j['interview_round'] = $job['interview_round'] ?? '';
                    $j['application_date']= $appDate;
                    $j['contacts']        = $job['contacts'] ?? '[]';
                    $j['documents']       = $job['documents'] ?? '[]';
                    $j['communications']  = $job['communications'] ?? '[]';
                    $j['updated_at']      = $now;
                    break;
                }
            }
            unset($j);
        } else {
            $store['jobs'][]  = [
                'id'              => $store['next_id'],
                'company'         => $job['company'],
                'title'           => $job['title'],
                'url'             => $job['url'] ?? '',
                'company_website' => $job['company_website'] ?? '',
                'status'          => $job['status'] ?? 'Submitted',
                'interview_round' => $job['interview_round'] ?? '',
                'application_date'=> $appDate,
                'contacts'        => $job['contacts'] ?? '[]',
                'documents'       => $job['documents'] ?? '[]',
                'communications'  => $job['communications'] ?? '[]',
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
            $store['next_id'] = $store['next_id'] + 1;
        }
        if (saveStore($path, $store)) respond(true, ['message' => 'Job saved']);
        else respond(false, null, 'Failed to write database file');
        return;
    }
    $pdo = getDBConnection($c);
    if (!$pdo) { respond(false, null, 'Database connection failed'); return; }
    try {
        $appDate = !empty($job['application_date']) ? $job['application_date'] : date('Y-m-d');
        if (empty($job['id'])) {
            $pdo->prepare("INSERT INTO jobs (company,title,url,company_website,status,interview_round,application_date,contacts,documents,communications) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$job['company'],$job['title'],$job['url']??'',$job['company_website']??'',$job['status'],$job['interview_round']??'',$appDate,$job['contacts']??'[]',$job['documents']??'[]',$job['communications']??'[]']);
        } else {
            $pdo->prepare("UPDATE jobs SET company=?,title=?,url=?,company_website=?,status=?,interview_round=?,application_date=?,contacts=?,documents=?,communications=? WHERE id=?")
                ->execute([$job['company'],$job['title'],$job['url']??'',$job['company_website']??'',$job['status'],$job['interview_round']??'',$appDate,$job['contacts']??'[]',$job['documents']??'[]',$job['communications']??'[]',$job['id']]);
        }
        respond(true, ['message' => 'Job saved']);
    } catch (PDOException $e) { respond(false, null, $e->getMessage()); }
}

function deleteJob($c, $jobId) {
    $mode = $c['mode'] ?? 'local';
    if ($mode === 'local') {
        $path  = jsonPath($c);
        $store = loadStore($path);
        $store['jobs'] = array_values(array_filter($store['jobs'], fn($j) => $j['id'] != $jobId));
        if (saveStore($path, $store)) respond(true); else respond(false, null, 'Write failed');
        return;
    }
    $pdo = getDBConnection($c);
    if (!$pdo) { respond(false, null, 'Database connection failed'); return; }
    try {
        $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$jobId]);
        respond(true);
    } catch (PDOException $e) { respond(false, null, $e->getMessage()); }
}

// ═════════════════════════════════════════════════════════════════════════════
//  RESUME
// ═════════════════════════════════════════════════════════════════════════════
function saveResume($c, $resume) {
    $mode = $c['mode'] ?? 'local';
    if ($mode === 'local') {
        $path  = jsonPath($c);
        $store = loadStore($path);
        $store['settings']['resume'] = $resume;
        if (saveStore($path, $store)) respond(true); else respond(false, null, 'Write failed');
        return;
    }
    $pdo = getDBConnection($c);
    if (!$pdo) { respond(false, null, 'Database connection failed'); return; }
    try {
        $val = json_encode($resume);
        $pdo->prepare("INSERT INTO user_settings (setting_key,setting_value) VALUES ('resume',?) ON DUPLICATE KEY UPDATE setting_value=?")
            ->execute([$val, $val]);
        respond(true);
    } catch (PDOException $e) { respond(false, null, $e->getMessage()); }
}

function getResume($c) {
    $mode = $c['mode'] ?? 'local';
    if ($mode === 'local') {
        $store = loadStore(jsonPath($c));
        respond(true, $store['settings']['resume'] ?? null); return;
    }
    $pdo = getDBConnection($c);
    if (!$pdo) { respond(false, null, 'Database connection failed'); return; }
    try {
        $st = $pdo->prepare("SELECT setting_value FROM user_settings WHERE setting_key='resume'");
        $st->execute();
        $row = $st->fetch();
        respond(true, $row ? json_decode($row['setting_value'], true) : null);
    } catch (PDOException $e) { respond(false, null, $e->getMessage()); }
}

// ═════════════════════════════════════════════════════════════════════════════
//  EXPORT / IMPORT
// ═════════════════════════════════════════════════════════════════════════════
function exportData($c) {
    $mode = $c['mode'] ?? 'local';
    if ($mode === 'local') {
        $store = loadStore(jsonPath($c));
        respond(true, ['jobs' => $store['jobs'], 'resume' => $store['settings']['resume'] ?? null, 'count' => count($store['jobs'])]);
        return;
    }
    $pdo = getDBConnection($c);
    if (!$pdo) { respond(false, null, 'Cannot connect to source database'); return; }
    try {
        $jobs   = $pdo->query("SELECT * FROM jobs ORDER BY id")->fetchAll();
        $resume = null;
        try {
            $r = $pdo->prepare("SELECT setting_value FROM user_settings WHERE setting_key='resume'");
            $r->execute(); $row = $r->fetch();
            if ($row) $resume = json_decode($row['setting_value'], true);
        } catch (Exception $e) {}
        respond(true, ['jobs' => $jobs, 'resume' => $resume, 'count' => count($jobs)]);
    } catch (PDOException $e) { respond(false, null, $e->getMessage()); }
}

function importData($c, $data) {
    $mode = $c['mode'] ?? 'local';
    if ($mode === 'local') {
        $path  = jsonPath($c);
        $store = loadStore($path);
        $imported = 0; $skipped = 0;
        foreach ($data['jobs'] ?? [] as $job) {
            $isDup = false;
            foreach ($store['jobs'] as $existing) {
                if ($existing['company'] === $job['company'] && $existing['title'] === $job['title']) { $isDup = true; break; }
            }
            if ($isDup) { $skipped++; continue; }
            $store['jobs'][]  = array_merge($job, ['id' => $store['next_id'], 'updated_at' => date('Y-m-d H:i:s')]);
            $store['next_id'] = $store['next_id'] + 1;
            $imported++;
        }
        if (!empty($data['resume']) && empty($store['settings']['resume'])) {
            $store['settings']['resume'] = $data['resume'];
        }
        if (saveStore($path, $store)) respond(true, ['imported' => $imported, 'skipped' => $skipped]);
        else respond(false, null, 'Failed to write to: ' . dataDir($c));
        return;
    }
    $pdo = getDBConnection($c);
    if (!$pdo) { respond(false, null, 'Cannot connect to destination'); return; }
    try { createMySQLTables($pdo); } catch (Exception $e) {}
    $imported = 0; $skipped = 0;
    try {
        foreach ($data['jobs'] ?? [] as $job) {
            $ck = $pdo->prepare("SELECT id FROM jobs WHERE company=? AND title=?");
            $ck->execute([$job['company'], $job['title']]);
            if ($ck->fetch()) { $skipped++; continue; }
            $pdo->prepare("INSERT INTO jobs (company,title,url,company_website,status,interview_round,application_date,contacts,documents,communications,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$job['company'],$job['title'],$job['url']??'',$job['company_website']??'',$job['status']??'Submitted',$job['interview_round']??'',$job['application_date']??null,$job['contacts']??'[]',$job['documents']??'[]',$job['communications']??'[]',$job['created_at']??date('Y-m-d H:i:s')]);
            $imported++;
        }
        if (!empty($data['resume'])) {
            $has = $pdo->prepare("SELECT id FROM user_settings WHERE setting_key='resume'"); $has->execute();
            if (!$has->fetch()) {
                $pdo->prepare("INSERT IGNORE INTO user_settings (setting_key,setting_value) VALUES ('resume',?)")->execute([json_encode($data['resume'])]);
            }
        }
        respond(true, ['imported' => $imported, 'skipped' => $skipped]);
    } catch (PDOException $e) { respond(false, null, $e->getMessage()); }
}

// ═════════════════════════════════════════════════════════════════════════════
//  AI PROXY  — Anthropic, OpenAI, Gemini, Grok
// ═════════════════════════════════════════════════════════════════════════════
function aiProxy($apiKey, $payload) {
    if (empty($apiKey)) { respond(false, null, 'API key required'); return; }
    if (!function_exists('curl_init')) {
        respond(false, null, 'PHP cURL extension is not enabled. Open php.ini, uncomment extension=curl, then restart the server.'); return;
    }

    // Detect provider from key format
    $provider = 'anthropic';
    if (strpos($apiKey, 'sk-ant-') === 0)   $provider = 'anthropic';
    elseif (strpos($apiKey, 'xai-') === 0)  $provider = 'grok';
    elseif (strpos($apiKey, 'AIza') === 0)  $provider = 'gemini';
    elseif (strpos($apiKey, 'sk-') === 0)   $provider = 'openai';

    $configs = [
        'anthropic' => [
            'url'     => 'https://api.anthropic.com/v1/messages',
            'headers' => ['Content-Type: application/json','x-api-key: '.$apiKey,'anthropic-version: 2023-06-01'],
            'type'    => 'anthropic',
        ],
        'openai' => [
            'url'     => 'https://api.openai.com/v1/chat/completions',
            'headers' => ['Content-Type: application/json','Authorization: Bearer '.$apiKey],
            'type'    => 'openai',
        ],
        'gemini' => [
            'url'     => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'headers' => ['Content-Type: application/json','Authorization: Bearer '.$apiKey],
            'type'    => 'openai',
        ],
        'grok' => [
            'url'     => 'https://api.x.ai/v1/chat/completions',
            'headers' => ['Content-Type: application/json','Authorization: Bearer '.$apiKey],
            'type'    => 'openai',
        ],
    ];

    $cfg = $configs[$provider];

    // Build request body — normalise for OpenAI-compatible providers
    if ($cfg['type'] === 'openai') {
        $oai = $payload;
        if (!empty($payload['system']) && is_string($payload['system'])) {
            $oai['messages'] = array_merge([['role'=>'system','content'=>$payload['system']]], $payload['messages'] ?? []);
            unset($oai['system']);
        }
        // Map Anthropic model names → provider equivalents
        $modelMap = [
            'claude-haiku-4-5-20251001' => ['openai'=>'gpt-4o-mini','gemini'=>'gemini-2.0-flash','grok'=>'grok-3-mini'],
            'claude-haiku-3-5-20241022' => ['openai'=>'gpt-4o-mini','gemini'=>'gemini-2.0-flash','grok'=>'grok-3-mini'],
            'claude-3-5-haiku-20241022' => ['openai'=>'gpt-4o-mini','gemini'=>'gemini-2.0-flash','grok'=>'grok-3-mini'],
            'claude-3-haiku-20240307'   => ['openai'=>'gpt-4o-mini','gemini'=>'gemini-1.5-flash','grok'=>'grok-3-mini'],
        ];
        $m = $oai['model'] ?? '';
        if (isset($modelMap[$m][$provider])) $oai['model'] = $modelMap[$m][$provider];
        unset($oai['tools']);
        $postData = json_encode($oai);
    } else {
        $postData = json_encode($payload);
    }

    // SSL cert
    $certPath = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cacert.pem';
    if (!file_exists($certPath)) {
        $pem = @file_get_contents('https://curl.se/ca/cacert.pem');
        if ($pem) @file_put_contents($certPath, $pem);
    }
    $sslOpts = file_exists($certPath)
        ? [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_CAINFO => $certPath]
        : [CURLOPT_SSL_VERIFYPEER => false];

    $ch = curl_init($cfg['url']);
    curl_setopt_array($ch, $sslOpts + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_HTTPHEADER     => $cfg['headers'],
        CURLOPT_TIMEOUT        => 120,
    ]);

    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);

    if ($curlErr) { respond(false, null, 'cURL error: '.$curlErr); return; }

    $result = json_decode($body, true);
    if ($httpCode !== 200) {
        respond(false, null, ucfirst($provider)." API error ($httpCode): ".($result['error']['message'] ?? $body)); return;
    }

    // Normalise to Anthropic-style response so frontend is provider-agnostic
    if ($cfg['type'] === 'openai') {
        $text   = $result['choices'][0]['message']['content'] ?? '';
        $result = ['content' => [['type' => 'text', 'text' => $text]]];
    }
    respond(true, $result);
}

// ═════════════════════════════════════════════════════════════════════════════
//  SHUTDOWN
// ═════════════════════════════════════════════════════════════════════════════
function shutdownServer() {
    echo json_encode(['success' => true, 'data' => ['message' => 'Server shutting down...']]);
    while (ob_get_level() > 0) ob_end_flush();
    flush();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    usleep(500000);
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $vbs = sys_get_temp_dir() . '\\shutdown_php.vbs';
        file_put_contents($vbs, "WScript.Sleep 1000\r\nCreateObject(\"WScript.Shell\").Run \"taskkill /F /IM php.exe\", 0, False");
        pclose(popen('wscript "'.$vbs.'"', 'r'));
    }
    exit(0);
}
?>

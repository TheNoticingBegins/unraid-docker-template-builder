<?php


function collectConfigs() {
    // Ports
    $ports = []; $i = 0;
    while (isset($_POST["p_name_$i"])) {
        $name = $_POST["p_name_$i"]; $target = $_POST["p_target_$i"];
        $def = $_POST["p_default_$i"] ?? ''; $mode = $_POST["p_mode_$i"] ?? 'tcp';
        if ($name || $target) $ports[] = "$name|$target|$def|$mode||true|always|false";
        $i++;
    }
    if (empty($ports)) { $ports = [$name ?? '' . '|' . ($target ?? '') . '|' . ($def ?? '') . '|' . ($mode ?? 'tcp')]; }
    
    // Volumes
    $vols = []; $i = 0;
    while (isset($_POST["v_name_$i"])) {
        $name = $_POST["v_name_$i"]; $target = $_POST["v_target_$i"];
        $def = $_POST["v_default_$i"] ?? ''; $mode = $_POST["v_mode_$i"] ?? 'rw';
        if ($name || $target) $vols[] = "$name|$target|$def|$mode||true|always|false";
        $i++;
    }
    
    // Env vars
    $envs = []; $i = 0;
    while (isset($_POST["e_name_$i"])) {
        $name = $_POST["e_name_$i"]; $target = $_POST["e_target_$i"];
        $def = $_POST["e_default_$i"] ?? ''; $req = $_POST["e_required_$i"] ?? 'false';
        if ($name || $target) $envs[] = "$name|$target|$def|||$req|always|false";
        $i++;
    }
    
    // Devices
    $devs = []; $i = 0;
    while (isset($_POST["d_name_$i"])) {
        $name = $_POST["d_name_$i"]; $target = $_POST["d_target_$i"];
        if ($name || $target) $devs[] = "$name|$target|||||always|false";
        $i++;
    }
    
    return [
        'env_config' => implode("\n", $envs),
        'port_config' => implode("\n", $ports),
        'vol_config' => implode("\n", $vols),
        'dev_config' => implode("\n", $devs),
    ];
}

function getDefaultTemplateDir() { return "/boot/config/plugins/dockerMan/templates-user"; }

function getCfgPath() { return "/boot/config/plugins/docker-builder/builder.cfg"; }

function getConfig() {
    $cfg = ['template_dir' => getDefaultTemplateDir()];
    if (!file_exists(getCfgPath())) return $cfg;
    foreach (file(getCfgPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') === false || $line[0] === '#') continue;
        [$k, $v] = explode('=', $line, 2);
        $cfg[trim($k)] = trim($v);
    }
    return $cfg;
}

function saveConfig($cfg) {
    $d = dirname(getCfgPath());
    if (!is_dir($d)) @mkdir($d, 0755, true);
    $out = '';
    foreach ($cfg as $k => $v) {
        $q = "'" . str_replace("'", "'\\''", $v) . "'";
        $out .= "$k=$q\n";
    }
    file_put_contents(getCfgPath(), $out);
}

function getTemplateDir() {
    $dir = isset($_POST['template_dir']) ? trim($_POST['template_dir']) : '';
    if ($dir === '') $dir = isset($_GET['template_dir']) ? trim($_GET['template_dir']) : '';
    if ($dir === '') { $c = getConfig(); $dir = $c['template_dir'] ?? ''; }
    if ($dir === '') $dir = getDefaultTemplateDir();
    return rtrim($dir, '/');
}

function dockerNetworks() {
    $nets = [];
    exec("docker network ls --format '{{.Name}}' 2>/dev/null", $out);
    foreach ($out as $n) {
        $n = trim($n);
        if ($n !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $n)) $nets[] = $n;
    }
    return array_values(array_unique($nets));
}

function buildDockerRunCommand($name, $repo, $net, $priv, $shell, $cpuset, $extra, $post, $envVars, $ports, $volumes, $devices) {
    $cmd = "docker run -d \\\n";
    if ($name) $cmd .= "  --name=" . escapeshellarg($name) . " \\\n";
    if ($net === 'host') $cmd .= "  --network=host \\\n";
    elseif ($net === 'none') $cmd .= "  --network=none \\\n";
    elseif ($net && $net !== 'bridge') $cmd .= "  --network=" . escapeshellarg($net) . " \\\n";
    if ($priv === 'true') $cmd .= "  --privileged \\\n";
    if ($shell) $cmd .= "  --entrypoint=" . escapeshellarg($shell) . " \\\n";
    if ($cpuset) $cmd .= "  --cpuset-cpus=" . escapeshellarg($cpuset) . " \\\n";
    foreach (explode("\n", $envVars) as $line) {
        $line = trim($line); if (!$line) continue;
        if (strpos($line, '=') !== false) $cmd .= "  -e " . escapeshellarg($line) . " \\\n";
    }
    foreach (explode("\n", $ports) as $line) {
        $line = trim($line); if (!$line || strpos($line, ':') === false) continue;
        $cmd .= "  -p " . escapeshellarg($line) . " \\\n";
    }
    foreach (explode("\n", $volumes) as $line) {
        $line = trim($line); if (!$line || strpos($line, ':') === false) continue;
        $cmd .= "  -v " . escapeshellarg($line) . " \\\n";
    }
    foreach (explode("\n", $devices) as $line) {
        $line = trim($line); if (!$line) continue;
        $cmd .= "  --device=" . escapeshellarg($line) . " \\\n";
    }
    if ($extra) $cmd .= "  " . $extra . " \\\n";
    if ($repo) $cmd .= "  " . escapeshellarg($repo);
    if ($post) $cmd .= " " . $post;
    return $cmd;
}

function saveDockerTemplate($name, $repo, $reg, $net, $priv, $shell, $support, $project, $readme, $overview, $cat, $webui, $templateurl, $icon, $extra, $post, $cpuset, $requires, $envCfg, $portCfg, $volCfg, $devCfg, $dir) {
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    if (!$safe) return "Error: Invalid container name";
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $x = "<?xml version=\"1.0\"?>\n<Container version=\"2\">\n";
    if ($name) $x .= "  <Name>" . h($name) . "</Name>\n";
    if ($repo) $x .= "  <Repository>" . h($repo) . "</Repository>\n";
    if ($reg) $x .= "  <Registry>" . h($reg) . "</Registry>\n";
    if ($net) $x .= "  <Network>" . h($net) . "</Network>\n";
    if ($priv === 'true') $x .= "  <Privileged>true</Privileged>\n";
    else $x .= "  <Privileged>false</Privileged>\n";
    if ($shell) $x .= "  <Shell>" . h($shell) . "</Shell>\n";
    if ($support) $x .= "  <Support>" . h($support) . "</Support>\n";
    if ($project) $x .= "  <Project>" . h($project) . "</Project>\n";
    if ($readme) $x .= "  <ReadMe>" . h($readme) . "</ReadMe>\n";
    if ($overview) $x .= "  <Overview>" . h($overview) . "</Overview>\n";
    if ($cat) $x .= "  <Category>" . h($cat) . "</Category>\n";
    if ($webui) $x .= "  <WebUI>" . h($webui) . "</WebUI>\n";
    if ($templateurl) $x .= "  <TemplateURL>" . h($templateurl) . "</TemplateURL>\n";
    if ($icon) $x .= "  <Icon>" . h($icon) . "</Icon>\n";
    if ($extra) $x .= "  <ExtraParams>" . h($extra) . "</ExtraParams>\n";
    if ($post) $x .= "  <PostArgs>" . h($post) . "</PostArgs>\n";
    if ($cpuset) $x .= "  <CPUset>" . h($cpuset) . "</CPUset>\n";
    if ($requires) $x .= "  <Requires>" . h($requires) . "</Requires>\n";

    $x .= "  <DateInstalled>" . time() . "</DateInstalled>\n";

    // Process Config entries from CSV-like input
    foreach (explode("\n", $envCfg) as $line) { $line = trim($line); if ($line) $x .= makeConfig($line, 'Variable'); }
    foreach (explode("\n", $portCfg) as $line) { $line = trim($line); if ($line) $x .= makeConfig($line, 'Port'); }
    foreach (explode("\n", $volCfg) as $line) { $line = trim($line); if ($line) $x .= makeConfig($line, 'Path'); }
    foreach (explode("\n", $devCfg) as $line) { $line = trim($line); if ($line) $x .= makeConfig($line, 'Device'); }

    $x .= "</Container>\n";
    $f = "$dir/$safe.xml";
    if (file_put_contents($f, $x)) { saveConfig(['template_dir' => $dir]); return "Saved to $f"; }
    return "Error: Could not write template.";
}

function h($s) { return htmlspecialchars($s, ENT_XML1, 'UTF-8'); }

function makeConfig($line, $type) {
    // Format: Name|Target|Default|Mode|Description|Required|Display|Mask|Value
    // Required/Display/Mask optional. Display: always (default) or advanced
    $parts = explode('|', $line);
    $name = isset($parts[0]) ? trim($parts[0]) : '';
    $target = isset($parts[1]) ? trim($parts[1]) : '';
    $default = isset($parts[2]) ? trim($parts[2]) : '';
    $mode = isset($parts[3]) ? trim($parts[3]) : ($type === 'Path' ? 'rw' : ($type === 'Port' ? 'tcp' : ''));
    $desc = isset($parts[4]) ? trim($parts[4]) : '';
    $required = isset($parts[5]) ? trim($parts[5]) : 'false';
    $display = isset($parts[6]) ? trim($parts[6]) : 'always';
    $mask = isset($parts[7]) ? trim($parts[7]) : 'false';
    $value = isset($parts[8]) ? trim($parts[8]) : '';

    $x = '  <Config';
    if ($name) $x .= ' Name="' . h($name) . '"';
    if ($target) $x .= ' Target="' . h($target) . '"';
    if ($default !== '' || $type === 'Port') $x .= ' Default="' . h($default) . '"';
    if ($mode) $x .= ' Mode="' . h($mode) . '"';
    if ($desc) $x .= ' Description="' . h($desc) . '"';
    $x .= ' Type="' . $type . '"';
    $x .= ' Display="' . $display . '"';
    $x .= ' Required="' . $required . '"';
    $x .= ' Mask="' . $mask . '"';
    $x .= '>';
    if ($value) $x .= h($value);
    $x .= "</Config>\n";
    return $x;
}

function launchContainer($name, $repo, $net, $priv, $shell, $cpuset, $extra, $post, $envVars, $ports, $volumes, $devices) {
    $cmd = buildDockerRunCommand($name, $repo, $net, $priv, $shell, $cpuset, $extra, $post, $envVars, $ports, $volumes, $devices);
    $out = shell_exec($cmd . " 2>&1");
    return "Command:\n$cmd\n\nOutput:\n" . trim($out ?? 'No output');
}

function listTemplates($dir) {
    if (!is_dir($dir)) return [];
    $t = [];
    foreach (glob("$dir/*.xml") as $f) {
        $xml = simplexml_load_file($f);
        if ($xml && isset($xml->Name)) $t[] = ['name' => (string)$xml->Name, 'repository' => (string)$xml->Repository, 'file' => basename($f)];
    }
    return $t;
}

function loadTemplateFile($file) {
    if (!file_exists($file)) return null;
    $xml = simplexml_load_file($file);
    if (!$xml) return null;

    // Build config entries
    $envCfg = []; $portCfg = []; $volCfg = []; $devCfg = [];
    foreach ($xml->Config as $cfg) {
        $type = (string)$cfg['Type'];
        $entry = (string)$cfg['Name'] . '|' . (string)$cfg['Target'] . '|' . (string)$cfg['Default'] . '|' . (string)$cfg['Mode'] . '|' . (string)$cfg['Description'] . '|' . (string)$cfg['Required'] . '|' . (string)$cfg['Display'] . '|' . (string)$cfg['Mask'] . '|' . (string)$cfg;
        if ($type === 'Variable') $envCfg[] = $entry;
        elseif ($type === 'Port') $portCfg[] = $entry;
        elseif ($type === 'Device') $devCfg[] = $entry;
        else $volCfg[] = $entry;
    }

    return [
        'name' => (string)$xml->Name,
        'repository' => (string)$xml->Repository,
        'registry' => (string)$xml->Registry,
        'network' => (string)$xml->Network,
        'privileged' => (string)$xml->Privileged,
        'shell' => (string)$xml->Shell,
        'support' => (string)$xml->Support,
        'project' => (string)$xml->Project,
        'readme' => (string)$xml->ReadMe,
        'overview' => (string)$xml->Overview,
        'category' => (string)$xml->Category,
        'webui' => (string)$xml->WebUI,
        'templateurl' => (string)$xml->TemplateURL,
        'icon' => (string)$xml->Icon,
        'extra' => (string)$xml->ExtraParams,
        'post' => (string)$xml->PostArgs,
        'cpuset' => (string)$xml->CPUset,
        'requires' => (string)$xml->Requires,
        'envCfg' => implode("\n", $envCfg),
        'portCfg' => implode("\n", $portCfg),
        'volCfg' => implode("\n", $volCfg),
        'devCfg' => implode("\n", $devCfg),
    ];
}

function verifyTemplateFile($file) {
    $issues = [];
    $errs = libxml_use_internal_errors(true);
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        foreach (libxml_get_errors() as $e) $issues[] = "XML error: " . trim($e->message) . " (line $e->line)";
        libxml_clear_errors();
        libxml_use_internal_errors($errs);
        return $issues;
    }
    libxml_use_internal_errors($errs);
    $bn = basename($file);
    if (empty((string)$xml->Name)) $issues[] = "[$bn] Missing <Name>";
    if (empty((string)$xml->Repository)) $issues[] = "[$bn] Missing <Repository>";
    if (!preg_match('/^[a-zA-Z0-9.\/: _-]+$/', (string)$xml->Repository)) $issues[] = "[$bn] Repository has invalid chars";
    $priv = (string)$xml->Privileged;
    if ($priv !== '' && $priv !== 'true' && $priv !== 'false') $issues[] = "[$bn] Privileged must be true/false, got '$priv'";
    $net = (string)$xml->Network;
    if ($net && !in_array($net, ['bridge','host','none']) && preg_match('/^[a-zA-Z0-9._-]+$/', $net) !== 1) $issues[] = "[$bn] Network '$net' looks invalid";
    foreach ($xml->Config as $cfg) {
        $t = (string)$cfg['Target'];
        $type = (string)$cfg['Type'];
        if (!$t) $issues[] = "[$bn] Config missing Target";
        if (!in_array($type, ['Port','Path','Variable','Device'])) $issues[] = "[$bn] Config has unknown Type '$type'";
        if ($type === 'Port' && !preg_match('/^\d+$/', (string)$cfg['Default'])) $issues[] = "[$bn] Port '".(string)$cfg['Name']."' has non-numeric default";
        if ($type === 'Port' && !preg_match('/^\d+$/', $t)) $issues[] = "[$bn] Port target '$t' is not a number";
        $req = (string)$cfg['Required'];
        if ($req && $req !== 'true' && $req !== 'false') $issues[] = "[$bn] Required must be true/false, got '$req'";
    }
    if (count($issues) === 0) $issues[] = "✓ $bn passed.";
    return $issues;
}

function verifyAllTemplates($dir) {
    if (!is_dir($dir)) return ["Directory does not exist: $dir"];
    $files = glob("$dir/*.xml");
    if (!$files) return ["No .xml files in $dir"];
    $allIssues = []; $checked = 0;
    foreach ($files as $f) { $checked++; foreach (verifyTemplateFile($f) as $i) $allIssues[] = $i; }
    array_unshift($allIssues, "Checked $checked file(s):");
    if ($checked > 0 && count($allIssues) === 1) $allIssues[0] = "✓ All $checked template(s) passed.";
    return $allIssues;
}


function browseDir($path) {
    $path = rtrim($path, '/');
    if (!is_dir($path)) return ['path' => $path, 'parent' => dirname($path), 'items' => []];
    $items = [];
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') continue;
        $full = $path . '/' . $name;
        $items[] = ['name' => $name, 'is_dir' => is_dir($full), 'path' => $full];
    }
    usort($items, function($a, $b) {
        if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });
    return ['path' => $path, 'parent' => dirname($path), 'items' => $items];
}


function getUnraidShares() {
    $shares = [];
    $paths = ['/mnt/user', '/mnt/disks', '/mnt/remotes'];
    foreach ($paths as $base) {
        if (!is_dir($base)) continue;
        $dh = opendir($base);
        if (!$dh) continue;
        while (($name = readdir($dh)) !== false) {
            if ($name[0] === '.') continue;
            $full = "$base/$name";
            if (is_dir($full)) $shares[] = $full;
        }
        closedir($dh);
    }
    sort($shares);
    return $shares;
}

// AJAX handlers

if (isset($_GET['browse'])) {
    header('Content-Type: application/json');
    $p = isset($_GET['path']) ? $_GET['path'] : '/mnt/user';
    echo json_encode(browseDir($p)); exit;
}

if (isset($_GET['shares'])) {
    header('Content-Type: application/json');
    echo json_encode(getUnraidShares()); exit;
}
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(listTemplates(getTemplateDir())); exit;
}
if (isset($_GET['loadfile'])) {
    header('Content-Type: application/json');
    $f = basename($_GET['loadfile']); $p = getTemplateDir() . "/$f";
    echo json_encode(loadTemplateFile($p) ?: []); exit;
}

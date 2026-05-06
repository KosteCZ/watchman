<?php
// check.php - Monitoring engine to be called periodically
require_once 'db.php';

function fetchValue($url, $selector) {
    $urlEscaped = escapeshellarg($url);
    $userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";
    $command = "curl.exe -s -L -A " . escapeshellarg($userAgent) . " " . $urlEscaped;
    
    $html = shell_exec($command);
    
    if (!$html) return null;

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    // More robust CSS to XPath conversion
    $parts = preg_split('/(\s*>\s*|\s+)/', trim($selector), -1, PREG_SPLIT_DELIM_CAPTURE);
    $xpathParts = [];
    foreach ($parts as $part) {
        $trimmedPart = trim($part);
        if ($trimmedPart === '>') {
            $xpathParts[] = '/';
            continue;
        }
        if ($trimmedPart === '' && $part !== '') {
            $xpathParts[] = '//';
            continue;
        }
        if ($trimmedPart === '') continue;
        
        $tag = '*';
        if (preg_match('/^([a-zA-Z0-9]+)/', $trimmedPart, $matches)) {
            $tag = $matches[1];
            $trimmedPart = substr($trimmedPart, strlen($tag));
        }
        
        $conditions = [];
        if (preg_match_all('/\.([a-zA-Z0-9_-]+)/', $trimmedPart, $matches)) {
            foreach ($matches[1] as $class) {
                $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
            }
        }
        if (preg_match_all('/#([a-zA-Z0-9_-]+)/', $trimmedPart, $matches)) {
            foreach ($matches[1] as $id) {
                $conditions[] = "@id='$id'";
            }
        }
        
        $xpathPart = $tag;
        if ($conditions) {
            $xpathPart .= "[" . implode(' and ', $conditions) . "]";
        }
        $xpathParts[] = $xpathPart;
    }
    
    $xpathSelector = '';
    for ($i = 0; $i < count($xpathParts); $i++) {
        $p = $xpathParts[$i];
        if ($p === '/' || $p === '//') {
            $xpathSelector .= $p;
        } else {
            if ($i > 0 && $xpathParts[$i-1] !== '/' && $xpathParts[$i-1] !== '//') {
                $xpathSelector .= '//';
            }
            $xpathSelector .= $p;
        }
    }
    if (strpos($xpathSelector, '/') !== 0) $xpathSelector = '//' . $xpathSelector;

    // echo "DEBUG XPath: $xpathSelector\n"; // Debugging line

    $nodes = $xpath->query($xpathSelector);
    if ($nodes && $nodes->length > 0) {
        return trim($nodes->item(0)->textContent);
    }

    return null;
}

// Get all targets to check
$stmt = $db->query("SELECT * FROM targets");
$targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Starting check of " . count($targets) . " targets...\n";

foreach ($targets as $target) {
    echo "Checking: " . $target['name'] . " (" . $target['url'] . ")... ";
    
    $newValue = fetchValue($target['url'], $target['selector']);
    
    if ($newValue !== null) {
        echo "Found: " . $newValue;
        
        // Update target last value
        $stmtUpdate = $db->prepare("UPDATE targets SET last_value = ?, last_checked = CURRENT_TIMESTAMP WHERE id = ?");
        $stmtUpdate->execute([$newValue, $target['id']]);
        
        // If value changed, record to history
        if ($newValue !== $target['last_value']) {
            $stmtHistory = $db->prepare("INSERT INTO history (target_id, value) VALUES (?, ?)");
            $stmtHistory->execute([$target['id'], $newValue]);
            echo " [CHANGED]";
        }
    } else {
        echo "FAILED to fetch value";
    }
    echo "\n";
}

echo "Done.\n";

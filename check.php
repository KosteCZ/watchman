<?php
// check.php - Multi-Store Search Engine
require_once 'db.php';

function fetchXpath($url, $xpathSelector) {
    $urlEscaped = escapeshellarg($url);
    $userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";
    $command = "curl.exe -s -L -A " . escapeshellarg($userAgent) . " " . $urlEscaped;
    
    $html = shell_exec($command);
    if (!$html) return null;

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $nodes = $xpath->query($xpathSelector);
    if ($nodes && $nodes->length > 0) {
        return $nodes->item(0);
    }
    return null;
}

function cssToXpath($selector) {
    $parts = preg_split('/(\s*>\s*|\s+)/', trim($selector), -1, PREG_SPLIT_DELIM_CAPTURE);
    $xpathParts = [];
    foreach ($parts as $part) {
        $trimmedPart = trim($part);
        if ($trimmedPart === '>') { $xpathParts[] = '/'; continue; }
        if ($trimmedPart === '' && $part !== '') { $xpathParts[] = '//'; continue; }
        if ($trimmedPart === '') continue;
        
        $tag = '*';
        if (preg_match('/^([a-zA-Z0-9]+)/', $trimmedPart, $matches)) {
            $tag = $matches[1];
            $trimmedPart = substr($trimmedPart, strlen($tag));
        }
        $conditions = [];
        if (preg_match_all('/\.([a-zA-Z0-9_-]+)/', $trimmedPart, $matches)) {
            foreach ($matches[1] as $class) { $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' $class ')"; }
        }
        if (preg_match_all('/#([a-zA-Z0-9_-]+)/', $trimmedPart, $matches)) {
            foreach ($matches[1] as $id) { $conditions[] = "@id='$id'"; }
        }
        $xpathPart = $tag;
        if ($conditions) { $xpathPart .= "[" . implode(' and ', $conditions) . "]"; }
        $xpathParts[] = $xpathPart;
    }
    $xpathSelector = '';
    for ($i = 0; $i < count($xpathParts); $i++) {
        $p = $xpathParts[$i];
        if ($p === '/' || $p === '//') { $xpathSelector .= $p; }
        else {
            if ($i > 0 && $xpathParts[$i-1] !== '/' && $xpathParts[$i-1] !== '//') { $xpathSelector .= '//'; }
            $xpathSelector .= $p;
        }
    }
    return (strpos($xpathSelector, '/') !== 0) ? '//' . $xpathSelector : $xpathSelector;
}

// Get all active products and all stores
$products = $db->query("SELECT * FROM products WHERE active = 1")->fetchAll(PDO::FETCH_ASSOC);
$stores = $db->query("SELECT * FROM stores")->fetchAll(PDO::FETCH_ASSOC);

echo "[" . date('Y-m-d H:i:s') . " UTC] Starting discovery for " . count($products) . " products across " . count($stores) . " stores...\n";

$pCount = 0;
foreach ($products as $product) {
    $pCount++;
    echo "[$pCount/" . count($products) . "] Product: " . $product['name'] . "\n";
    
    foreach ($stores as $store) {
        $searchUrl = $store['search_url_template'] . urlencode($product['name']);
        echo "  - " . $store['name'] . ": ";
        
        // 1. Find the match (Link, Title, Price, Availability, Image)
        $priceXpath = cssToXpath($store['price_selector']);
        $linkXpath = cssToXpath($store['link_selector']);
        $titleXpath = cssToXpath($store['title_selector']);
        $availXpath = $store['availability_selector'] ? cssToXpath($store['availability_selector']) : null;
        $imgXpath = $store['image_selector'] ? cssToXpath($store['image_selector']) : null;
        
        // Fetch HTML once for all selectors would be better, but our fetchXpath fetches per call.
        // Let's optimize slightly by fetching the whole document once.
        $userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";
        // On Windows, escapeshellarg strips % from URLs. Using manual quoting instead.
        $command = "curl.exe -s -L --connect-timeout 10 --max-time 30 -A " . escapeshellarg($userAgent) . " \"" . $searchUrl . "\"";
        $html = shell_exec($command);
        
        if (!$html) {
            // Check if it's really empty or if there was an error
            echo "FAILED (Empty response for URL: $searchUrl)\n";
            continue;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        
        $priceNode = $xpath->query($priceXpath)->item(0);
        $linkNode = $xpath->query($linkXpath)->item(0);
        $titleNode = $xpath->query($titleXpath)->item(0);
        $availNode = $availXpath ? $xpath->query($availXpath)->item(0) : null;
        $imgNode = $imgXpath ? $xpath->query($imgXpath)->item(0) : null;
        
        if ($priceNode && $linkNode && $titleNode) {
            $price = trim($priceNode->textContent);
            // Clean price: remove non-numeric except spaces and decimal separators
            $price = preg_replace('/[^\d\s,.]/', '', $price);
            $price = trim($price);
            
            $title = trim($titleNode->textContent);
            $url = $linkNode->getAttribute('href');
            $avail = $availNode ? trim($availNode->textContent) : 'Neznámá';
            
            // Extract Image: prefer data-src for lazy-loaded images, then src
            $img = null;
            if ($imgNode) {
                $img = $imgNode->getAttribute('data-src') ?: $imgNode->getAttribute('src');
            }
            
            // Handle relative URLs for link and image
            $parsedBase = parse_url($store['search_url_template']);
            $base = $parsedBase['scheme'] . '://' . $parsedBase['host'];
            
            if (strpos($url, 'http') !== 0) {
                $url = $base . (strpos($url, '/') === 0 ? '' : '/') . $url;
            }
            // Handle absolute paths (e.g. //domain.com/path)
            if ($img && strpos($img, '//') === 0) {
                $img = 'https:' . $img;
            } 
            // Handle relative paths
            elseif ($img && strpos($img, 'http') !== 0) {
                $img = $base . (strpos($img, '/') === 0 ? '' : '/') . $img;
            }
            
            echo "Found: $price ($title) - $avail\n";
            
            // 1. Update/Insert into matches
            $stmt = $db->prepare("SELECT id FROM product_matches WHERE product_id = ? AND store_id = ?");
            $stmt->execute([$product['id'], $store['id']]);
            $match = $stmt->fetch();
            
            if ($match) {
                $stmt = $db->prepare("UPDATE product_matches SET found_title = ?, found_url = ?, last_price = ?, availability = ?, image_url = ?, last_checked = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$title, $url, $price, $avail, $img, $match['id']]);
            } else {
                $stmt = $db->prepare("INSERT INTO product_matches (product_id, store_id, found_title, found_url, last_price, availability, image_url, last_checked) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$product['id'], $store['id'], $title, $url, $price, $avail, $img]);
            }

            // 2. Log to price_history (daily)
            $today = date('Y-m-d');
            $stmt = $db->prepare("INSERT OR REPLACE INTO price_history (product_id, store_id, price, check_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$product['id'], $store['id'], $price, $today]);
        } else {
            if (!$priceNode) echo "Price not found. ";
            if (!$linkNode) echo "Link not found. ";
            if (!$titleNode) echo "Title not found. ";
            echo "\n";
        }
        // Small delay to be polite to the servers
        usleep(500000); // 0.5s
    }
}

echo "Done.\n";

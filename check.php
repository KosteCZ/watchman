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

echo "Starting discovery for " . count($products) . " products across " . count($stores) . " stores...\n";

foreach ($products as $product) {
    echo "--- Product: " . $product['name'] . " ---\n";
    
    foreach ($stores as $store) {
        $searchUrl = $store['search_url_template'] . urlencode($product['name']);
        echo "Searching in " . $store['name'] . "... ";
        
        // 1. Find the match (Link, Title, Price)
        $priceXpath = cssToXpath($store['price_selector']);
        $linkXpath = cssToXpath($store['link_selector']);
        $titleXpath = cssToXpath($store['title_selector']);
        
        // Fetch HTML once for all selectors would be better, but our fetchXpath fetches per call.
        // Let's optimize slightly by fetching the whole document once.
        $urlEscaped = escapeshellarg($searchUrl);
        $userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";
        $html = shell_exec("curl.exe -s -L -A " . escapeshellarg($userAgent) . " " . $urlEscaped);
        
        if (!$html) {
            echo "FAILED to fetch search results.\n";
            continue;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        
        $priceNode = $xpath->query($priceXpath)->item(0);
        $linkNode = $xpath->query($linkXpath)->item(0);
        $titleNode = $xpath->query($titleXpath)->item(0);
        
        if ($priceNode && $linkNode && $titleNode) {
            $price = trim($priceNode->textContent);
            $title = trim($titleNode->textContent);
            $url = $linkNode->getAttribute('href');
            
            // Handle relative URLs
            if (strpos($url, 'http') !== 0) {
                $base = parse_url($store['search_url_template'], PHP_URL_SCHEME) . '://' . parse_url($store['search_url_template'], PHP_URL_HOST);
                $url = $base . (strpos($url, '/') === 0 ? '' : '/') . $url;
            }
            
            echo "Found: $price ($title)\n";
            
            // Update or Insert match
            $stmt = $db->prepare("SELECT id FROM product_matches WHERE product_id = ? AND store_id = ?");
            $stmt->execute([$product['id'], $store['id']]);
            $match = $stmt->fetch();
            
            if ($match) {
                $stmt = $db->prepare("UPDATE product_matches SET found_title = ?, found_url = ?, last_price = ?, last_checked = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$title, $url, $price, $match['id']]);
            } else {
                $stmt = $db->prepare("INSERT INTO product_matches (product_id, store_id, found_title, found_url, last_price, last_checked) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$product['id'], $store['id'], $title, $url, $price]);
            }
        } else {
            echo "No result found.\n";
        }
    }
}

echo "Done.\n";

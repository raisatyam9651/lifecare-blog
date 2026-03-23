<?php
$blogDir = __DIR__ . '/';
$files = glob($blogDir . '*.php');
$results = [];
foreach ($files as $file) {
        $filename = basename($file);
            if ($filename === 'scan_images.php' || $filename === 'index.php') continue;
                $content = file_get_contents($file);
                    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $imgMatches);
                        preg_match('/"image":\s*"([^"]+)"/i', $content, $schemaMatch);
                            preg_match('/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i', $content, $altMatch);
                                $results[] = [
                                            'file' => $filename,
                                                    'imgs' => $imgMatches[1] ?? [],
                                                            'schema_img' => $schemaMatch[1] ?? '',
                                                                    'alt' => $altMatch[1] ?? ''
                                                                        ];
                                                                        }
                                                                        header('Content-Type: application/json');
                                                                        echo json_encode($results, JSON_PRETTY_PRINT);
                                                                        ?>]
}
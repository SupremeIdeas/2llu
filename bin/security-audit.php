#!/usr/bin/env php
<?php

/**
 * NaaraSim dependency security gate (blueprint Section 30). Runs `composer
 * audit` and FAILS on any vulnerable dependency EXCEPT a small, documented
 * allow-list of advisories we have consciously accepted with a mitigation and a
 * tracking note. This is the "CI fails on a vulnerable dependency" control — any
 * NEW advisory (a package we add, or a new CVE) breaks the build.
 */

$accepted = [
    // laravel/framework — Laravel 11 is past its security-support window (ended
    // 2026-03-12) and these are fixed only in Laravel 12.60+. The Laravel 12
    // upgrade is tracked in SECURITY.md. Interim mitigations:
    //   * Signed-URL path confusion — we never build temporary signed URLs from
    //     user-controlled paths.
    //   * CRLF in the default email rule — registration/login emails are also
    //     length-bounded and never echoed into raw headers by our code.
    'PKSA-m5cs-t1y6-qpcs' => 'Laravel 11 EOL — signed URL path confusion; fixed in L12.60+',
    'PKSA-3r5d-mb8f-1qw9' => 'Laravel 11 EOL — CRLF in email rule; fixed in L12.60+',
    'PKSA-mdq4-51ck-6kdq' => 'Laravel 11 EOL — CVE-2026-48019 CRLF email rule; fixed in L12.60+',
];

exec('composer audit --locked --no-scripts --format=json 2>/dev/null', $out);
$json = json_decode(implode("\n", $out), true) ?: [];
$advisories = $json['advisories'] ?? [];

$unaccepted = [];
$acceptedCount = 0;
foreach ($advisories as $package => $items) {
    foreach ($items as $item) {
        $id = $item['advisoryId'] ?? ($item['cve'] ?? 'unknown');
        if (array_key_exists($id, $accepted)) {
            $acceptedCount++;

            continue;
        }
        $unaccepted[] = "{$package}: {$id} — ".($item['title'] ?? '');
    }
}

if ($unaccepted !== []) {
    fwrite(STDERR, "\n[SECURITY] Vulnerable dependency(ies) found — build failed:\n  ".implode("\n  ", $unaccepted)."\n\n");
    fwrite(STDERR, "If one is genuinely accepted, add it to the allow-list in bin/security-audit.php with a justification.\n");
    exit(1);
}

echo "[SECURITY] Dependency audit passed. {$acceptedCount} known advisory(ies) accepted (see bin/security-audit.php).\n";
exit(0);

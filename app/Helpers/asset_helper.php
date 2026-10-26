<?php
declare(strict_types=1);

/** Content fingerprints prevent stale CSS/JS when different builds share a URL. */
function asset_url(string $path): string
{
    $file = FCPATH . $path;
    $version = is_file($file) ? substr(hash_file('sha256', $file), 0, 12) : 'missing';

    return base_url($path) . '?v=' . $version;
}

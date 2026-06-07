<?php
/**
 * Supabase Storage integration.
 * Uploads files to Supabase and returns their public URL.
 * Falls back to local storage if Supabase is not configured.
 */

define('SUPABASE_URL',          getenv('SUPABASE_URL')          ?: '');
define('SUPABASE_SERVICE_KEY',  getenv('SUPABASE_SERVICE_KEY')  ?: '');
define('SUPABASE_BUCKET',       getenv('SUPABASE_BUCKET')       ?: 'media');

/**
 * Upload a file to Supabase Storage.
 *
 * @param string $localPath   Absolute path to the local file
 * @param string $remotePath  Path inside the bucket, e.g. "gallery/photo1.webp"
 * @param string $mime        MIME type, e.g. "image/webp"
 * @return string|false       Public URL on success, false on failure
 */
function supabase_upload(string $localPath, string $remotePath, string $mime = 'image/webp'): string|false
{
    if (!SUPABASE_URL || !SUPABASE_SERVICE_KEY) return false;

    $url  = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . ltrim($remotePath, '/');
    $data = file_get_contents($localPath);
    if ($data === false) return false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: true',   // overwrite if exists
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        return rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . ltrim($remotePath, '/');
    }

    error_log('[storage] Supabase upload failed (' . $code . '): ' . $resp);
    return false;
}

/**
 * Delete a file from Supabase Storage.
 *
 * @param string $remotePath  Path inside the bucket, e.g. "gallery/photo1.webp"
 */
function supabase_delete(string $remotePath): void
{
    if (!SUPABASE_URL || !SUPABASE_SERVICE_KEY) return;

    $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . ltrim($remotePath, '/');
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Upload image and return the URL to store in DB.
 * If Supabase is configured — uploads there and returns CDN URL.
 * If not — saves locally and returns relative path.
 *
 * @param string $tmpPath     Temp file path ($_FILES[...]['tmp_name'])
 * @param string $localDest   Absolute local destination path
 * @param string $remotePath  Path inside Supabase bucket
 * @param string $mime        MIME type
 * @return string|false       URL/path to store in DB, or false on failure
 */
function upload_image(string $tmpPath, string $localDest, string $remotePath, string $mime = 'image/webp'): string|false
{
    // Always save locally first (needed as source for Supabase upload)
    if (!move_uploaded_file($tmpPath, $localDest)) return false;

    $supabaseUrl = supabase_upload($localDest, $remotePath, $mime);
    if ($supabaseUrl) return $supabaseUrl;

    // Fallback: return relative path
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $projRoot = rtrim(dirname(__DIR__), '/');
    return '/' . ltrim(str_replace($docRoot, '', $localDest), '/');
}

/**
 * Upload a base64-encoded cropped image.
 *
 * @param string $b64         Base64 data URI (data:image/jpeg;base64,...)
 * @param string $localDest   Absolute local destination path (without extension)
 * @param string $remotePath  Path inside Supabase bucket (without extension)
 * @return string|false       URL/path to store in DB, or false on failure
 */
function upload_image_b64(string $b64, string $localDest, string $remotePath): string|false
{
    if (!function_exists('save_cropped_image')) return false;

    $ext = save_cropped_image($b64, $localDest . '.jpg');
    if (!$ext) return false;

    $finalLocal  = $localDest . '.' . $ext;
    $finalRemote = $remotePath . '.' . $ext;
    $mime        = $ext === 'png' ? 'image/png' : 'image/jpeg';

    $supabaseUrl = supabase_upload($finalLocal, $finalRemote, $mime);
    if ($supabaseUrl) return $supabaseUrl;

    $docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $projRoot = rtrim(dirname(__DIR__), '/');
    return '/' . ltrim(str_replace($docRoot, '', $finalLocal), '/');
}

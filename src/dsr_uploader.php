<?php
define('DSR_UPLOADER_VERSION', 2);
define('OLD_REPLAYS_CONFIG_PATH', __DIR__.'/old_replays.json');

echo "DS-RATING.COM replay uploader v".DSR_UPLOADER_VERSION."\n\n";
ensure_latest_version();


echo "\nFinding SC2 replay folders...\n";
$replay_folders = get_replay_folders();
echo "Found ".count($replay_folders)." folder".plural(count($replay_folders)).":\n".implode("\n", $replay_folders)."\n";

echo "\nFinding SC2 replays...\n";
$all_replay_paths = get_all_replay_paths($replay_folders);
echo "Found ".count($all_replay_paths)." replays.\n";

$old_replay_paths = get_old_replay_paths();
$new_replay_paths = get_new_replay_paths($all_replay_paths, $old_replay_paths);
$new_replays_count = count($new_replay_paths);
echo "New replays: $new_replays_count\n";
if ($new_replays_count === 0) {
    press_any_key_to_exit();
}

echo "Uploading new replays...\n";
$current_replay_index = 0;
foreach ($new_replay_paths as $new_replay_path) {
    $current_replay_index++;
    $success = upload_replay($new_replay_path, $current_replay_index, $new_replays_count);
    if ($success) {
        $old_replay_paths[] = $new_replay_path;
    }
}
flush_all_buffered_text();

// This way to store data is not reliable, can not save it after each
// processed replay, or we will risk loosing all info, if for example
// user will Ctrl+C early.
save_old_replay_paths($old_replay_paths);

press_any_key_to_exit();
























function get_replay_folders() {
    $replay_folders = [];

    $sc2_accounts_root_path = get_sc2_accounts_root_path();
    if ($sc2_accounts_root_path === false) {
        echo "Could not find SC2 Accounts folder. Get help in Discord https://discord.gg/KXKw8HqKKK\n";
        press_any_key_to_exit();
    }

    $account_paths = glob($sc2_accounts_root_path.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);
    foreach ($account_paths as $account_path) {
        $profile_paths = glob($account_path.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);
        foreach ($profile_paths as $profile_path) {
            $replay_folder = $profile_path.DIRECTORY_SEPARATOR.'Replays'.DIRECTORY_SEPARATOR.'Multiplayer';
            if (is_dir($replay_folder)) {
                $replay_folders[] = $replay_folder;
            }
        }
    }

    if (count($replay_folders) === 0) {
        echo "Could not find SC2 Replays folder. Get help in Discord https://discord.gg/KXKw8HqKKK\n";
        press_any_key_to_exit();
    }

    return $replay_folders;
}

function get_sc2_accounts_root_path() {
    $user_path = getenv('USERPROFILE');
    if ($user_path) {
        $accounts_path = $user_path.DIRECTORY_SEPARATOR.'Documents'.DIRECTORY_SEPARATOR.'StarCraft II'.DIRECTORY_SEPARATOR.'Accounts';
        if (is_dir($accounts_path)) {
            return $accounts_path;
        }

        $accounts_path = $user_path.DIRECTORY_SEPARATOR.'My Documents'.DIRECTORY_SEPARATOR.'StarCraft II'.DIRECTORY_SEPARATOR.'Accounts';
        if (is_dir($accounts_path)) {
            return $accounts_path;
        }
    }

    return false;
}

function press_any_key_to_exit() {
    echo "\nPress ENTER to exit...";
    fgetc(STDIN);
    die;
}

function ensure_latest_version() {
    echo "Checking for uploader updates...\n";
    $version_info = download_latest_version_info();
    if ($version_info['fail'] ?? false) {
        echo "Failed to connect to ds-rating.com website.\n";
        press_any_key_to_exit();
    }
    else if (($version_info['version']??0) === DSR_UPLOADER_VERSION) {
        echo "You are using latest uploader version, good.\n";
    }
    else if (($version_info['version']??0) > DSR_UPLOADER_VERSION) {
        echo "Your replay uploader version is ".DSR_UPLOADER_VERSION.".\n";
        echo "Download new version ".$version_info['version']." at ".$version_info['download_url']."\n";
        press_any_key_to_exit();
    }
    else if (($version_info['version']??0) < DSR_UPLOADER_VERSION) {
        echo "Something went wrong:\n";
        echo "Your replay uploader version ".DSR_UPLOADER_VERSION." is higher than actual version ".$version_info['version']." available at ".$version_info['download_url']."\n";
        echo "Get help in Discord https://discord.gg/KXKw8HqKKK\n";
        press_any_key_to_exit();
    }
    else {
        echo "Unknown error while getting data from ds-rating.com website.\n";
        press_any_key_to_exit();
    }
}

function download_latest_version_info() {
    $result = get_reply('https://ds-rating.com/dsr/dsr-uploader-version.json'.'?random='.md5(microtime(true)));
    return $result;
}

function get_all_replay_paths($replay_folders) {
    $all_replay_paths = [];
    foreach ($replay_folders as $replay_folder) {
        $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($replay_folder));
        foreach ($paths as $path => $path_info) {
            if ($path_info->isFile() && string_ends_with(strtolower($path), '.sc2replay')) {
                $all_replay_paths[] = $path;
            }
        }
    }
    return $all_replay_paths;
}

function string_ends_with(string $haystack, string $needle) {
    return '' === $needle || ('' !== $haystack && 0 === \substr_compare($haystack, $needle, -\strlen($needle)));
}

function get_old_replay_paths() {
    if (!file_exists(OLD_REPLAYS_CONFIG_PATH)) {
        return [];
    }

    $old_replay_paths = json_decode(file_get_contents(OLD_REPLAYS_CONFIG_PATH), true);
    if (!is_array($old_replay_paths)) {
        return [];
    }

    return $old_replay_paths;
}

function get_new_replay_paths($all_replay_paths, $old_replay_paths) {
    $new_replay_paths = array_diff($all_replay_paths, $old_replay_paths);
    natsort($new_replay_paths);
    return array_values($new_replay_paths);
}

function upload_replay($replay_path, $current_index, $count) {
    echo "\n$current_index/$count $replay_path\n";

    // Using this to make reading console output more pleasant, no jitter.
    flush_buffered_text();
    ob_start();

    if (!file_exists($replay_path)) {
        echo "file does not exist\n";
        return false;
    }

    $replay_hash_info = check_replay_hash($replay_path);
    if ($replay_hash_info['fail'] ?? false) {
        echo "failed to verify hash with ds-rating.com: ".($replay_hash_info['reason']??'no_reason')."\n";
        return false;
    }
    else if (($replay_hash_info['result']??'') === 'hash_exists') {
        echo "was already processed\n";
        return true;
    }
    else if (($replay_hash_info['result']??'') === 'inbox_exists') {
        echo "was already uploaded, processing pending\n";
        return true;
    }
    else if (($replay_hash_info['result']??'') === 'not_found') {
        return upload_replay_to_server($replay_path);
    }
    else {
        echo "unknown upload fail\n";
        return false;
    }
}

function check_replay_hash($replay_path) {
    $hash = md5_file($replay_path);
    $url = 'https://ds-rating.com/dsr/check_replay_hash.php?hash='.urlencode($hash);
    $result = get_reply($url);
    return $result;
}

function upload_replay_to_server($replay_path) {
    $result = post_upload_file('https://ds-rating.com/dsr/upload_replay.php', $replay_path);
    if ($result['fail'] ?? false) {
        echo "failed to upload: ".$result['reason']."\n";
        return false;
    }
    if (($result['result']??'') !== 'success') {
        echo "failed to upload\n";
        return false;
    }

    echo 'upload success, import queue #'.($result['queue_length']??-1)."\n";
    return true;
}

// curl --insecure -F 'replay=@c:/_replays/_temp/qwe.SC2Replay' https://ds-rating.com/dsr/upload_replay.php
function post_upload_file($url, $file_path) {
    $boundary = '===afa0b560b1a19f42a1b9c0eb37d9c6b2===';
    $newline = "\r\n";

    return get_reply($url, stream_context_create(['http' => [
        'header'  => 'Content-Type: multipart/form-data; boundary='.$boundary,
        'method'  => 'POST',
        'content' =>
            '--'.$boundary.$newline.
            'Content-Disposition: form-data; name="replay"; filename="'.basename($file_path).'"'.$newline.
            'Content-Type: application/octet-stream'.$newline.$newline.
            file_get_contents($file_path).$newline.
            '--'.$boundary.'--'.$newline
        ]]));
}

function save_old_replay_paths($old_replay_paths) {
    file_put_contents(OLD_REPLAYS_CONFIG_PATH, json_encode($old_replay_paths, JSON_PRETTY_PRINT));
    echo "\nSaved ".count($old_replay_paths)." processed replays list.\n";
}

function plural($amount, $singular = '', $plural = 's') {
    if ($amount === 1) {
        return $singular;
    }
    return $plural;
}

function get_reply($url, $context = null) {
    try {
        $response_string = file_get_contents($url, false, $context);
    } catch (Exception $e) {
        return [
            'fail' => true,
            'reason' => 'exception while trying to get reply from '.$url,
            ];
    }

    if ($response_string === false) {
        return [
            'fail' => true,
            'reason' => 'error while trying to get reply from '.$url,
            ];
    }

    $result = json_decode($response_string, true);
    if ($result === null) {
        return [
            'fail' => true,
            'reason' => 'cannot decode reply from '.$url,
            ];
    }

    return $result;
}

function flush_buffered_text() {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}

function flush_all_buffered_text() {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}

<?php $start = microtime(true);
define('DSR_UPLOADER_VERSION', 9);
define('OLD_REPLAYS_CONFIG_PATH', __DIR__.'/old_replays_v2.json');
define('DSR_DOMAIN', 'https://ds-rating.com');

define('DSR_PRINT_LEVEL_FULL', 0);
define('DSR_PRINT_LEVEL_SERVICE', 5);
define('DSR_PRINT_LEVEL_SILENT', 9);

define('DSR_ERROR_CODE_OK', 0);
define('DSR_ERROR_CODE_VERSION', 10);
define('DSR_ERROR_CODE_ACCOUNT_PATH', 20);
define('DSR_ERROR_CODE_REPLAY_PATH', 30);
define('DSR_ERROR_CODE_CONFIG_PERMISSIONS', 40);



process_command_line_arguments();

dsr_print("DS-RATING.COM replay uploader v".DSR_UPLOADER_VERSION."\n\n");
ensure_latest_version();


dsr_print("\nFinding SC2 replay folders...\n");
$replay_folders = get_replay_folders();
dsr_print("Found ".count($replay_folders)." folder".plural(count($replay_folders)).":\n".implode("\n", $replay_folders)."\n");

dsr_print("\nFinding SC2 replays...\n");
$all_replay_paths = get_all_replay_paths($replay_folders);
dsr_print("Found ".count($all_replay_paths)." replays.\n");

$old_replay_paths = get_old_replay_paths();
$new_replay_paths = get_new_replay_paths($all_replay_paths, $old_replay_paths);
$new_replays_count = count($new_replay_paths);
dsr_print("New replays: $new_replays_count\n");
if ($new_replays_count === 0) {
    exit_with_error_code(DSR_ERROR_CODE_OK);
}

dsr_print("Uploading new replays...\n");
$current_replay_index = 0;
foreach ($new_replay_paths as $new_replay_path) { $current_replay_index++;
    dsr_print("\n$current_replay_index/$new_replays_count $new_replay_path\n", DSR_PRINT_LEVEL_SERVICE);
    $success = upload_replay($new_replay_path);
    if ($success) {
        $old_replay_paths[] = $new_replay_path;
    }

    // Save processed replays history
    // every 100 replays uploaded at a time.
    if ($current_replay_index%100 === 0) {
        save_old_replay_paths($old_replay_paths);
    }

    // Service is launching uploader every 10-30 minutes,
    // committing changes and quitting at 9 minutes.
    if ($GLOBALS['is_service'] ?? false) {
        $duration_running_seconds = microtime(true) - $start;
        if ($duration_running_seconds > 9*60) {
            break;
        }
    }
}
flush_all_buffered_text();

save_old_replay_paths($old_replay_paths);
dsr_print("\nSaved ".count($old_replay_paths)." processed replays list.\n");

exit_with_error_code(DSR_ERROR_CODE_OK);
























function process_command_line_arguments() {
    $GLOBALS['print_level'] = DSR_PRINT_LEVEL_FULL;
    $GLOBALS['is_service'] = false;

    foreach (($GLOBALS['argv']??[]) as $arg) {
        if ($arg === 'print_level_service') {
            $GLOBALS['print_level'] = DSR_PRINT_LEVEL_SERVICE;
            $GLOBALS['is_service'] = true; // supporting legacy v7 service launcher
        }
        else if ($arg === 'print_level_silent') {
            $GLOBALS['print_level'] = DSR_PRINT_LEVEL_SILENT;
        }
        else if ($arg === 'get_version') {
            echo DSR_UPLOADER_VERSION;
            exit_with_error_code(DSR_ERROR_CODE_OK);
        }
        else if ($arg === 'service') {
            $GLOBALS['is_service'] = true;
        }
    }
}

function get_replay_folders() {
    $replay_folders = [];

    $sc2_accounts_root_path = get_sc2_accounts_root_path();
    if ($sc2_accounts_root_path === false) {
        dsr_print(
            "Could not find SC2 Accounts folder.\n".
            "Move Uploader folder into your \"Documents/StarCraft II\" folder to help Uploader find it.\n".
            "Get more help in Discord https://discord.gg/KXKw8HqKKK\n",
            DSR_PRINT_LEVEL_SERVICE);
        exit_with_error_code(DSR_ERROR_CODE_ACCOUNT_PATH);
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
        dsr_print("Could not find SC2 Replays folder. Get help in Discord https://discord.gg/KXKw8HqKKK\n", DSR_PRINT_LEVEL_SERVICE);
        exit_with_error_code(DSR_ERROR_CODE_REPLAY_PATH);
    }

    return $replay_folders;
}

function get_sc2_accounts_root_path() {
    // Hardcode if everything else failed.
    // return 'C:\Users\your username\Documents\StarCraft II\Accounts';


    // If you put Uploader somewhere inside your documents/sc2 folder.
    $sc2_folder_position = strpos(__DIR__, 'StarCraft II');
    if ($sc2_folder_position === false) {
        $sc2_folder_position = stripos(__DIR__, 'StarCraft II');
    }
    if ($sc2_folder_position !== false) {
        $accounts_path = substr(__DIR__, 0, $sc2_folder_position).'StarCraft II'.DIRECTORY_SEPARATOR.'Accounts';
        if (is_dir($accounts_path)) {
            return $accounts_path;
        }
    }


    $current_working_directory = getcwd();
    $current_user_path = getenv('USERPROFILE');
    $current_user_path_macos = getenv('HOME');
    $possible_user_paths = [
        $current_working_directory, // actual user path is set as cwd for service.
        $current_user_path, // works for manual launch as current user.
        $current_user_path_macos, // works for manual launch as current user in MacOS.
        ];

    foreach ($possible_user_paths as $possible_user_path) {
        if ($possible_user_path) {
            // Win7+.
            $accounts_path =
                $possible_user_path.
                DIRECTORY_SEPARATOR.'Documents'. // if you have default english documents folder name.
                DIRECTORY_SEPARATOR.'StarCraft II'.
                DIRECTORY_SEPARATOR.'Accounts';
            if (is_dir($accounts_path)) {
                return $accounts_path;
            }

            // Win XP.
            $accounts_path =
                $possible_user_path.
                DIRECTORY_SEPARATOR.'My Documents'.
                DIRECTORY_SEPARATOR.'StarCraft II'.
                DIRECTORY_SEPARATOR.'Accounts';
            if (is_dir($accounts_path)) {
                return $accounts_path;
            }

            // MacOS.
            // Works on MacOS 11.3.
            $accounts_path =
                $possible_user_path.
                DIRECTORY_SEPARATOR.'Library'.
                DIRECTORY_SEPARATOR.'Application Support'.
                DIRECTORY_SEPARATOR.'Blizzard'.
                DIRECTORY_SEPARATOR.'StarCraft II'.
                DIRECTORY_SEPARATOR.'Accounts';
            if (is_dir($accounts_path)) {
                return $accounts_path;
            }
        }
    }


    // You may pass sc2 accounts root path via command line argument.
    // Make sure to quote it to protect from spaces in path.
    //
    // Example call 1: >upload.bat "C:\Users\your username\Documents\StarCraft II\Accounts"
    // Example call 2: >bin/php/php.exe src/uploader.php "C:\Users\your username\Documents\StarCraft II\Accounts"
    // Example call 3: >php uploader.php "C:\Users\your username\Documents\StarCraft II\Accounts"
    $first_command_line_argument = $GLOBALS['argv'][1] ?? false;
    if ($first_command_line_argument !== false && @is_dir($first_command_line_argument)) {
        return $first_command_line_argument;
    }


    return false;
}

function exit_with_error_code($error_code = DSR_ERROR_CODE_OK) {
    exit($error_code);
}

function ensure_latest_version() {
    dsr_print("Checking for uploader updates...\n");

    $version_info = download_latest_version_info();
    if ($version_info['fail'] ?? false) {
        dsr_print("Failed to connect to ds-rating.com website:\n", DSR_PRINT_LEVEL_SERVICE);
        dsr_print(($version_info['reason']??'noreason')."\n", DSR_PRINT_LEVEL_SERVICE);
        exit_with_error_code(DSR_ERROR_CODE_VERSION);
    }

    if (($version_info['version']??0) !== DSR_UPLOADER_VERSION) {
        dsr_print("Your replay uploader version is outdated.\n", DSR_PRINT_LEVEL_SERVICE);
        dsr_print("Run updater or download new version at: ".DSR_DOMAIN."/download/\n", DSR_PRINT_LEVEL_SERVICE);
        exit_with_error_code(DSR_ERROR_CODE_VERSION);
    }

    dsr_print("You are using latest uploader version, good.\n");
}

function download_latest_version_info() {
    $result = get_reply(DSR_DOMAIN.'/dsr/dsr-uploader-version.json'.'?random='.md5(microtime(true)));
    return $result;
}

function get_all_replay_paths($replay_folders) {
    $all_replay_paths = [];
    foreach ($replay_folders as $replay_folder) {
        $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($replay_folder));
        foreach ($paths as $path => $path_info) {
            $is_file = $path_info->isFile();
            if (!$is_file) {
                continue;
            }

            $extension_lowercase = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($extension_lowercase !== 'sc2replay') {
                continue;
            }

            $filename = pathinfo($path, PATHINFO_FILENAME);
            $filename_lowercase = strtolower($filename);
            if (!string_starts_with($filename_lowercase, 'direct strike') &&
                !string_starts_with($filename, '다이렉트 스트라이크')) { // korean Direct Strike map name
                continue;
            }

            $all_replay_paths[] = $path;
        }
    }
    return $all_replay_paths;
}

function string_starts_with(string $haystack, string $needle) {
    return 0 === \strncmp($haystack, $needle, \strlen($needle));
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

function get_new_replay_paths(&$all_replay_paths, &$old_replay_paths) {
    $new_replay_paths = array_diff($all_replay_paths, $old_replay_paths);
    natsort($new_replay_paths);
    return array_values($new_replay_paths);
}

function upload_replay($replay_path) {
    // Using this to make reading console output more pleasant, no jitter.
    flush_buffered_text();
    ob_start();

    if (!file_exists($replay_path)) {
        dsr_print("file does not exist\n", DSR_PRINT_LEVEL_SERVICE);
        return false;
    }

    $replay_hash_info = check_replay_hash($replay_path);
    if ($replay_hash_info['fail'] ?? false) {
        dsr_print("failed to verify hash with ds-rating.com: ".($replay_hash_info['reason']??'no_reason')."\n", DSR_PRINT_LEVEL_SERVICE);
        return false;
    }
    else if (($replay_hash_info['result']??'') === 'hash_exists') {
        dsr_print("was already processed\n", DSR_PRINT_LEVEL_SERVICE);
        return true;
    }
    else if (($replay_hash_info['result']??'') === 'inbox_exists') {
        dsr_print("was already uploaded, processing pending\n", DSR_PRINT_LEVEL_SERVICE);
        return true;
    }
    else if (($replay_hash_info['result']??'') === 'not_found') {
        return upload_replay_to_server($replay_path);
    }
    else {
        dsr_print("unknown upload fail\n", DSR_PRINT_LEVEL_SERVICE);
        return false;
    }
}

function check_replay_hash($replay_path) {
    $hash = md5_file($replay_path);
    $url = DSR_DOMAIN.'/dsr/check_replay_hash.php?hash='.urlencode($hash);
    $result = get_reply($url);
    return $result;
}

function upload_replay_to_server($replay_path) {
    $result = post_upload_file(DSR_DOMAIN.'/dsr/upload_replay.php', $replay_path);
    if ($result['fail'] ?? false) {
        dsr_print("failed to upload: ".$result['reason']."\n", DSR_PRINT_LEVEL_SERVICE);
        return false;
    }
    if (($result['result']??'') !== 'success') {
        dsr_print("failed to upload\n", DSR_PRINT_LEVEL_SERVICE);
        return false;
    }

    dsr_print('upload success, import queue #'.($result['queue_length']??-1)."\n", DSR_PRINT_LEVEL_SERVICE);
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

function save_old_replay_paths(&$old_replay_paths) {
    $tmp = OLD_REPLAYS_CONFIG_PATH.'.tmp';
    file_put_contents($tmp, json_encode($old_replay_paths, JSON_PRETTY_PRINT));
    $success = rename($tmp, OLD_REPLAYS_CONFIG_PATH);
    if (!$success) {
        dsr_print("Error: could not save config.\n", DSR_PRINT_LEVEL_SERVICE);
        exit_with_error_code(DSR_ERROR_CODE_CONFIG_PERMISSIONS);
    }
}

function plural($amount, $singular = '', $plural = 's') {
    if ($amount === 1) {
        return $singular;
    }
    return $plural;
}

function get_reply($url, $context = null) {
    try {
        $response_string = @file_get_contents($url, false, $context);
    } catch (Exception $e) {
        return [
            'fail' => true,
            'reason' => 'exception while trying to get reply from '.$url,
            ];
    }

    if ($response_string === false) {
        return [
            'fail' => true,
            'reason' => 'error while trying to get reply from '.$url."\n".(error_get_last()['message']??'nomessage'),
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

function dsr_print($message, $level = DSR_PRINT_LEVEL_FULL) {
    $print_level = $GLOBALS['print_level'] ?? DSR_PRINT_LEVEL_FULL;
    if ($level >= $print_level) {
        echo $message;
    }
}

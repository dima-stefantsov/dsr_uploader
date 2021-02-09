<?php
define('DSR_DOMAIN', 'https://ds-rating.com');
define('DSR_UPLOADER_BIN', __DIR__.'/uploader.php');
define('DSR_UPLOADER_BIN_TEMP', __DIR__.'/uploader_temp.php');



delete_uploader_bin_temp();

$local_uploader_version = get_local_uploader_version(DSR_UPLOADER_BIN);
$actual_uploader_version = get_actual_uploader_version();
if ($actual_uploader_version === false) {
    // echo already done inside get_actual_uploader_version() function.
    die;
}
if ($local_uploader_version === $actual_uploader_version) {
    if (!in_array('print_level_service', $argv??[])) {
        echo "You already have latest ds-rating.com_uploader v$local_uploader_version.\n";
    }
    die;
}


$new_uploader_content = get_content('https://raw.githubusercontent.com/dima-stefantsov/dsr_uploader/master/src/uploader.php'.'?random='.md5(microtime(true)));
if ($new_uploader_content['fail'] ?? false) {
    echo "UPDATER: error while downloading new uploader bin: ".($new_uploader_content['reason']??'noreason')."\n";
    die;
}

delete_uploader_bin_temp();
$successfully_written_temp = file_put_contents(DSR_UPLOADER_BIN_TEMP, $new_uploader_content);
if ($successfully_written_temp === false) {
    echo "UPDATER: failed to write temp uploader file ".DSR_UPLOADER_BIN_TEMP."\n";
    delete_uploader_bin_temp();
    die;
}

$temp_uploader_version = get_local_uploader_version(DSR_UPLOADER_BIN_TEMP);
if ($temp_uploader_version === 0) {
    echo "UPDATER: failed to get new temp uploader version\n";
    delete_uploader_bin_temp();
    die;
}

if ($temp_uploader_version !== $actual_uploader_version) {
    echo "UPDATER: new temp uploader version v$temp_uploader_version is wrong, should be v$actual_uploader_version\n";
    delete_uploader_bin_temp();
    die;
}

$successfully_replaced = rename_safe(DSR_UPLOADER_BIN_TEMP, DSR_UPLOADER_BIN);
if ($successfully_replaced === false) {
    echo "UPDATER: failed to replace old uploader with a new one. Maybe it's currently in use.\n";
    delete_uploader_bin_temp();
    die;
}

delete_uploader_bin_temp();
echo "UPDATER: successfully updated uploader v$local_uploader_version to v$temp_uploader_version\n";
die;



















function get_local_uploader_version($uploader_bin) {
    if (!file_exists($uploader_bin)) {
        return 0;
    }

    $php_bin = dirname(__DIR__).'/bin/php/php.exe';
    $arguments = 'get_version';
    $cmd = "\"$php_bin\" \"$uploader_bin\" $arguments";

    exec($cmd, $output_lines, $result_code);
    if ($result_code !== 0) {
        return 0;
    }

    $version = intval($output_lines[0]??0);
    return $version;
}

function get_actual_uploader_version() {
    $version_info = get_reply(DSR_DOMAIN.'/dsr/dsr-uploader-version.json'.'?random='.md5(microtime(true)));
    if ($version_info['fail'] ?? false) {
        echo "UPDATER: error while getting actual uploader version: ".($version_info['reason']??'noreason')."\n";
    }

    if (($version_info['version']??false) === false) {
        echo "UPDATER: failed to get actual uploader version\n";
    }

    return $version_info['version'] ?? false;
}

function get_reply($url) {
    $response_string = get_content($url);
    if ($response_string['fail'] ?? false) {
        return $response_string;
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

function get_content($url) {
    try {
        $response_string = @file_get_contents($url);
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

    return $response_string;
}

function delete_uploader_bin_temp() {
    if (file_exists(DSR_UPLOADER_BIN_TEMP)) {
        @unlink(DSR_UPLOADER_BIN_TEMP);
    }
}

function rename_safe($from, $to) {
    if (!file_exists($from)) {
        return false;
    }

    if (file_exists($to)) {
        @unlink($to);
    }

    if (!@rename($from, $to)) {
        if (@copy($from, $to)) {
            @unlink($from);
            return true;
        }
        return false;
    }
    return true;
}

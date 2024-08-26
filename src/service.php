<?php
$php_bin = dirname(__DIR__).'/bin/php/php.exe';
$uploader_bin = __DIR__.'/uploader.php';
$uploader_arguments = 'print_level_service service';
$updater_bin = __DIR__.'/update.php';
$updater_arguments = 'print_level_service';

define('DSR_ERROR_CODE_VERSION', 10);
define('CMD_UPLOADER', "\"$php_bin\" \"$uploader_bin\" $uploader_arguments");
define('CMD_UPDATER', "\"$php_bin\" \"$updater_bin\" $updater_arguments");

run_command(CMD_UPDATER); // check for updates on each system startup, or once a ~day.
for ($i = 0; $i < 100; $i++) {
    $result_code = run_command(CMD_UPLOADER);
    if ($result_code === DSR_ERROR_CODE_VERSION) {
        run_command(CMD_UPDATER);
    }
    sleep(rand(600,1800)); // check for new DS replays every 10-30 minutes.
}

function run_command($command) {
    exec($command, $output_lines, $result_code);
    foreach ($output_lines as $output_line) {
        echo $output_line."\n";
    }

    if ($result_code !== 0) {
        throw new Exception("Error running command: $result_code");
    }

    return $result_code;
}
<?php
$php_bin = dirname(__DIR__).'/bin/php/php.exe';
$uploader_bin = __DIR__.'/uploader.php';
$uploader_arguments = 'print_level_service';
$updater_bin = __DIR__.'/update.php';
$updater_arguments = 'print_level_service';

define('DSR_ERROR_CODE_VERSION', 10);
define('CMD_UPLOADER', "\"$php_bin\" \"$uploader_bin\" $uploader_arguments");
define('CMD_UPDATER', "\"$php_bin\" \"$updater_bin\" $updater_arguments");



run_updater(); // check for updates on each system startup, or once a ~day.
for ($i = 0; $i < 100; $i++) {
    run_uploader();
    sleep(rand(600,1800)); // check for new DS replays once every 10-30 minutes.
}











function run_uploader() {
    exec(CMD_UPLOADER, $output_lines, $result_code);
    foreach ($output_lines as $output_line) {
        echo $output_line."\n";
    }

    if ($result_code === DSR_ERROR_CODE_VERSION) {
        run_updater();
    }
}

function run_updater() {
    exec(CMD_UPDATER, $output_lines, $result_code);
    foreach ($output_lines as $output_line) {
        echo $output_line."\n";
    }
}

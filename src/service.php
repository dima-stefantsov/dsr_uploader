<?php
// require_once dirname(__DIR__, 2).'/wp/vendor/autoload.php';
// require_once dirname(__DIR__, 2).'/wp/src/ds/helpers/kint.php';


$php_bin = dirname(__DIR__).'/bin/php/php.exe';
$uploader = dirname(__DIR__).'/src/uploader.php';
$arguments = 'print_level_service';

$cmd = "\"$php_bin\" \"$uploader\" $arguments";

for ($i = 0; $i < 100; $i++) {
    run_uploader($cmd);
}
echo "100 done\n";


// sleep(600);

function run_uploader($cmd) {
    exec($cmd, $output_lines, $result_code);
    if ($result_code !== 0) {
        echo "\nUploader finished with error code: $result_code\n";
        die;
    }

    foreach ($output_lines as $output_line) {
        echo $output_line."\n";
    }
}


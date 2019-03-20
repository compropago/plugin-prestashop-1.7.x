<?php

/**
 * Build module files
 * 
 * 2019 ComproPago
 * @author José Beltrán Solís <j.beltran@live.com.mx>
 * 
 */

echo "\n";
echo "\033[1;32mGenerating ComproPago module for Prestashop 1.7.x\033[0m";
echo "\n";


$filename = "compropago.zip";
$zip = new ZipArchive();
$ignore = [
    '.', '..',                          # root directories
    '.git', '.gitignore',               # git files
    'build.php',                        # Build file
    'composer.json', 'composer.lock',   # Composer files
    '.DS_Store'                         # OS System files
];


if (file_exists($filename)) {
    echo "\033[1;31mDeleting old file\033[0m\n";
    unlink($filename);
}


if ($zip->open($filename, ZipArchive::CREATE) === TRUE)
{
    foreach (list_files() as $file) {
        $file = str_replace(__DIR__.'/', '', $file);

        if (in_array(current(explode('/', $file)), $ignore)) continue;
        else {
            echo  "\e[1mAdding:\e[0m '$file'\n";
            $zip->addFile( $file, "compropago/$file");
        }
    }
 
    $zip->close();
} else {
    exit("Cannot open <$filename>\n");
}

echo "\e[1mFinish...\e[0m" . PHP_EOL;


/**
 * Functions
 */

function list_files($directory=__DIR__) {
    $list = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        $path = $file->getRealPath();
        if (!is_dir($path)) $list[] = $path;
    }

    return $list;
}

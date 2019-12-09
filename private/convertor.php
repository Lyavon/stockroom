<?php

require_once '/usr/local/lib/php/database.php';
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'images.php';

function conf_db()
{
    static $config = NULL;
    if (!is_array($config))
        $config = parse_json_config('.database.json');

    return $config;
}

function conf()
{
    return ['http_root_path' => '/stockroom/',
            'absolute_root_path' => '/storage/www/stockroom/'];
}

function process_dir($path, $parent_id) {
    $l = scandir($path);

    foreach ($l as $f) {
        if ($f[0] == '.')
            continue;
        if ($f == 'temp')
            continue;

        if(is_dir($path.$f)) {
            $loc_id = db()->insert('location',
                                   ['parent_id' => $parent_id,
                                    'name' => $f,
                                    'fullness' => '100',
                                    'user_id' => 1]);
            process_dir($path.$f.'/', $loc_id);
        }

        if(is_file($path.$f)) {
            $obj_id = db()->insert('objects', ['name' => $f,
                                               'number' => 1,
                                               'catalog_id' => 0,
                                               'location_id' => $parent_id,
                                               'user_id' => 1]);
            $hash = image_upload_local($path.$f, 'objects', $obj_id);
            $photo = image_by_hash($hash);
            $photo->resize('mini', ['w' => 1000]);
            $photo->resize('list', ['w' => 300]);

        }

    }
}

function main()
{
    return 0;
    set_time_limit(0);

    db()->query('delete from catalog');
    db()->query('ALTER TABLE catalog AUTO_INCREMENT = 1');

    db()->query('delete from images');
    db()->query('ALTER TABLE images AUTO_INCREMENT = 1');

    db()->query('delete from location');
    db()->query('ALTER TABLE location AUTO_INCREMENT = 1');

    db()->query('delete from objects');
    db()->query('ALTER TABLE objects AUTO_INCREMENT = 1');

    run_cmd('rm -f /storage/www/stockroom/i/obj/*');

    $gd_path = '/storage/google-disk/';
    process_dir($gd_path, 0);
}


main();
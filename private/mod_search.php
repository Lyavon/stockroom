<?php

require_once "private/object.php";
require_once "private/images.php";


class Mod_search extends Module {

    function content($args = [])
    {
        $tpl = new strontium_tpl("private/tpl/mod_search.html", conf()['global_marks'], false);
        $text = trim($args['text']);
        $catalog_id = (int)$args['cat_id'] ? (int)$args['cat_id'] : 0;
        $object_attrs_text = trim($args['object_attrs']);

        $obj_attrs = parse_attrs($object_attrs_text);
        $object_attrs_text = attrs_to_text($obj_attrs);

        $tpl->assign(NULL, ['form_url' => mk_url(),
                            'mod' => $this->name,
                            'cat_id' => $catalog_id,
                            'object_attrs' => $object_attrs_text,
                            'text' => $text]);

        $existed_attrs = get_existed_attrs();
        if (count($existed_attrs))
            foreach ($existed_attrs as $attr)
                $tpl->assign('existed_attr', ['attr' => $attr]);

        if ($text) {
            /* search object by #ID */
            preg_match('/^#(\d+)/', $text, $m);
            if (isset($m[1])) {
                $object = object_by_id($m[1]);
                if (!$object) {
                    $tpl->assign('no_result');
                    return $tpl->result();
                }

                header('location: '.mk_url(['mod' => 'object', 'id' => $object['id']]));
                return;
            }

            /* search location by ##ID */
            preg_match('/^##(\d+)/', $text, $m);
            if (isset($m[1])) {
                $location = location_by_id($m[1]);
                if (!$location) {
                    $tpl->assign('no_result');
                    return $tpl->result();
                }

                header('location: '.mk_url(['mod' => 'location', 'id' => $location['id']]));
                return;
            }
        }

        if ($text[0] == '' && $catalog_id == 0 && $object_attrs_text == "")
            return $tpl->result();

        if (!$obj_attrs) {
            $cat_result = $this->find_by_catalog($text, $catalog_id);
            $loc_result = $this->find_by_location($text);
        }

        $obj_result = $this->find_by_object($text, $catalog_id, $obj_attrs);
        /*set_time_limit(0);
        dump($obj_result);
        foreach ($obj_result as $object) {
            preg_match('/-\s*([\d\.]+)/i', $object['name'], $m);
            if (!isset($m[1]))
                continue;

            db()->update('objects', $object['id'], ['attrs' => sprintf('Длина: %s\n', $m[1])]);
        }
        exit;*/


        if (!count($cat_result) && !count($loc_result) && !count($obj_result))
            $tpl->assign('no_result');

        if ($cat_result) {
            $tpl->assign('result_catalog');
            foreach ($cat_result as $category) {
                $tpl->assign('result_catalog_row',
                             ['id' => $category['id'],
                              'link' => mk_url(['mod' => 'catalog',
                              'id' => $category['id']])]);

                foreach ($category['path'] as $node)
                    $tpl->assign('result_catalog_path', ['name' => $node['name']]);
            }
        }


        if ($loc_result) {
            $tpl->assign('result_location');
            foreach ($loc_result as $location) {
                $tpl->assign('result_location_row',
                             ['id' => $location['id'],
                              'link' => mk_url(['mod' => 'location',
                              'id' => $location['id']])]);

                foreach ($location['path'] as $node)
                    $tpl->assign('result_location_path', ['name' => $node['name']]);
            }
        }

        if ($obj_result) {
            $tpl->assign('result_objects');
            foreach ($obj_result as $object) {
                $img_url = '';
                $photos = images_by_obj_id('objects', $object['id']);
                if (count($photos)) {
                    $photo = $photos[0];
                    $img_url = $photo->url('list');
                }

                $tpl->assign('result_objects_row',
                             ['id' => $object['id'],
                              'link' => mk_url(['mod' => 'object',
                                                'id' => $object['id']]),
                              'name' => $object['name'],
                              'attrs' => $object['attrs'],
                              'description' => $object['description'],
                              'img' => $img_url]);
                if ($object['number'] > 1)
                    $tpl->assign('object_count', ['count' => $object['number']]);

                $location = location_by_id($object['location_id']);
                foreach ($location['path'] as $item)
                    $tpl->assign('location_path', ['name' => $item['name'],
                                                   'link' => $item['url']]);

                $catalog = catalog_by_id($object['catalog_id']);
                foreach ($catalog['path'] as $item)
                    $tpl->assign('catalog_path', ['name' => $item['name'],
                                                  'id' => $item['id'],
                                                  'link' => $item['url']]);
            }
        }

        return $tpl->result();
    }

    function find_by_catalog($text, $cat_id)
    {
        if (!$text)
            return NULL;

        $rows = db()->query_list('select id from catalog where '.
                                 'name LIKE "%%%s%%" or '.
                                 'description LIKE "%%%s%%" ',
                                 $text, $text);

        if (!is_array($rows) || !count($rows))
            return NULL;

        $list = [];
        foreach ($rows as $row) {
            if (catalog_is_child($row['id'], $cat_id))
                $list[] = catalog_by_id($row['id']);
        }
        return count($list) ? $list : NULL;
    }

    function find_by_location($text)
    {
        if (!$text)
            return NULL;

        $rows = db()->query_list('select id from location where '.
                                 'name LIKE "%%%s%%" or '.
                                 'description LIKE "%%%s%%" ',
                                 $text, $text);
        if (!is_array($rows) || !count($rows))
            return NULL;

        $list = [];
        foreach ($rows as $row)
            $list[] = location_by_id($row['id']);
        return count($list) ? $list : NULL;
    }

    function find_by_object($text, $cat_id, $search_attrs)
    {
        $rows = db()->query_list('select id, catalog_id from objects where '.
                                 'name LIKE "%%%s%%" or '.
                                 'description LIKE "%%%s%%" ',
                                 $text, $text);
        if (!is_array($rows) || !count($rows))
            return NULL;

        $list = [];
        foreach ($rows as $row) {
            if (!catalog_is_child($row['catalog_id'], $cat_id))
                continue;

            $object = object_by_id($row['id']);
            if (!object_attrs_match(parse_attrs($object['attrs']), $search_attrs))
                continue;

            $list[] = $object;
        }
        return count($list) ? $list : NULL;
    }

}

modules()->register('search', new Mod_search);

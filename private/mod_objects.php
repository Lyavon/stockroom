<?php

require_once "private/object.php";
require_once "private/images.php";


class Mod_object extends Module {

    function content($args = [])
    {
        $tpl = new strontium_tpl("private/tpl/mod_objects.html", conf()['global_marks'], false);

        if (!isset($args['id']) || $args['id'] <= 0) {
            $tpl->assign(NULL, ['number' => 1,
                                'form_url' => mk_url(['mod' => $this->name], 'query'),
                                'catalog_id' => $args['catalog_id'] ? $args['catalog_id'] : 0,
                                'location_id' => $args['location_id'] ? $args['location_id'] : 0]);
            $tpl->assign('object_add');
            return $tpl->result();
        }

        $object_id = $args['id'];
        $object = object_by_id($object_id);
        $tpl->assign(NULL, ['number' => $object['number'],
                            'form_url' => mk_url(['mod' => $this->name], 'query'),
                            'catalog_id' => $object['catalog_id'],
                            'location_id' => $object['location_id'],
                            'object_id' => $object_id,
                            'object_name' => $object['name'],
                            'object_description' => $object['description']]);

        $tpl->assign('object_edit_id', ['id' => $object_id,
                                        'name' => $object['name']]);
        $tpl->assign('edit_button', ['object_name' => $object['name']]);
        $tpl->assign('remove_button',
                     ['link' => mk_url(['mod' => $this->name,
                                        'method' => 'remove_object',
                                        'id' => $object_id],
                                       'query'),
                      'object_name' => $object['name']]);

        $photos = images_by_obj_id('objects', $object_id);
        foreach ($photos as $photo) {
            $link_remove = mk_url(['method' => 'remove_photo',
                                   'photo_hash' => $photo->hash(),
                                   'mod' =>  $this->name,
                                   'obj_id' => $object_id], 'query');

            $tpl->assign('photo', ['img' => $photo->url('mini'),
                                   'img_orig' => $photo->url(),
                                   'link_remove' => $link_remove]);
        }

        $location = location_by_id($object['location_id']);
        foreach ($location['path'] as $item)
            $tpl->assign('location_path', ['name' => $item['name'],
                                           'link' => $item['url']]);

        $catalog = catalog_by_id($object['catalog_id']);
        foreach ($catalog['path'] as $item)
            $tpl->assign('catalog_path', ['name' => $item['name'],
                                           'link' => $item['url']]);
        return $tpl->result();
    }

    function query($args)
    {
        switch($args['method']) {
        case 'object_add':
            $object_id = object_add($args['catalog_id'],
                                    $args['location_id'],
                                    $args['object_name'],
                                    $args['object_description'],
                                    $args['objects_number']);

            if ($object_id <= 0) {
                message_box_err('Can`t add new object');
                return mk_url(['mod' => 'location', 'id' => $args['location_id']]);
            }

            if ($_FILES['photos']['name']) {
                $photos = images_upload_from_form('photos', 'objects', $object_id);
                if (!count($photos))
                    message_box_err('Can`t upload photos');

                foreach ($photos as $photo) {
                    $photo->resize('mini', ['w' => 1000]);
                    $photo->resize('list', ['w' => 300]);
                }
            }

            message_box_ok(sprintf('Added new object %d', $object_id));
            return mk_url(['mod' => $this->name, 'id' => $object_id]);

        case 'object_edit':
            $rc = object_edit($args['object_id'],
                              $args['catalog_id'],
                              $args['location_id'],
                              $args['object_name'],
                              $args['object_description'],
                              $args['objects_number']);
            if ($rc)
                message_box_err('Can`t edit object');

            if ($_FILES['photos']['name']) {
                $photos = images_upload_from_form('photos', 'objects', $args['object_id']);
                if (!count($photos))
                    message_box_err('Can`t upload photos');

                foreach ($photos as $photo) {
                    if (!$photo) {
                        message_box_err('Can`t resize photos');
                        message_box_ok(sprintf('Object %d changed', $args['object_id']));
                    }
                    $photo->resize('mini', ['w' => 1000]);
                    $photo->resize('list', ['w' => 300]);
                }
            }

            message_box_ok(sprintf('Object %d changed', $args['object_id']));
            return mk_url(['mod' => $this->name, 'id' => $args['object_id']]);

        case 'remove_object':
            $obj_id = $args['id'];
            $photos = images_by_obj_id('objects', $obj_id);
            if ($photos)
                foreach ($photos as $photo)
                    $photo->remove();
            $obj = object_by_id($obj_id);
            db()->query('delete from objects where id = %d', $obj_id);
            message_box_ok(sprintf('Object %d was removed', $obj_id));
            return mk_url(['mod' => 'catalog', 'id' => $obj['catalog_id']]);


        case 'remove_photo':
            $photo = image_by_hash($args['photo_hash']);
            $photo->remove();
            return mk_url(['mod' => $this->name, 'id' => $args['obj_id']]);


        }
        return mk_url(['mod' => $this->name]);
    }



}

modules()->register('object', new Mod_object);
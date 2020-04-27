<?php

namespace Lightning\Model;

use Lightning\Tools\Database;

/**
 * Class CMS
 * @package Source\Model
 *
 * @parameter integer $id
 * @parameter integer $cms_id
 * @parameter string $note
 * @parameter string $name
 * @parameter string content
 * @parameter string class
 * @parameter integer last_modified
 */
class CMSOverridable extends BaseObject {

    const TABLE = 'cms';
    const PRIMARY_KEY = 'cms_id';

    /**
     * @param string $name
     * @return bool|CMSOverridable
     * @throws \Exception
     */
    public static function loadByName($name) {
        $content = Database::getInstance()->selectRow(static::TABLE, ['name' => $name]);
        if ($content) {
            return new static($content);
        } else {
            return false;
        }
    }
}

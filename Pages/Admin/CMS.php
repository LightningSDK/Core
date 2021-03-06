<?php

namespace lightningsdk\core\Pages\Admin;

use lightningsdk\core\Model\Permissions;
use lightningsdk\core\Tools\ClientUser;
use lightningsdk\core\Tools\Output;
use lightningsdk\core\Tools\Request;
use lightningsdk\core\View\API;
use lightningsdk\core\Model\CMS as CMSModel;
use lightningsdk\core\View\CMS as CMSView;

class CMS extends API {

    public function hasAccess() {
        return ClientUser::requirePermission(Permissions::EDIT_CMS);
    }

    /**
     * Save most CMS objects.
     */
    public function postSave() {
        if (ClientUser::getInstance()->isAdmin()) {
            $name = Request::post('cms');
            $content = Request::post('content', Request::TYPE_HTML, '', '', true);
            CMSModel::insertOrUpdate(
                ['name' => $name, 'content' => $content, 'last_modified' => time()],
                ['content' => $content, 'last_modified' => time()]
            );
            Output::json(Output::SUCCESS);
        } else {
            Output::json(Output::ACCESS_DENIED);
        }
    }

    /**
     * Save image objects which have additional data.
     */
    public function postSaveImage() {
        if (ClientUser::getInstance()->isAdmin()) {
            $name = Request::post('cms');
            $content = Request::post('content');
            $class = Request::post('class');
            CMSModel::insertOrUpdate(
                ['name' => $name, 'content' => $content, 'last_modified' => time(), 'class' => $class],
                ['content' => $content, 'last_modified' => time(), 'class' => $class]
            );
            // we want to clear the cache so the changes show up on the next page load
            CMSView::clearCache();
            Output::json(Output::SUCCESS);
        } else {
            Output::json(Output::ACCESS_DENIED);
        }
    }
}

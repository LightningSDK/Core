<?php

namespace lightningsdk\core\Pages\Mailing;

use lightningsdk\core\Pages\Table;
use lightningsdk\core\Tools\ClientUser;
use lightningsdk\core\Tools\Database;
use lightningsdk\core\Tools\Request;
use lightningsdk\core\Tools\Template;

class ListUsers extends Table {

    const TABLE = 'user';
    const PRIMARY_KEY = 'user_id';

    protected $accessTable = 'message_list_user';
    protected $fields = [
        'user_id' => [],
        'email' => [
            'type' => 'email',
        ],
        'last' => [
            'type' => 'string',
        ],
        'first' => [
            'type' => 'string',
        ],
    ];

    protected $action_fields = [
        'select' => [
            'type' => 'checkbox',
            'display_name' => '',
        ]
    ];

    protected $rowClick = [
        'type' => 'url',
        'url' => '/admin/users?id=',
    ];

    protected $editable = false;
    protected $deleteable = false;

    public function __construct() {
        ClientUser::requireAdmin();

        $list_id = Request::get('list', 'int');
        if ($list_id === 0) {
            Template::getInstance()->set('title', 'Users not on any mailing list.');
            $this->accessTableWhere = [
                'message_list_id' => ['IS NULL'],
            ];
        } elseif ($list_id > 0) {
            $list = Database::getInstance()->selectField('name', 'message_list', ['message_list_id' => $list_id]);
            Template::getInstance()->set('title', "Users on list {$list}.");
            $this->accessTableWhere = [
                'message_list_id' => $list_id,
            ];
        } else {
            Template::getInstance()->set('title', 'All users on all lists.');
        }

        parent::__construct();
    }
}

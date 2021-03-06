<?php

namespace lightningsdk\core\Database\Schema;

use lightningsdk\core\Database\Schema;

class MessageCriteria extends Schema {

    const TABLE = 'message_criteria';

    public function getColumns() {
        return [
            'message_criteria_id' => $this->autoincrement(),
            'criteria_name' => $this->varchar(255),
            'join' => $this->varchar(255),
            'where' => $this->varchar(255),
            'select' => $this->varchar(255),
            'group_by' => $this->varchar(255),
            'having' => $this->varchar(255),
        ];
    }

    public function getKeys() {
        return [
            'primary' => 'message_criteria_id',
        ];
    }
}

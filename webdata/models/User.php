<?php

class User extends Pix_Table
{
    public function init()
    {
        $this->_name = 'user';

        $this->_primary = 'user_id';

        $this->_columns['user_id'] = array('type' => 'char', 'size' => 9);
        $this->_columns['user_name'] = array('type' => 'varchar', 'size' => 255);
        $this->_columns['display_name'] = array('type' => 'varchar', 'size' => 255);

        $this->addIndex('user_name', array('user_name'), 'unique');
    }
}

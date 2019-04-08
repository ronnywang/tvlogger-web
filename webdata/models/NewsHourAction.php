<?php

class NewsHourAction extends Pix_Table
{
    public function init()
    {
        $this->_name = 'news_hour_action';
        $this->_primary = array('channel', 'time', 'id');

        $this->_columns['id'] = array('type' => 'char', 'size' => 10);
        $this->_columns['channel'] = array('type' => 'varchar', 'size' => 8);
        $this->_columns['time'] = array('type' => 'int');
        $this->_columns['data'] = array('type' => 'json');
    }

}

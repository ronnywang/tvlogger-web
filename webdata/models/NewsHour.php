<?php

class NewsHour extends Pix_Table
{
    public function init()
    {
        $this->_name = 'news_hour';
        $this->_primary = array('channel', 'time');

        $this->_columns['channel'] = array('type' => 'varchar', 'size' => 8);
        $this->_columns['time'] = array('type' => 'int');
        $this->_columns['data'] = array('type' => 'json');
    }
}

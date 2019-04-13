<?php

class NewsHourRow extends Pix_Table_Row
{
    public function getData()
    {
        $data = json_decode($this->data);
        if (!$data->sections[count($data->sections) - 1]->end) {
            arraY_pop($data->sections);
        }
        return $data;
    }
}

class NewsHour extends Pix_Table
{
    public function init()
    {
        $this->_name = 'news_hour';
        $this->_primary = array('channel', 'time');
        $this->_rowClass = 'NewsHourRow';

        $this->_columns['channel'] = array('type' => 'varchar', 'size' => 8);
        $this->_columns['time'] = array('type' => 'int');
        $this->_columns['data'] = array('type' => 'json');
    }
}

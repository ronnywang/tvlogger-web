<?php

class NewsHourActionRow extends Pix_Table_Row
{
    public function getData()
    {

        $data = json_decode($this->data);
        if (property_exists($data, 'result')) {
            foreach ($data->result as $k => $v) {
                $data->meta->{$k} = $v;
            }
            return $data->meta;
        }

        $sections_data = $this->news_hour->getData();
        $processing_sections = $sections_data->sections;
        $actions = json_decode($this->data)->data;
        return NewsHourAction::actionToResult($actions, $processing_sections);
    }
}

class NewsHourAction extends Pix_Table
{
    public function init()
    {
        $this->_name = 'news_hour_action';
        $this->_primary = array('channel', 'time', 'id');
        $this->_rowClass = 'NewsHourActionRow';

        $this->_columns['id'] = array('type' => 'char', 'size' => 10);
        $this->_columns['channel'] = array('type' => 'varchar', 'size' => 8);
        $this->_columns['time'] = array('type' => 'int');
        $this->_columns['data'] = array('type' => 'json');

        $this->_relations['news_hour'] = array('rel' => 'has_one', 'type' => 'NewsHour');
    }

    public static function actionToResult($actions, $processing_sections)
    {
        $result = new StdClass;
        $result->sections = array();
        $result->pending = array();
        $result->warnings = array();
        if (!$processing_sections[count($processing_sections) - 1]->end) {
            array_shift($processing_sections[count($processing_sections) - 1]);
        }
        
        $sections = array();

        $last_section_id = function() use (&$sections) {
            $vars = array_keys($sections);
            return $vars[count($vars) - 1];
        };

        $button_map = array(
            'btn-right' => array('新聞', 'news'),
            'btn-ad' => array('廣告', 'ad'),
            'btn-section-other' => array('其他', 'other'),
            'btn-section-start' => array('轉場', 'start'),
        );
        foreach ($actions as $action) {
            if (is_array($action)) {
                $action_params = array_slice($action, 1);
                $action = $action[0];
            }
            $processing_section = $processing_sections[0];
            $start = intval($processing_section->start);
            $end = intval($processing_section->end);
            $youtube_id = $processing_section->{'youtube-id'};
            $youtube_title = $processing_section->{'youtube-title'};

            if (array_key_exists($action, $button_map)) {
                $section = new StdClass;
                $section->start = $start;
                $section->end = $end;
                $section->youtube_title = $youtube_title;
                $section->youtube_id = $youtube_id;
                $section->members = array($start);
                $section->type = $button_map[$action][0];
                $section->title = "{$youtube_title}({$youtube_id})";
                $sections[$start] = $section;
                array_shift($processing_sections);
            } elseif ($action == 'btn-like-prev') {
                $last_start = $last_section_id();
                $sections[$last_start]->members[] = $start;
                $sections[$last_start]->end = $end;
                if (!$sections[$last_start]->youtube_id and $youtube_id) {
                    $sections[$last_start]->youtube_id = $youtube_id;
                    $sections[$last_start]->youtube_title  = $youtube_title;
                    $sections[$last_start]->title = "{$youtube_title}({$youtube_id})";
                }
                array_shift($processing_sections);
            } elseif ($action == 'split') {
                $origin_start = $action_params[0];
                $new_start = $action_params[1];
                $members = $sections[$origin_start]->members;
                $start = $origin_start;
                $end = $sections[$origin_start]->end;

                $sections[$origin_start]->end = $new_start - 1;
                $sections[$origin_start]->members = array_filter($members, function($s) use ($new_start) { return $s  < $new_start; });

                $section = new StdClass;
                $section->start = $new_start;
                $section->end = $end;
                $section->type = $sections[$origin_start]->type;
                $section->members = array_filter($members, function($s) use ($new_start) { return $s >= $new_start; });

                $sections[$new_start] = $section;
                ksort($sections);
            } elseif ($action == 'set-title') {
                $start = intval($action_params[0]);
                $title = $action_params[1];
                if (!array_key_exists($start, $sections)) {
                    $warnings[] = array($start, "無法成功塞入標題 {$title}");
                } else {
                    $sections[$start]->title = $title;
                }
            } else {
                throw new Exception("沒有實作這動作 {$action}");
            }
        }

        $sections = array_values($sections);
        $result->sections = $sections;
        $result->pending = $processing_sections;

        $score = 100;
        $warnings = array();

        // rule1: 如果超過 10% 沒處理就 -10，超過 30% 沒處理 -30，超過 50 % 沒處理 -50
        // rule2: 如果新聞區沒有加標題的話，一則 -2 分，最多 -20 
        // rule3: 如果新聞區的量 < 50% ，直接 -30

        // rule1: 如果超過 10% 沒處理就 -10，超過 30% 沒處理 -30，超過 50 % 沒處理 -50
        if ($processing_sections) {
            $start = $processing_sections[0]->start - 1;
            $end = $processing_sections[count($processing_sections) - 1]->end;
            if ($start / $end < 0.1) {
                $score -= 90;
            } elseif ($start / $end < 0.5) {
                $score -= 50;
            } elseif ($start / $end < 0.7) {
                $score -= 30;
            }

            if ($start / $end < 0.7) {
                $duration = $end - $start + 1;
                if ($duration > 60) {
                    $time = sprintf("%02d 分 %02d 秒", $duration / 60, $duration % 60);
                } else {
                    $time = sprintf("%02d 秒", $duration);
                }
                $warnings[] = array(null, sprintf("從 %02d:%02d 開始 還有 %s 片段未處理", $start / 60, $start % 60, $time));
            }
        }

        // rule2: 如果新聞區沒有加標題的話，一則 -2 分，最多 -20 
        $no_title = 0;
        foreach ($sections as $idx => $section) {
            if ($section->type !== '新聞') {
                continue;
            }

            if ($idx == 0 or $idx == count($sections) - 1) {
                continue;
            }

            if ((($section->title == '') or ($section->title == '()'))) {
                $no_title ++;
                $warnings[] = array($section->start, sprintf('%02d:%02d 沒有新聞標題', $section->start / 60, $section->start % 60), $idx);
            }
        }
        $score -= min(20, 2 * $no_title);

        // rule3: 如果新聞區的量 < 50% ，直接 -30

        $result->score = $score;
        $result->warnings = $warnings;

        return $result;
    }
}

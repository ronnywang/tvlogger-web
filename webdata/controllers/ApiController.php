<?php

class ApiController extends Pix_Controller
{
    public function listAction()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');

        $news_hours = array();
        $sql = "SELECT channel, time FROM news_hour";
        $res = NewsHour::getDb()->query($sql);
        while ($row = $res->fetch_array()) {
            $news_hour = new StdClass;
            $news_hour->channel = $row[0];
            $news_hour->time = intval($row[1]);
            $news_hour->result = array();
            $news_hours[$rows[0] . $rows[1]] = $news_hour;
        }

        $sql = "SELECT channel, time, id, data->'$.result_data_time', data->'$.result.score' FROM news_hour_action";
        $res = NewsHourAction::getDb()->query($sql);
        while ($row = $res->fetch_array()) {
            list($channel, $time, $id, $result_time, $result_score) = $row;

            $r = new StdClass;
            $r->id = $id;
            $r->created_at = intval($result_time);
            $r->score = intval($result_score);


            $news_hours[$channel . $time]->result[] = $r;
        }

        return $this->json(array_values($news_hours));
    }
}


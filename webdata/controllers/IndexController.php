<?php

class IndexController extends Pix_Controller
{
    public function init()
    {
        if (!$sToken = Pix_Session::get('sToken')) {
            $sToken = crc32(uniqid());
            Pix_Session::set('sToken', $sToken);
        }
        $this->view->sToken = $sToken;
    }

    public function indexAction()
    {
    }

    public function editAction()
    {
        $terms = explode('/', $this->getURI());
        list(, /*index*/, /*edit*/, $channel, $time) = $terms;

        if (!$news_hour = NewsHour::find(array(strval($channel), intval($time)))) {
            return $this->redirect('/');
        }
        if (array_key_exists(5, $terms) and $news_hour_action = NewsHourAction::find(array(strval($channel), intval($time), strval($terms[5])))) {
            $this->view->news_hour_action = $news_hour_action;
        } else {
            $this->view->news_hour_action = null;
        }
        $this->view->news_hour = $news_hour;
    }

    public function postAction()
    {
        list(, /*index*/, /*edit*/, $channel, $time) = explode('/', $this->getURI());
        if (!$news_hour = NewsHour::find(array(strval($channel), intval($time)))) {
            return $this->redirect('/');
        }
        if ($_POST['sToken'] != $this->view->sToken) {
            return $this->redirect('/');
        }
        $id = substr(str_replace('+', '', str_replace('/', '', base64_encode(md5(uniqid(), true)))), 0, 10);
        $data = new StdClass;
        $data->data = json_decode($_POST['data']);
        $data->meta = new StdClass;
        $data->meta->created_at = time();
        $data->meta->created_from = $_SERVER['REMOTE_ADDR'];
        $data->meta->from = $_POST['from-action'];
        $data->result = NewsHourAction::actionToResult($data->data, $news_hour->getData()->sections);
        $data->result_data_time = $news_hour->getData()->time;

        NewsHourAction::insert(array(
            'id' => $id,
            'channel' => $news_hour->channel,
            'time' => $news_hour->time,
            'data' => json_encode($data),
        ));
        return $this->redirect("/index/edit/{$channel}/{$time}/{$id}");
    }
}

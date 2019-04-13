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

    public function resultAction()
    {
        $terms = explode('/', $this->getURI());
        list(, /*index*/, /*result*/, $channel, $time, $id) = $terms;

        if (!$news_hour = NewsHour::find(array(strval($channel), intval($time)))) {
            return $this->redirect('/');
        }
        if (!$news_hour_action = NewsHourAction::find(array(strval($channel), intval($time), strval($id)))) {
            return $this->redirect('/');
        }

        if (array_key_exists('format', $_GET) and $_GET['format'] == 'csv') {
            $output = fopen('php://output', 'w');
            header('Content-Type: text/plain');
            fputcsv($output, array('start', 'end', 'duration', 'type', 'title'));
            foreach ($news_hour_action->getData()->sections as $section) {
                fputcsv($output, array(
                    intval($section->start),
                    intval($section->end),
                    intval($section->end - $section->start + 1),
                    strval($section->type),
                    strval($section->title),
                ));

            }
            return $this->noview();
        } else {
            return $this->json($news_hour_action->getData());
        }
    }

    public function checkAction()
    {
        $terms = explode('/', $this->getURI());
        list(, /*index*/, /*check*/, $channel, $time) = $terms;

        if (!$news_hour = NewsHour::find(array(strval($channel), intval($time)))) {
            return $this->redirect('/');
        }

        if (array_key_exists('from-action', $_POST) and $_POST['from-action']) {
            $from_action = $news_hour->actions->search(array('id' => $_POST['from-action']))->first();
        }
        $data = json_decode($_POST['data']);
        $result = NewsHourAction::actionToResult($data, $news_hour->getData()->sections);
        if ($from_action) {
            if (json_encode(json_decode($from_action->data)->data) == json_encode($data)) {
                $result->score -= 50;
                $result->warnings[] = array(null, '您並未做任何修改');
            }
        }
        return $this->json($result);
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

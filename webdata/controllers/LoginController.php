<?php

class LoginController extends Pix_Controller
{
    public function init()
    {
        if (!$sToken = Pix_Session::get('sToken')) {
            $sToken = crc32(uniqid());
            Pix_Session::set('sToken', $sToken);
        }
        $this->view->sToken = $sToken;
        $this->view->user = ($user_id = Pix_Session::get('user_id')) ? User::find($user_id) : null;
    }

    public function googledoneAction()
    {
        $return_to = 'https://' . $_SERVER['HTTP_HOST'] . '/login/googledone';

        $params = array();
        $params[] = 'code=' . urlencode($_GET['code']);
        $params[] = 'client_id=' . urlencode(getenv('GOOGLE_CLIENT_ID'));
        $params[] = 'client_secret=' . urlencode(getenv('GOOGLE_CLIENT_SECRET'));
        $params[] = 'redirect_uri=' . urlencode($return_to);
        $params[] = 'grant_type=authorization_code';
        $curl = curl_init('https://www.googleapis.com/oauth2/v4/token');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, implode('&', $params));
        $obj = json_decode(curl_exec($curl));
        if (!$obj->id_token) {
            return $this->alert('login failed', '/');
        }
        $tokens = explode('.', $obj->id_token);
        $login_info = json_decode(base64_decode($tokens[1]));
        if (!$login_info->email or !$login_info->email_verified) {
            return $this->alert('login failed', '/');
        }
        $email = $login_info->email;
        if (!$user = User::search(array('user_name' => 'google://' . $email))->first()) {
            Pix_Session::set('creating_user', $login_info);
            return $this->redirect('/login/newuser');
        }
        Pix_Session::set('user_id', $user->user_id);
        return $this->redirect('/');
    }

    public function newuserAction()
    {
        if (!$login_info = Pix_Session::get('creating_user')) {
            return $this->alert('creating user failed', '/');
        }
        $this->view->display_name = $login_info->name;

        if ($_POST['sToken']) {
            if ($_POST['sToken'] != $this->view->sToken) {
                return $this->alert('wrong stoken', '/login/newuser');
            }

            if (!is_scalar($_POST['display_name'])) {
                return $this->alert('顯示名稱不正確', '/login/newuser');
            }

            if (mb_strlen($_POST['display_name']) <= 0 or mb_strlen($_POST['display_name'], 'UTF-8') > 16) {
                return $this->alert('顯示名稱長度需在 1 ~ 16 字', '/login/newuser');
            }

            while (true) {
                $id = substr(str_replace('+', '', str_replace('/', '', base64_encode(md5(uniqid(), true)))), 0, 8);
                if (!User::find($id)) {
                    break;
                }
            }
            $user = User::insert(array(
                'user_id' => $id,
                'user_name' => "google://{$login_info->email}",
                'display_name' => $_POST['display_name'],
            ));
            Pix_Session::set('creating_user', null);
            Pix_Session::set('user_id', $user->user_id);
            return $this->redirect('/');
        }
    }

    public function googleAction()
    {
        $return_to = 'https://' . $_SERVER['HTTP_HOST'] . '/login/googledone';
        $url = 'https://accounts.google.com/o/oauth2/auth?'
            . '&state='
            . '&scope=email profile'
            . '&redirect_uri=' . urlencode($return_to)
            . '&response_type=code'
            . '&client_id=' . getenv('GOOGLE_CLIENT_ID')
            . '&access_type=offline';
        return $this->redirect($url);
    }

    public function logoutAction()
    {
        Pix_Session::delete('user_id');
        return $this->redirect('/');
    }
}

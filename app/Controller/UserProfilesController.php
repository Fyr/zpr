<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 05.08.12
 * Time: 15:22
 * To change this template use File | Settings | File Templates.
 */
/**
 * Class UserProfilesController
 * @deprecated old version controller
 */
class UserProfilesController extends AppController {
    public $uses = array('User',
                         'Country',
                         'UserRating');
    public $components = array('RequestHandler');

    public function edit($id) {
        $success = true;
        $data = array();

        $imageTypes = array(
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_JPEG => 'jpeg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WBMP => 'wbmb',
            IMAGETYPE_XBM => 'xbm'
        );

        $imageSizes = array(
            '4'   => array('width' => 4,   'height' => 4),
            '6'   => array('width' => 6,   'height' => 6),
            '8'   => array('width' => 8,   'height' => 8),
            '12'  => array('width' => 12,  'height' => 12),
            '16'  => array('width' => 16,  'height' => 16),
            '20'  => array('width' => 20,  'height' => 20),
            '24'  => array('width' => 24,  'height' => 24),
            '28'  => array('width' => 28,  'height' => 28),
            '32'  => array('width' => 32,  'height' => 32),
            '36'  => array('width' => 36,  'height' => 36),
            '48'  => array('width' => 48,  'height' => 48),
            '50'  => array('width' => 50,  'height' => 50),
            '64'  => array('width' => 64,  'height' => 64),
            '75'  => array('width' => 75,  'height' => 75),
            '88'  => array('width' => 88,  'height' => 88),
            '96'  => array('width' => 96,  'height' => 96),
            '128' => array('width' => 128, 'height' => 128),
            '256' => array('width' => 256, 'height' => 256),
        );

        if (!$id) {
            $success = false;
            $data = 'no user specified';
        }

        if (Configure::read('debug') == 0 and $success and !$this->Auth->authenticate($id, $this->authCookie)) {
            $this->response->statusCode(403);

            $success = false;
            $data = 'access denied';
        }

        if ($success) {
            $user = $this->User->findByFbId($id);
            if (!$user or empty($user)) {
                $success = false;
                $data = 'bad user request';
            }
        }

        if ($success) {
            $name = isset($this->data['name']) ? ($this->data['name']) : false;
            if (!$name) {
                $success = false;
                $data = 'no name specified';
            }
        }

        if ($success) {
            $country_code = isset($this->data['country']) ? ($this->data['country']) : false;
            if (!$country_code) {
                $success = false;
                $data = 'no country specified';
            } else {
                $country_id = $this->Country->field('id', array('UPPER(code) = UPPER(TRIM(?))' => $country_code));
                if (!$country_id) {
                    $success = false;
                    $data = 'bad country request';
                }
            }
        }

        if ($success) {
            $picture_url = isset($this->data['picture']) ? ($this->data['picture']) : false;
            // $picture_url = preg_replace('/^https:\/\//i', 'http:\/\/', $picture_url);
            if (!$picture_url) {
                $success = false;
                $data = 'no picture specified';
            } elseif (($image_size = getimagesize($picture_url)) === false) {
                $success = false;
                $data = 'bad picture request';
            } elseif (!isset($imageTypes[$image_size[2]])) {
                $success = false;
                $data = 'bad picture format';
            }

            $w  = isset($this->data['w'])  ? ($this->data['w'])  : false;
            $h  = isset($this->data['h'])  ? ($this->data['h'])  : false;
            $x  = isset($this->data['x'])  ? ($this->data['x'])  : 0;
            $x2 = isset($this->data['x2']) ? ($this->data['x2']) : false;
            $y  = isset($this->data['y'])  ? ($this->data['y'])  : 0;
            $y2 = isset($this->data['y2']) ? ($this->data['y2']) : false;
        }

        if ($success) {
            if ($w === false and $h === false) {
                $w = $image_size[0];
                $h = $image_size[1];
            }
            if ($x2 === false and $y2 === false) {
                $x2 = min($w + $x, $w);
                $y2 = min($h + $y, $h);
            }
            $w = min($x2 - $x, $w);
            $h = min($y2 - $y, $h);

            $im_fn = "imagecreatefrom{$imageTypes[$image_size[2]]}";
            $im = $im_fn($picture_url);

            $res_im = imagecreatetruecolor($w, $h);
            imagecopy($res_im, $im, 0, 0, $x, $y, $w, $h);

            foreach ($imageSizes as $filename => $params) {
                $im = imagecreatetruecolor($params['width'], $params['height']);
                imagecopyresampled($im, $res_im, 0, 0, 0, 0, $params['width'], $params['height'], $w, $h);
                if (!file_exists("img/users/{$id}")) {
                    mkdir("img/users/{$id}", 0777, true);
                }
                imagejpeg($im, "img/users/{$id}/{$filename}.jpg", 100);
            }

            imagedestroy($im);
            imagedestroy($res_im);

            try {
                $user['User']['country_id'] = $country_id;
                $user['User']['name']       = $name;
                $user['User']['is_ready']      = 1;

                if (!$this->User->save($user)) {
                    $success = false;
                    $data = 'user saving error';
                }
            } catch (Exception $e) {
                $success = false;
                $data = 'user saving error';
            }

            if ($success) {
                $joins   = array();
                $joins[] = array(
                    'table'      => $this->Country->getDataSource()->fullTableName($this->Country),
                    'alias'      => 'Country',
                    'type'       => 'LEFT',
                    'conditions' => array('Country.id = User.country_id')
                );
                $joins[] = array(
                    'table'      => $this->UserRating->getDataSource()->fullTableName($this->UserRating),
                    'alias'      => 'UserRating',
                    'type'       => 'LEFT',
                    'conditions' => array('UserRating.user_id = User.id',
                        'UserRating.country_id = User.country_id')
                );
                $user    = $this->User->find('first', array('conditions' => array('fb_id' => $id),
                                                            'fields'     => array('User.id',
                                                                                  'User.fb_id',
                                                                                  'User.name',
                                                                                  'User.created',
                                                                                  'User.is_ready',
                                                                                  'User.post_id',
                                                                                  'User.last_position',
                                                                                  'Country.id',
                                                                                  'Country.code',
                                                                                  'IFNULL(UserRating.positive_votes, 0) as positive_votes',
                                                                                  'IFNULL(UserRating.negative_votes, 0) as negative_votes'),
                                                            'joins'      => $joins));

                $joins   = array();
                $joins[] = array(
                    'table'      => $this->User->getDataSource()->fullTableName($this->User),
                    'alias'      => 'User',
                    'type'       => 'INNER',
                    'conditions' => array('User.id = UserRating.user_id',
                        'User.is_ready' => 1)
                );

                $res = $this->UserRating->find('first', array(
                    'conditions' => array(
                        'UserRating.country_id' => $user['Country']['id'],
                        'OR'                    => array(
                            'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0) >' => $user[0]['positive_votes'] - $user[0]['negative_votes'],
                            'AND'                                                                => array(
                                'IFNULL(UserRating.positive_votes, 0) - IFNULL(UserRating.negative_votes, 0)' => $user[0]['positive_votes'] - $user[0]['negative_votes'],
                                'User.created <'                                                   => $user['User']['created']
                            )
                        )
                    ),
                    'fields'     => array('COUNT(DISTINCT UserRating.user_id) + 1 as rating'),
                    'joins'      => $joins));

                $data['user']                  = array();
                $data['user']['id']            = $user['User']['fb_id'];
                $data['user']['name']          = $user['User']['name'];
                $data['user']['is_ready']         = $user['User']['is_ready'] ? true : false;
                $data['user']['post_id']       = $user['User']['post_id'];
                $data['user']['last_position'] = $user['User']['last_position'];
                $data['country']               = array();
                $data['country']['name']       = $user['Country']['name'];
                $data['likes']                 = $user[0]['positive_votes'];
                $data['dislikes']              = $user[0]['negative_votes'];
                $data['position']              = $res[0]['rating'];
            }
        }

        $answer = array('status' => ($success ? 'success' : 'error'), 'data' => $data);

        $this->set(compact('answer'));
    }
}
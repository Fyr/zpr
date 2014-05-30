<?php

/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 08.04.14
 * Time: 22:44
 * To change this template use File | Settings | File Templates.
 */
class Credo extends AppModel {
    public $useTable = 'credo';

    const CREDO_FIELD_LENGTH = 200;

    public function getCredoId($text, $create = true) {
        $credo = $this->findByText($text);
        if (!$credo or empty($credo)) {
            if ($create) {
                $this->create();
                $res = $this->save(array('Credo' => array('text' => $text)));
                if (!$res) {
                    return null;
                }

                return $this->id;
            } else {
                return null;
            }
        }

        return $credo['Credo']['id'];
    }
}
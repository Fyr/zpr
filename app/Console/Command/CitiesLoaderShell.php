<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Rem
 * Date: 1/13/13
 * Time: 1:17 AM
 */

class CitiesLoaderShell extends AppShell {
    public $uses = array('City');

    public function main() {
        $this->out('Test');

        // $this->out(print_r($this->City));

        // $this->City->reconnect();

        $offset = 0;
        $limit  = 2000;

        do {
            $result = $this->City->query("SELECT gn.id,
                                                 gn.name,
                                                 gn.asciiname,
                                                 gn.alternatenames,
                                                 gn.longitude,
                                                 gn.latitude,
                                                 gn.timezone,
                                                 c.country_id,
                                                 c.title_ru AS country_name,
                                                 TRIM(ac.id) AS region_code,
                                                 ac.name AS region_name
                                            FROM gn
                                            LEFT JOIN countries AS c
                                              ON LOWER(TRIM(c.country_id)) = LOWER(TRIM(gn.country_code))
                                            LEFT JOIN  gn_admin_codes AS ac
                                              ON ac.id = CONCAT(gn.country_code, '.', gn.admin1_code)
                                           WHERE feature_code IN ('PPLA', 'PPLA2', 'PPLA3', 'PPLA4', 'PPLC', 'PPLL', 'PPL')
                                            /* AND country_code = 'RU' */
                                           /* ORDER BY gn.id */
                                           LIMIT {$offset}, {$limit};", false);

    //        $this->out(print_r($result, 1));
    //        die;

            $all_cnt = 0;
            if ($result and is_array($result)) {
                $cnt = 0;
                foreach ($result as $row) {
                    ++$all_cnt;
                    $res = $this->City->find('first', array('conditions' => array(
                        'name'        => $row['gn']['name'],
                        'country_id'  => $row['c']['country_id'],
                        'region_code' => $row[0]['region_code']
                    )));

//                    print_r($res);
//                    die();

                    if (!$res or empty($res) or !isset($res['City']) or empty($res['City'])) {
                        $this->City->create();

                        $data = array();
                        $data['name']              = $row['gn']['name'];
                        $data['longitude']         = $row['gn']['longitude'];
                        $data['latitude']          = $row['gn']['latitude'];
                        $data['asciiname']         = $row['gn']['asciiname'];
                        $data['alternative_names'] = $row['gn']['alternatenames'];
                        $data['timezone']          = $row['gn']['timezone'];
                        $data['country_id']        = $row['c']['country_id'];
                        $data['region_code']       = $row[0]['region_code'];
                        $data['region_name']       = $row['ac']['region_name'];

                        try {
                            $reason = 'unknown';
                            $res = $this->City->save($data);
                        } catch (Exception $e) {
                            $res = false;
                            $reason = $e->getMessage();
                        }

                        if (!$res) {
                            $this->out("Can't save city '{$data['name']} ({$data['country_id']} - {$data['region_name']})'.\n\treason - {$reason}.");
                        } else {
                            ++$cnt;
                        }
                    }
                }
                $this->out("limit={$limit}, offset={$offset} : {$cnt} from " . count($result) . " rows loaded. (all_cnt={$all_cnt})");
            } else {
                die('error');
            }
            $offset += $limit;
        } while ($all_cnt == $limit);
    }
}
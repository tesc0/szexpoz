<?php

class Calendar
{

    private $db;
    private $question_number = 8;
    private $month_names = ["Január", "Február", "Március", "Április", "Május", "Június", "Július", "Augusztus", "Szeptember", "Október", "November", "December"];
    private $day_names = ["Hétfő", "Kedd", "Szerda", "Csütörtök", "Péntek", "Szombat", "Vasárnap"];
    private $sexpositions = [];
    private $html;
    private $months = [];
    private $frequency, $frequency_final;
    private $repeat = false;
    private $sexpositions_temp = [];
    private $sex_dates = [];
    private $merged_preferences = [];
    private $startDate = "";
    private $is53Week = false;
    private $positions_leftover = [];

    public function __construct($db_conn)
    {
        $this->db = $db_conn;

        //$this->setMonths();
        //$this->getAllDays();
    }

    public function setFrequency($data)
    {
        $this->frequency = $data;
    }

    public function test()
    {
        $this->setStartDate( date("Y-m-d") );
        $this->getAllDays();
    }

    /*
     * mindkét fél válaszolt-e
     */
    public function checkQuestionnaires($questionnaire_id)
    {

        $selectStatement = $this->db->select(["*"])
            ->from("answers")
            ->where(new \FaaPz\PDO\Clause\Conditional("questionnaire_id", "=", $questionnaire_id));
        $stmt = $selectStatement->execute();
        $answers_data = $stmt->fetch();

        if (!empty($answers_data)) {
            if (count($answers_data) % ($this->question_number * 2) == 0) {
                return true;
            }
        }

        return false;
    }

    /*
     * naptár-feltétlek megszerzése: pózok, gyakoriság és ismétlődés
     */
    public function getCalendarData($questionnaire_id)
    {
        $selectStatement = $this->db->select(["data"])
            ->from("answers")
            ->where(new \FaaPz\PDO\Clause\Conditional("questionnaire_id", "=", $questionnaire_id));
        $stmt = $selectStatement->execute();
        $answers_data = $stmt->fetch();

        if (!empty($answers_data)) {
            foreach ($answers_data as $answer) {
                $json = json_decode($answer["data"]);

                $position_list[] = $json[7]->position_order;

                if ($json[5]->answer == "Heti 1-2 alkalommal.") {
                    $frequencies[] = $this->frequency = [1, 2];
                } else if ($json[5]->answer == "Heti 3-4 alkalommal.") {
                    $frequencies[] = $this->frequency = [3, 4];
                } else if ($json[5]->answer == "Heti 5-6 alkalommal.") {
                    $frequencies[] = $this->frequency = [5, 6];
                } else {
                    $frequencies[] = $this->frequency = [7, 7];
                }

                if (strpos($json[6]->answer, "egy-egy") !== false) {
                    $this->repeat = true;
                }
            }

            $this->frequency[0] = ($frequencies[0][0] + $frequencies[1][0]) / 2;
            $this->frequency[1] = ($frequencies[0][1] + $frequencies[1][1]) / 2;

            $position_order = [];

            foreach($position_list[0] as $index => $position) {
                foreach($position_list[1] as $index2 => $position2) {
                    if($position == $position2) {
                        $position_order[ round($index + $index2 / 2) ] = $position;
                    }
                }
            }

            $this->merged_preferences = $position_order;
            ksort($this->merged_preferences);

        }

    }

    /**
     *
     *
     * @return mixed
     */
    public function getSexPosition()
    {


    }

    /*
     * hónapok beállítása
     */
    public function setStartDate($date)
    {
        $this->startDate = $date;

        $date_temp = new DateTime($date);
        $date_temp->setISODate($date_temp->format("Y"), 53);

        $this->is53Week = ($date_temp->format("W") === "53") ? true : false;
    }

    /*
     * tömb minden nappal
     */
    public function getAllDays()
    {

        // a hetek megszerzése évekre bontva, a 12 hónapra megfelelően
        $begin = new DateTime($this->startDate);
        $end = new DateTime();
        $end = $end->modify( '+1 year' );

        $interval = new DateInterval('P1W');
        $daterange = new DatePeriod($begin, $interval ,$end);

        foreach($daterange as $date){
            $year = $date->format("Y");
            $month = $date->format("m");
            $day = $date->format("d");
            $week = $date->format("W");

            $weeks_array[$year][$week] = [];
        }
//print_R($weeks_array);

        // frekvencia
        $freq_low = $this->frequency[0];
        if (!empty($this->frequency[1])) {
            $freq_high = $this->frequency[1];

            $frequency = rand($freq_low, $freq_high);
        } else {
            $frequency = $this->frequency[0];
        }

        $this->frequency_final = $frequency;


        // a korábbi év-hét tömb feldolgozása, a frekvenciának (heti gyakoriságnak) megfelelően feltöltjük a tömböt hónap-nap kulcsokkal
        if (!empty($weeks_array)) {

            foreach ($weeks_array as $year => $data) {
                foreach ($data as $week => $value) {
                    //echo "<br>";
                    //echo date("Y-m-d", strtotime($year . "W" . $week));
                    $randoms = [];


                    $ok = false;
                    while (!$ok) {
                        $random_day = rand(1, 7);

                        if (!in_array($random_day, $randoms)) {
                            $randoms[] = $random_day;
                        }

                        if (count($randoms) == $frequency) {
                            $ok = true;
                        }
                    }


                    for ($i = 0; $i <= 6; $i++) {

                        $year_ = $year;

                        $month = date("m", strtotime($year . "W" . $week . " +" . $i . "days"));
                        $day = date("d", strtotime($year . "W" . $week . " +" . $i . "days"));
                        $year2 = date("Y", strtotime($year . "W" . $week . " +" . $i . "days"));

                        //echo $year2 . "---" . $year . "W" . $week . " +" . $i . "days";

                        if($year2 == $year + 1) {
                            $year_ = $year2;
                        }

                        $dates[$year_][$month][$day] = "";
                        foreach ($randoms as $random_day) {
                            if ($random_day == date("N", strtotime($year_ . "-" . $month . "-" . $day))) {
                                $dates[$year_][$month][$day] = 1;
                            }
                        }

                    }
                }

            }

            $this->sex_dates = $dates;
/*
            echo "<pre>";
            print_r($dates);
*/
        }


    }

    /*
     * naptár generálása
     */
    public function generateHTML()
    {
        $html = "";

        foreach ($this->months as $month) {

            $html .= "
            
<table>
    <thead>
    <tr>
        <th colspan=\"7\" class=\"center\">
            " . $month->format("Y") . $this->month_names[$month->format("m") - 1] . "
        </th>
    </tr>
    <tr>
        <th width=\"15%\">" . $this->day_names[0] . "</th>
        <th width=\"15%\">" . $this->day_names[1] . "</th>
        <th width=\"16%\">" . $this->day_names[2] . "</th>
        <th width=\"15%\">" . $this->day_names[3] . "</th>
        <th width=\"15%\">" . $this->day_names[4] . "</th>
        <th width=\"12%\">" . $this->day_names[5] . "</th>
        <th width=\"12%\">" . $this->day_names[6] . "</th>
    </tr>
    </thead>
    <tbody>
    <tr>";


            $days_in_month = $month->format("t");
            $start_day_of_week = $month->format("N") - 1;
            $day_of_week = $start_day_of_week;

            $loop = 0;
            for ($day = 1; $day <= $days_in_month; $day++) {

                if ($loop == 0 && $start_day_of_week != 0) {

                    $html .= "<td colspan=\"" . $start_day_of_week . "\"></td>";
                }


                $html .= "
<td>
            <div>
                <div>" . $day . " <br> <img src=\"gallery/sexpositionsclub_kepek/" . $this->sexpositions[$month->format("Y")][$month->format("m")][$month->format("d")] . "\"></div>
                <div></div>
            </div>
        </td>";
                if ($loop == $day and $day_of_week != 6) {
                    $html .= "<td colspan=\"" . (6 - $day_of_week) . "\">&nbsp;</td>";
                }
                if ($day_of_week == 6) {
                    $day_of_week = 0;
                    $html .= "</tr><tr>";
                } else {
                    $day_of_week++;
                }


                $loop++;
            }
        }

        $this->html = $html;
        return $html;
    }

    public function saveIntoPDF()
    {

    }


    public function sortPositionsForYear()
    {

        foreach($this->sexpositions as $item) {
            foreach($item as $position_name) {
                $this->sexpositions_temp[] = $position_name;
            }
        }

        shuffle($this->sexpositions_temp);
        //echo count($this->sexpositions_temp);
        echo "<pre>";
        //print_R($this->sex_dates);

        $count = 0;
        foreach($this->sex_dates as $year => $data) {
            foreach($data as $month => $data2) {
                foreach($data2 as $day => $value) {
                    if(!empty($value)) {
                        $count++;

                        $rand_key = array_rand($this->sexpositions_temp, 1);

                        echo $rand_key . "<br>";

                        $this->sex_dates[$year][$month][$day] = $this->sexpositions_temp[$rand_key];
                        unset($this->sexpositions_temp[$rand_key]);

                        shuffle($this->sexpositions_temp);

                        //print_r($this->sexpositions_temp);
                    }
                }
            }
        }


        print_R($this->sex_dates);
    }

    public function setTestData($order1, $order2)
    {
        foreach($order1 as $index => $pose) {
            foreach($order2 as $index2 => $pose2) {
                if($pose == $pose2) {
                    $this->merged_preferences[round(($index + $index2) / 2)] = $pose;
                }
            }

        }

        ksort($this->merged_preferences);
        //print_R($this->merged_preferences);
    }

    public function calculateCategories()
    {
        echo "<pre>";

        //10 kategória
        /*
        for($i = 1; $i <= 10; $i++) {
            $array[$i] = $i;
        }
        */

//kategóriák száma
        $preferences_keys = array_keys($this->merged_preferences);
        $length = rand(3, 10);
        $array2 = [];

        do {
            $selected = rand(1, count($preferences_keys) - 1);

            if(!in_array($preferences_keys[$selected], $array2)) {
                $array2[] = $preferences_keys[$selected];
            }
        } while( count($array2) != $length );

//kimaradt kategóriák
        $temp_r = [];
        foreach($this->merged_preferences as $index => $item) {
            if(!in_array($index, $array2)) {
                $temp_r[] = $index;
            }
        }
        /*
        echo "******************";
        echo "<br>";
        echo "kiválasztott kategóriák";
        echo "<pre>";
        print_R($array2);
        echo "<br>";
        echo "******************";
        echo "<br>";
        echo "kimaradt kategóriák";
        echo "<br>";
        print_R($temp_r);
        */

// 52 alkalomra való súlyozás

        $number_ = ($this->is53Week) ? 53 : 52;

        $avg = $number_ / array_sum($array2);
        $avg_rounded = round($avg);
/*
        echo '$avg: ' . $avg . ' / $avg_rounded: ' . $avg_rounded;
        echo "<br>";
        echo "<br>";
*/
        foreach($temp_r as $key => $temp_item) {
            $temp_r2[$temp_item] = round($temp_item * $avg);
        }

//kimaradtak alkalmai arányosan

        echo "<br>";
        echo "kimaradt kategóriák arányosan";
        echo "<br>";
        print_r($temp_r2);

        $this->positions_leftover = $temp_r2;

//kimaradtak alkalmainak összege
        $temp_sum = array_sum($temp_r2);
/*
        echo "<br>";
        echo "kimaradt kategóriák alkalmainak összege";
        echo "<br>";

        echo $temp_sum . "//////////////////<br><br><br>";
*/
        $array3 = [];

//kiválasztott kategóriák alkamai
        foreach($array2 as $val) {
            //echo $val . ": " . ($val * $avg) . " (" . round($val * $avg) . ") kerekitve: " . ($val * $avg_rounded) . "<br>";

            $times = round($val * $avg);

            for($i = 1; $i <= $times; $i++) {
                $array3[$val][$i] = [$val];
            }

        }
        /*
        echo "******************";
        echo "<br>";
        echo "<br>";
        echo "kiválasztott kategóriák alkalmai";
        echo "<br>";
        print_r($array3);
*/



        foreach($array3 as $category => $item3) {

            foreach($item3 as $index => $valueX) {

                /*
                 * x% esély van, hogy a kimaradt kategóriákból bepakol a kiválaszottak közé
                 * */
                $chance_ = rand(1, 100);
                if(1 == 0 && $chance_ < /*50*/ $temp_sum) {

                    if($temp_sum > 0) {

                        do {

                            $selected = rand(0, count($temp_r) - 1);
                            $key_ = $temp_r[$selected];

                            //echo "******:" . $temp_r2[$key_] . "----" . $selected . "<br>";
                            //var_dump(!empty($temp_r[$selected]) && $temp_r2[$key_] > 0);
                            //die();

                        } while ( empty($temp_r[$selected]) || $temp_r2[$key_] <= 0 );

                        $array4[$category][$index] = [$category, $temp_r[$selected]];

                        $temp_r2[$key_]--;
                        $temp_sum--;
                    }

                } else {
                    $array4[$category][$index] = [$category];
                }

            }


        }
/*
        echo "******************";
        echo "<br>";
        echo "<br>";
        echo "kategóriák beosztása";
        echo "<br>";
        print_r($array4);

        ob_clean();
*/

        $categories_temp = [];
        $categories_temp2 = $categories_temp;

        foreach($array4 as $category => $item) {

            //print_r($item);
            foreach($item as $index => $data_) {
                //print_r($data_);

                //echo "<br>kategóriák szűrve:******************* ";
                //print_R($categories_temp);

                if(!empty($data_[1])) {
/*
                    $sql = "SELECT * FROM sexpositionstags
                            WHERE tag_id IN (:tags_not)
                            GROUP BY position_id";
*/
                    if(!empty($categories_temp)) {
                        $sql = "SELECT * FROM sexpositionstags
                            WHERE tag_id IN (" . implode(", ", $categories_temp) . ")
                            GROUP BY position_id
                            ORDER BY id";
                        $query = $this->db->prepare($sql);
                        $query->execute([
                            //"tags_not" => implode(", ", $categories_temp)
                        ]);
                        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

                        $positions_to_filter = [];
                        foreach ($rows as $row) {
                            $positions_to_filter[] = $row["position_id"];
                        }
                    }

                    $sql_ext = "";
                    if(!empty($positions_to_filter)) {
                        $sql_ext = " AND sp_t.position_id NOT IN (" . implode(", ", $positions_to_filter) . ")";
                    }

                    $sql = "SELECT sp.name_en 
                    FROM sexpositions sp
                    LEFT JOIN sexpositionstags sp_t ON sp.id = sp_t.position_id
                    WHERE sp_t.tag_id IN (:tags) " . $sql_ext . "
                    ORDER BY RAND()
                    LIMIT 1";


/*
                    echo "<br>---------filter1-----";
print_r($positions_to_filter);
*/
                    $query = $this->db->prepare($sql);
                    $query->execute([
                        "tags" => implode(", ", [$data_[0], $data_[1]]),
                        //"positions_not" => "(" . implode(", ", $positions_to_filter) . ")"
                    ]);
                    $row = $query->fetchAll(PDO::FETCH_ASSOC);

                    $final[$category][$index] = $row[0]["name_en"];

                } else {
/*
                    $sql = "SELECT * FROM sexpositionstags
                            WHERE tag_id IN (:tags_not)
                            GROUP BY position_id";
*/
                    if(!empty($categories_temp)) {

                        $sql = "SELECT * FROM sexpositionstags
                            WHERE tag_id IN (" . implode(", ", $categories_temp) . ")
                            GROUP BY position_id
                            ORDER BY id";

                        $query = $this->db->prepare($sql);
                        $query->execute([
                            //"tags_not" => implode(", ", $categories_temp)
                        ]);
                        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

                        $positions_to_filter = [];
                        foreach ($rows as $row) {
                            $positions_to_filter[] = $row["position_id"];
                        }
/*
                        echo "<br>---------filter2-----";
                        print_r($positions_to_filter);
*/
                    }

                    $sql_ext = "";
                    if(!empty($positions_to_filter)) {
                        $sql_ext = " AND sp_t.position_id NOT IN (" . implode(", ", $positions_to_filter) . ")";
                    }


                    $sql = "SELECT sp.name_en 
                    FROM sexpositions sp
                    LEFT JOIN sexpositionstags sp_t ON sp.id = sp_t.position_id
                    WHERE sp_t.tag_id = :tag " . $sql_ext . "
                    ORDER BY RAND()
                    LIMIT 1";

                    $query = $this->db->prepare($sql);

                    $query->execute([
                        "tag" => $data_[0],
                        //"positions_not" => x
                    ]);
                    $row = $query->fetchAll(PDO::FETCH_ASSOC);

                    if(empty($row)) {
                        echo $data_[0] . "----------";
                        echo $sql . "<br><br>";

                        // TODO
                        // ha nem talál erre a kategóriára már

                        while( empty($row[0]) ) {

                            $maxVal = max($this->positions_leftover);
                            $maxKey = array_search($maxVal, $this->positions_leftover);

                            $query = $this->db->prepare($sql);
                            $query->execute([
                                "tag" => $maxKey,
                            ]);
                            $row = $query->fetchAll(PDO::FETCH_ASSOC);

                            if( empty($row) ) {
                                unset($this->positions_leftover[$maxKey]);
                            } else {
                                $this->positions_leftover[$maxKey]--;
                            }
                        }
                    }
                    $final[$category][$index] = $row[0]["name_en"];

                }

            }
            $categories_temp[] = $category;

        }
/*
        echo "******************";
        echo "<br>";
        echo "<br>";
        echo "végleges behelyettesítve?";
        echo "<br>";
        print_r($final);
*/
        $this->sexpositions = $final;
/*
        echo "******************";
        echo "<br>";
        echo "<br>";
        echo "fennmaradó kategóriák";
        echo "<br>";
        print_r($temp_r2);
*/
    }
}
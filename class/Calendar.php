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

    public function setFrequency($data)
    {
        $this->frequency = $data;
    }

    public function __construct($db_conn)
    {
        $this->db = $db_conn;

        //$this->setMonths();
        //$this->getAllDays();
    }

    public function test()
    {
        $this->setMonths();
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
                    $frequencies[] = $this->frequency = [7];
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

        }

    }

    public function fillDateArray()
    {

        $freq_low = $this->frequency[0];
        if (!empty($this->frequency[1])) {
            $freq_high = $this->frequency[1];

            $frequency = rand($freq_low, $freq_high);
        } else {
            $frequency = $this->frequency[0];
        }

        foreach ($this->months as $month) {
            $days_in_month = $month->format("t");

            for ($day = 1; $day <= $days_in_month; $day++) {


                $year = $month->format("Y");
                $month = $month->format("m");


                $day = sprintf("%02.02d", $day);

                $week_number = date("W", strtotime($year . "-" . $month . "-" . $day));

                $weeks_array[$year][$week_number] = [];

            }
        }

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

                        $month = date("m", strtotime($year . "W" . $week . " +" . $i . "days"));
                        $day = date("d", strtotime($year . "W" . $week . " +" . $i . "days"));

                        $dates[$year][$month][$day] = "";
                        foreach ($randoms as $random_day) {
                            if ($random_day == date("N", strtotime($year . "-" . $month . "-" . $day))) {
                                $dates[$year][$month][$day] = $this->getSexPosition();
                            }
                        }

                    }
                }
            }

        }
    }

    /**
     *
     *
     * @return mixed
     */
    public function getSexPosition()
    {

        $files = scandir("gallery/sexpositionsclub_kepek");

        $images = [];
        foreach ($files as $file) {
            if ($file != ".." && $file != ".") {
                $images[] = $file;
            }
        }

        $ok = false;

        do {
            $index = rand(0, count($images) - 1);
            $selected_position = $images[$index];

            if (empty($this->sexpositions_temp)) {
                $this->sexpositions_temp[] = $selected_position;
                $ok = true;
            } else {
                if ($this->repeat) {
                    $this->sexpositions_temp[] = $selected_position;
                    $ok = true;
                } else {
                    if(!in_array($selected_position, $this->sexpositions_temp)) {
                        $this->sexpositions_temp[] = $selected_position;
                        $ok = true;
                    }
                }
            }
        } while(!$ok);

        return $selected_position;
    }

    /*
     * szexpozíciók feltöltése nap szerint
     */
    public function fillPositions()
    {

        $util = new Util();

        $files = scandir("gallery/sexpositionsclub_kepek");

        $images = [];
        foreach ($files as $file) {
            if ($file != ".." && $file != ".") {
                $images[] = $file;
            }
        }

        $freq_low = $this->frequency[0];
        if (!empty($this->frequency[1])) {
            $freq_high = $this->frequency[1];

            $frequency = rand($freq_low, $freq_high);
        } else {
            $frequency = $this->frequency[0];
        }

        foreach ($this->sexpositions as $year => $data) {
            foreach ($data as $week => $value) {
                foreach ($value as $day => $item) {

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

                        $month = date("m", strtotime($year . "W" . $week . " +" . $i . "days"));
                        $day = date("d", strtotime($year . "W" . $week . " +" . $i . "days"));

                        $dates[$year][$month][$day] = "";
                        foreach ($randoms as $random_day) {
                            if ($random_day == date("N", strtotime($year . "-" . $month . "-" . $day))) {
                                $dates[$year][$month][$day] = 1;
                            }
                        }

                    }


                    if ($this->repeat) {
                        $this->sexpositions[$year][$month][$day] = $images[rand(0, count($images))];
                    } else {

                        $ok = false;
                        do {
                            $random_pose = $images[rand(0, count($images))];

                            if (!$util->in_array_r($random_pose, $this->sexpositions)) {
                                $ok = true;
                            }

                        } while (!$ok);

                        if ($ok) {
                            $this->sexpositions[$year][$month][$day] = $random_pose;
                        }
                    }
                }
            }
        }
    }

    /*
     * hónapok beállítása
     */
    public function setMonths()
    {
        for ($i = 0; $i < 12; $i++) {
            $this->months[] = new DateTime("today +" . $i . " months");
        }
    }

    /*
     * tömb minden nappal
     */
    public function getAllDays()
    {


        // a hetek megszerzése évekre bontva, a 12 hónapra megfelelően
        foreach ($this->months as $month) {
            $days_in_month = $month->format("t");

            for ($day = 1; $day <= $days_in_month; $day++) {

                $year = $month->format("Y");
                $month_ = $month->format("m");


                $date_current = new DateTime();
                $month_current = $date_current->format("m");
                $day_current = $date_current->format("d");
                if($month_ == $month_current) {
                    if($day < $day_current) {
                        continue;
                    }
                }


                $day = sprintf("%02.02d", $day);

                $week_number = date("W", strtotime($year . "-" . $month_ . "-" . $day));

                $weeks_array[$year][$week_number] = [];
            }
        }


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

                        $month = date("m", strtotime($year . "W" . $week . " +" . $i . "days"));
                        $day = date("d", strtotime($year . "W" . $week . " +" . $i . "days"));

                        $dates[$year][$month][$day] = "";
                        $this->sexpositions[$year][$month][$day] = "";
                        foreach ($randoms as $random_day) {
                            if ($random_day == date("N", strtotime($year . "-" . $month . "-" . $day))) {
                                $dates[$year][$month][$day] = 1;
                            }
                        }

                    }
                }

            }

            $this->sex_dates = $dates;

            echo "<pre>";
            print_r($dates);
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


    public function getPositions()
    {

        $count = 0;
        foreach($this->sex_dates as $year => $data) {
            foreach($data as $month => $data2) {
                foreach($data2 as $day => $value) {
                    if(!empty($value)) {
                        $count++;
                    }
                }
            }
        }

        $sexpositions_selected = [];
        while(1==1) {

            $selectStatement = $this->db->select(['id, img'])
                ->from('sexpositions')
                ->orderBy("RAND()")
                ->limit(new \FaaPz\PDO\Clause\Limit(1));

            $stmt = $selectStatement->execute();
            $data = $stmt->fetch();

            if(!empty($data)) {
                $sexpositions_selected[] = $data[0]["id"];
            }

        }
    }
}
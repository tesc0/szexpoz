<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

require_once "../config/info.php";
$db = new PDO("mysql:host=" . $config['db']['host'] . ";dbname=" . $config['db']['database'], $config['db']['username'], $config['db']['password'] );

$dsn = 'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['database'] . ';charset=utf8';
$usr = $config['db']['username'];
$pwd = $config['db']['password'];

//https://github.com/FaaPz/PDO
$pdo = new \FaaPz\PDO\Database($dsn, $usr, $pwd);

// Create App
$app = AppFactory::create();

// Create Twig
$twig = Twig::create('../templates');

// Add Twig-View Middleware
$app->add(TwigMiddleware::create($app, $twig));

$question_list = [
    "Mennyire vagy elégedett a szexuális életetekkel?",
    "Mit gondolsz, vajon a partnered elégedett az együttléteitekkel?",
    "Ha egy dolgot kérhetnél a párodtól, amit eddig sohasem mertél megemlíteni neki, mi lenne az?",
    "Mit szólnál hozzá, ha a partnered meztelenül, egyetlen Mikulás-sapkában várna otthon egy fárasztó nap után?",
    "Megnéznétek együtt egy erotikus filmet?",
    "Milyen gyakran szeretnél szeretkezni?",
    "Mit látnál szívesen egy szexnaptárban? Ha időnként ismétlődő pózok is szerepelnének benne, vagy inkább napról napra újabbakat szeretnél kipróbálni?",
];

$app->get('/test-cal', function ($request, $response, $args) use ($pdo) {

    require_once "../class/Calendar.php";
    $calendar = new Calendar($pdo);
    $calendar->setFrequency([1, 1]);
    $calendar->test();

    $pose_order1 = [
        "doggy style",
        "rear entry",
        "woman on top",
        "standing",
        "spooning",
        "blowjob",
        "oral sex",
        "kneeling",
        "69 sex position",
        "right angle",
        "cunnilingus",
        "lying down",
        "anal sex",
        "sitting",
        "reverse",
        "cowgirl",
        "man on top",
        "from behind",
        "face to face",
        "criss cross",
        "sideways",
    ];

    $pose_order2 = [
        "cunnilingus",
        "anal sex",
        "from behind",
        "rear entry",
        "face to face",
        "standing",
        "blowjob",
        "oral sex",
        "man on top",
        "kneeling",
        "right angle",
        "doggy style",
        "lying down",
        "sitting",
        "woman on top",
        "reverse",
        "cowgirl",
        "69 sex position",
        "criss cross",
        "sideways",
        "spooning"
    ];

    //shuffle($pose_order1);
    //shuffle($pose_order2);

    $calendar->setTestData($pose_order1, $pose_order2);

    $calendar->calculateCategories();

    //$calendar->getPositions();

    $response->getBody()->write(json_encode([]));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});


$app->get('/import', function ($request, $response, $args) use ($pdo) {

    set_time_limit(60 * 60);

    $row = 1;
    if (($handle = fopen("../_data/sexpositionsclub_file_2020-09-01_20_09.csv", "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

            if($row == 1) {
                $row++;
                continue;
            }

            $name = $data[1];
            $tags = $data[2];

            $tags_array = explode(", ", $tags);

            $insertStatement = $pdo->insert([
                "name_en" => $name,
                "img" => $name . ".png",
            ])
                ->into("sexpositions");
            $sexposition_id = $insertStatement->execute();

            foreach($tags_array as $tag) {

                $selectStatement = $pdo->select(['id'])
                    ->from('tags')
                    ->where(new \FaaPz\PDO\Clause\Conditional("name_en", "=", $tag));

                $stmt = $selectStatement->execute();
                $data_tag = $stmt->fetch();

                $insertStatement = $pdo->insert([
                    "position_id" => $sexposition_id,
                    "tag_id" => $data_tag["id"],
                ])
                    ->into("sexpositionstags");
                $insertStatement->execute();
            }




        }
        fclose($handle);
    }


    $files = scandir("gallery/sexpositionsclub_kepek");

    $images = [];
    foreach($files as $file) {
        if($file != ".." && $file != ".") {
            $images[] = $file;
        }
    }


    $data = array('data' => "imported");
    $payload = json_encode($data);

    $response->getBody()->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);

})->setName('root');


// Define named route
$app->get('/[{hash}]', function ($request, $response, $args) use ($pdo) {

    $questions = [
        [
            "question" => "Mennyire vagy elégedett a szexuális életetekkel?",
            "answers" => [
                "Alapvetően elégedett vagyok vele.",
                "Kicsit laposnak találom.",
                "Szexuális élet? Már nem is emlékszem, milyen az."
            ]
        ],
        [
            "question" => "Mit gondolsz, vajon a partnered elégedett az együttléteitekkel?",
            "answers" => [
                "(Majdnem) tökéletesen elégedett.",
                "Előfordulhat, hogy többre/másra vágyik.",
                "Régen volt, talán igaz sem volt…"
            ]
        ],
        [
            "question" => "Ha egy dolgot kérhetnél a párodtól, amit eddig sohasem mertél megemlíteni neki, mi lenne az?",
            "answers" => [
                "Egy extra csípős gyros.",
                "Egész éjszakás kényeztetés.",
                "Elpirulnék, ha leírnám, mindenesetre vannak ötleteim."
            ]
        ],
        [
            "question" => "Mit szólnál hozzá, ha a partnered meztelenül, egyetlen Mikulás-sapkában várna otthon egy fárasztó nap után?",
            "answers" => [
                "Megkérdezném, hogy fázik-e.",
                "Meghökkennék, de tetszene a gondolat.",
                "Télanyuként/Télapóként azonnal ráugranék."
            ]
        ],
        [
            "question" => "Megnéznétek együtt egy erotikus filmet?",
            "answers" => [
                "Ez eddig sem volt tabu.",
                "Mostanában sok a munkánk, de majd időt szakítunk rá.",
                "Naná, bármikor!"
            ]
        ],
        [
            "question" => "Milyen gyakran szeretnél szeretkezni?",
            "answers" => [
                "Heti 1-2 alkalommal.",
                "Heti 3-4 alkalommal.",
                "Heti 5-6 alkalommal.",
                "Hetente legalább hétszer."
            ]
        ],
        [
            "question" => "Súlyozd az alábbi pózokat annak megfelelően, hogy mennyire kedveled őket!",
            "answers" => [
                "doggy style",
                "from behind",
                "rear entry",
                "standing",
                "blowjob",
                "oral sex",
                "kneeling",
                "right angle",
                "cunnilingus",
                "lying down",
                "anal sex",
                "sitting",
                "woman on top",
                "reverse",
                "cowgirl",
                "man on top",
                "face to face",
                "69 sex position",
                "criss cross",
                "sideways",
                "spooning"
            ]
        ],
        [
            "question" => "Mit látnál szívesen egy szexnaptárban? Ha időnként ismétlődő pózok is szerepelnének benne, vagy inkább napról napra újabbakat szeretnél kipróbálni?",
            "answers" => [
                "Alkalmanként visszatérhetünk egy-egy pozitúrához.",
                "A változatosság gyönyörködtet."
            ]
        ],

    ];

    $questionnaire_id = "";
    if(!empty($args["hash"])) {
        $selectStatement = $pdo->select(['id'])
            ->from('questionnaire')
            ->where(new \FaaPz\PDO\Clause\Conditional("second_link", "=", filter_var($args["hash"], FILTER_SANITIZE_STRING)));

        $stmt = $selectStatement->execute();
        $data = $stmt->fetch();
        if(!empty($data)) {
            $questionnaire_id = $data["id"];
        }
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'index.html.twig', [
        'questions' => $questions,
        'questionnaire_id' => $questionnaire_id
    ]);
})->setName('root');

$app->post('/start', function ($request, $response, $args) use ($pdo) {

    $post = $request->getParsedBody();

    if(!empty($post["mode"])) {

        $random1 = $random2 = 0;

        $ok = false;
        do {
            $random1 = bin2hex(random_bytes(20));
            $random2 = bin2hex(random_bytes(20));

            $selectStatement = $pdo->select(['main_link'])
                ->from('questionnaire')
                ->where(
                    new \FaaPz\PDO\Clause\Grouping("OR",
                        new \FaaPz\PDO\Clause\Conditional("main_link", "=", $random1),
                        new \FaaPz\PDO\Clause\Conditional("second_link", "=", $random1)
                    )
                );

            $stmt = $selectStatement->execute();
            $data = $stmt->fetch();


            $selectStatement = $pdo->select(['second_link'])
                ->from('questionnaire')
                ->where(
                    new \FaaPz\PDO\Clause\Grouping("OR",
                        new \FaaPz\PDO\Clause\Conditional("main_link", "=", $random2),
                        new \FaaPz\PDO\Clause\Conditional("second_link", "=", $random2)
                    )
                );

            $stmt = $selectStatement->execute();
            $data2 = $stmt->fetch();

            if(empty($data) && empty($data2)) {
                $ok = true;
            }

        } while(!$ok);

        $insertStatement = $pdo->insert([
            "main_link" => $random1,
            "second_link" => $random2,
        ])
            ->into("questionnaire");
        $questionnaire_id = $insertStatement->execute();

        $array["questionnaire_id"] = $questionnaire_id;

        if($post["mode"] == "alone") {

            $array["second_link"] = $random2;
        }

        $result = 'success';

    } else {

        $array["error"] = "missing_mode";
        $result = 'error';
    }


    $data = array('data' => $array, 'result' => $result);
    $payload = json_encode($data);

    $response->getBody()->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);

});

$app->post('/save', function ($request, $response, $args) use ($pdo, $question_list) {

    //$post = $request->getParsedBody();
    $post = json_decode(file_get_contents("php://input"), true);

    $q_id = filter_var($post["questionnaire_id"], FILTER_SANITIZE_NUMBER_INT);

    if(!empty($post["answers_main"]) || (empty($post["answers_main"]) && !empty($post["answers_second"]))) {


        $answers_json = "";
        if(!empty($post["answer_main"])) {

            foreach($post["answer_main"] as $key => $answers) {

                if ($key == 7) {

                    $answers_temp[] = [
                        "position_order" => $answers["positions"]
                    ];

                } else {

                    $answers_temp[] = [
                        "question" => $question_list[$key],
                        "anwser" => $answers["value"]
                    ];
                }
            }


            $answers_json = json_encode($answers_temp);

            $insertStatement = $pdo->insert([
                "questionnaire_id" => $q_id,
                "data" => $answers_json,
                "main" => 1
            ])
                ->into("answers");
            $insertStatement->execute();
        }

        if(!empty($post["answer_partner"])) {

            foreach($post["answer_partner"] as $key => $answers) {

                if ($key == 7) {

                    $answers_temp2[] = [
                        "position_order" => $answers["positions"]
                    ];

                } else {

                    $answers_temp2[] = [
                        "question" => $question_list[$key],
                        "anwser" => $answers["value"]
                    ];
                }
            }


            $answers_json = json_encode($answers_temp2);

            $insertStatement = $pdo->insert([
                "questionnaire_id" => $q_id,
                "data" => $answers_json,
                "main" => 0
            ])
                ->into("answers");
            $insertStatement->execute();
        }



        /**
         * GENERATE
         */
        require_once "../class/Calendar.php";
        $calendar = new Calendar($pdo);


        $array["preview"] = "";
        $array["calendar"] = "";
        $result = 'success';

    } else {

        $array["error"] = "missing_answers";
        $result = 'error';
    }


    $data = array('data' => $array, 'result' => $result);
    $payload = json_encode($data);

    $response->getBody()->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);

});

// Run app
$app->run();

<?php 
//include_once "function.php";
include_once "Teploraspredelenie_class.php";

$material_09g2s=array(
    "1600"=>array(
        "teploemkost"=>963,
        "plotnost"=>6873,
        "teploprovodnost"=>35.2
    ),
    "1539"=>array(
        "teploemkost"=>908,
        "plotnost"=>6949,
        "teploprovodnost"=>35.2
    ),
    "1508"=>array(
        "teploemkost"=>903,
        "plotnost"=>6999,
        "teploprovodnost"=>35.2
    ),
    "1503"=>array(
        "teploemkost"=>897,
        "plotnost"=>7039,
        "teploprovodnost"=>35.1
    ),
    "1495"=>array(
        "teploemkost"=>893,
        "plotnost"=>7108,
        "teploprovodnost"=>34.8
    ),
    "1485"=>array(
        "teploemkost"=>889,
        "plotnost"=>7159,
        "teploprovodnost"=>34.6
    ),
    "1477"=>array(
        "teploemkost"=>885,
        "plotnost"=>7187,
        "teploprovodnost"=>34.5
    ),
    "1473"=>array(
        "teploemkost"=>872,
        "plotnost"=>7203,
        "teploprovodnost"=>34.4
    ),
    "1470"=>array(
        "teploemkost"=>867,
        "plotnost"=>7218,
        "teploprovodnost"=>34.4
    ),
    "1465"=>array(
        "teploemkost"=>865,
        "plotnost"=>7236,
        "teploprovodnost"=>34.2
    ),
    "1462"=>array(
        "teploemkost"=>861,
        "plotnost"=>7254,
        "teploprovodnost"=>34.1
    ),
    "1400"=>array(
        "teploemkost"=>853,
        "plotnost"=>7282,
        "teploprovodnost"=>33.4
    ),
    "1300"=>array(
        "teploemkost"=>823,
        "plotnost"=>7330,
        "teploprovodnost"=>31.2
    ),
    "1200"=>array(
        "teploemkost"=>786,
        "plotnost"=>7378,
        "teploprovodnost"=>29.6
    ),
    "1000"=>array(
        "teploemkost"=>678,
        "plotnost"=>7425,
        "teploprovodnost"=>26.1
    ),
    "800"=>array(
        "teploemkost"=>641,
        "plotnost"=>7517,
        "teploprovodnost"=>24.9
    ),
    "600"=>array(
        "teploemkost"=>869,
        "plotnost"=>7602,
        "teploprovodnost"=>26.1
    ),
    "400"=>array(
        "teploemkost"=>742,
        "plotnost"=>7690,
        "teploprovodnost"=>38.1
    ),
    "200"=>array(
        "teploemkost"=>631,
        "plotnost"=>7776,
        "teploprovodnost"=>46.8
    ),
    "25"=>array(
        "teploemkost"=>461,
        "plotnost"=>7850,
        "teploprovodnost"=>69.7
    )
);



//размеры пластины
$plate_param=array(
    "plateX"=>(int)$_POST["plateX"],
    "plateY"=>(int)$_POST["plateY"],
    "plateZ"=>(int)$_POST["plateZ"],
    "plateTemperature"=>(float)$_POST["plateTemperature"],
    "ambientTemperature"=>(float)$_POST["ambientTemperature"],
    "material"=>$material_09g2s,
);

$source_param=array(
    "sourceX"=>(float)$_POST["sourceX"],
    "sourceY"=>(int)$_POST["sourceY"],
    "sourceZ"=>(int)$_POST["sourceZ"],
    "sourceTemperature"=>(float)$_POST["sourceTemperature"],
    "sourceSpeed"=>(int)$_POST["sourceSpeed"],

);

$step_param=array(
    "timeStep"=>(float)$_POST["timeStep"]
);


 $test=new Teploraspredelenie($plate_param,$source_param,$step_param);

 $test->show_all_parametrs();
//  $test->count();

// var_dump($test->koef_heat_emission(612.12));
// var_dump($test->thermophysical_properties(1700));
// var_dump($res=$test->koef_heat_emission(20));
// $test->show_all_parametrs();
// $test->count_Raspr_Temperature();
// $test->show_Progon_koef();
// $test->show_koef_Matrix();
// $test->show_Raspredelenie();
// $test->csv_write();


?>
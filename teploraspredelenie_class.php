<?php

class Teploraspredelenie
{

    #параметры пластины
    private $plateX;
    private $plateY;
    private $plateZ;
    private $plateTemperature; //начальная температура пластины
    private $plateMaterial;

    private $ambient_temperature = 20;//температура окружающей среды

    # Шаги разбиения
    private $step;//шаг по координатам

    private $timeStep; //отслеживаемые шаги по времени

    # параметры источника
    private $sourceX;
    private $sourceY;
    private $sourceZ;
    private $sourceTemperature; //температура горелки
    private $sourceSpeed;   //скорость прохода горелки по пластине

    private $koef_surface_E = array(
        "1500" => 0.6,
        "1000" => 0.59,
        "730" => 0.58,
        "600" => 0.56,
        "575" => 0.55,
        "500" => 0.54,
        "475" => 0.53,
        "400" => 0.51,
        "375" => 0.5,
        "300" => 0.48,
        "275" => 0.47,
        "225" => 0.46,
        "200" => 0.45,
        "175" => 0.44,
        "100" => 0.4,
        "75" => 0.35,
        "0" => 0.2
    );

#________________________________________________________________________________________________  
#------------------------------------------------------------------------------------------------

//ПАРАМЕТРЫ КОТОРЫЕ СЧИТАЕМ
    # разбиение размера источника на ячейки в соответствии с разбивкой материала
    private $partitionSourceX; // разбиение размера источника по оси Х для сохранения пропорций
    private $partitionSourceY;
    private $partitionSourceZ;

    private $sourceShift; // изменение позиции источника за один расматирваемый момент
    private $partitionPlateX;//разбиение пластины на равные ячейки так что бы 1 ячейка=sourceShift
    private $partitionPlateY;
    private $partitionPlateZ;
    private $time;//общее время прохода горелки по пластине
    private $justMomentOfTime;//общее количество отслежвыаемых моментов времени

    private $nextStartPosition = 0;

    # матрица коэффицентов
    public $koef_Matrix = array(
        "A" => array(),
        "B" => array(),
        "C" => array(),
        "D" => array(),
    );

    # матрица прогоночных коэффицентов
    public $koef_Progon_Matrix = array(
        "Alpha" => array(),
        "Beta" => array(),
    );

    # результаты вычислений
    private $Raspredelenie_temperature = [[[]]];

#________________________________________________________________________________________________  
#------------------------------------------------------------------------------------------------

    function __construct($plate_proporites, $source_proporites, $step_proporites)
    {
        # параметры для пластины
        $this->plateX = $plate_proporites["plateX"];
        $this->plateY = $plate_proporites["plateY"];
        $this->plateZ = $plate_proporites["plateZ"];
        $this->plateTemperature = $plate_proporites["plateTemperature"];
        $this->plateMaterial = $plate_proporites["material"];
        $this->ambient_temperature = $plate_proporites["ambientTemperature"];

        # параметры для горелки
        $this->sourceX = $source_proporites["sourceX"];
        $this->sourceY = $source_proporites["sourceY"];
        $this->sourceZ = $source_proporites["sourceZ"];
        $this->sourceTemperature = $source_proporites["sourceTemperature"];
        $this->sourceSpeed = $source_proporites["sourceSpeed"];

        # параметры шагов
        $this->timeStep = $step_proporites["timeStep"];

        $this->time = round(($this->plateX - $this->sourceX) / $this->sourceSpeed, 1) + 0.1; //общее время прохода горелки в зависимости от скорости
        $this->justMomentOfTime = $this->time / $this->timeStep; //кол-во рассматриваемых временных промежутков

        $this->sourceShift = $this->sourceSpeed * $this->timeStep; //какое расстояние проходит горелка за одни момент времени
        $this->partitionPlateX = ceil($this->plateX / $this->sourceShift); //сеточное разбиение по оси X, одна ячейка = расстоянию прохода горелки за один момент времени
        $this->partitionPlateY = ceil($this->plateY / $this->sourceShift); //сеточное разбиение по оси Y, одна ячейка = расстоянию прохода горелки за один момент времени
        $this->partitionPlateZ = ceil($this->plateZ / $this->sourceShift);//сеточное разбиение по оси Y, одна ячейка = расстоянию прохода горелки за один момент времени
        $this->partitionSourceX = round($this->sourceX / $this->sourceShift); // сеточное разбиение горелки по оси X, для сохранение пропорций с размером пластины
        $this->partitionSourceY = round($this->sourceY / $this->sourceShift);// сеточное разбиение горелки по оси Y, для сохранение пропорций с размером пластины
        $this->partitionSourceZ = round($this->sourceZ / $this->sourceShift);// сеточное разбиение горелки по оси Z, для сохранение пропорций с размером пластины


        # генерация сеточной разбивки пластины и выставление начальных параметров в каждой ячейке
        $this->plateStartTemperature();
    }

# выставление начального сосотояния
    private function plateStartTemperature()
    {
        for ($z = 0; $z < $this->partitionPlateZ; $z++) {
            for ($x = 0; $x < $this->partitionPlateX; $x++) {
                for ($y = 0; $y < $this->partitionPlateY; $y++) {
                    $this->Raspredelenie_temperature[$z][$x][$y] = 20;
                }
            }
        }

        for ($z = 0; $z < $this->partitionSourceZ; $z++) {
            for ($x = 0; $x < $this->partitionSourceX; $x++) {
                for ($y = 0; $y < $this->partitionSourceY; $y++) {
                    $this->Raspredelenie_temperature[$z][$x][$y] = $this->sourceTemperature;
                }
            }
        }
    }

# Фукция подсчёта теплораспределения во все моменты времени
    public function count(){
        $this->nextStartPosition=0;
        for ($t = 0; $t < $this->justMomentOfTime; $t++) {
           $this->go_X_count_source_front_area($this->nextStartPosition);
            $this->show_Raspredelenie($t);
            $this->shift_temperature();
            $this->nextStartPosition++;
        }
    }

    private function shift_temperature(){
            for ($z=0; $z<$this->partitionPlateZ; $z++){
                for ($x=$this->partitionPlateX; $x>=0; $x--){
                    for ($y=0; $y<$this->partitionPlateY; $y++)
                        if ($x==0){
                         $this->Raspredelenie_temperature[$z][$x][$y]=$this->plateTemperature;
                        }else {
                            $this->Raspredelenie_temperature[$z][$x][$y] = $this->Raspredelenie_temperature[$z][$x - 1][$y];
                        }
                }
            }
    }

    private function go_X_count_source_front_area($startPosition){

        $frontSourceFace=($startPosition+$this->partitionSourceX)-1; //передная граница горелки
        for($z=0; $z<$this->partitionPlateZ-1; $z++){
            for($y=0; $y<$this->partitionPlateY-1; $y++){
                for($x=$this->partitionPlateX-1; $x>$frontSourceFace; $x--){
                    $this->A($z,$x,$y,"X",$frontSourceFace);
                    $this->B($z,$x,$y,"X", $frontSourceFace);
                    $this->C($z,$x,$y,"X",$frontSourceFace);
                    $this->D($z,$x,$y,"X",$frontSourceFace);
                    if($x>$frontSourceFace+1){
                        $this->Alpha($x);
                        $this->Beta($x);
                    }
                }
                $N=$frontSourceFace+1;

                $this->Raspredelenie_temperature[$z][$N][$y]=-round(($this->koef_Matrix["D"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N+1])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N+1]),2);

                //расчёт обратным шагом по всей оси x
                for($x=$N+1;$x<$this->partitionPlateX;$x++){
                    $this->Raspredelenie_temperature[$z][$x][$y]=round($this->koef_Progon_Matrix["Alpha"][$x]*$this->Raspredelenie_temperature[$z][$x-1][$y]+$this->koef_Progon_Matrix["Beta"][$x],2);
                }
//                echo"<pre>";
//                var_dump($this->koef_Matrix);
//                var_dump($this->koef_Progon_Matrix);
//                echo"</pre>";
                $this->koef_Progon_Matrix=array(
                    "Alpha"=>array(),
                    "Beta"=>array(),
                );
                $this->koef_Matrix=array(
                    "A"=>array(),
                    "B"=>array(),
                    "C"=>array(),
                    "D"=>array(),
                );
            }
        }
    }

    public function thermophysical_properties( $temperature){
        $index=array(25,200,400,600,800,1000,1200,1300,1400,1462,1465,1470,1473,1477,1485,1495,1503,1508,1539,1600);
        for($i=0; $i<count($index); $i++){
            if ($temperature>$index[19]){
                $index_proporites=(string)$index[19];
            }else if ($temperature<$index[$i] ){
                if ($i>0){
                    $granitsa=($index[$i]-$index[$i-1])/2;
                    $ostatok=$temperature-$index[$i-1];
                    if ($ostatok>$granitsa) $index_proporites=(string)$index[$i];
                    else $index_proporites=(string)$index[$i-1];
                } else $index_proporites=(string)$index[$i];
                break;
            }

        }
        //echo $this->plateMaterial[$index_proporites]["teploprovodnost"];
        return $this->plateMaterial[$index_proporites];

    }

    private function koef_heat_emission($temperature){
        $emission;
        $index_prop;

        $index=array(0,75,100,175,200,225,275,300,375,400,475,500,575,600,730,1000,1500);

        for($i=0; $i<count($index); $i++){
            if ($temperature<$index[$i]){
                if($i>0){
                    $index_prop=(string)$index[$i-1];
                }
                else {
                    $index_prop=(string)$index[$i];
                }
            }else if ($temperature>=$index[16]){
                $index_prop=(string)$index[$i];
            }
        }
        $emission=$this->koef_surface_E[$index_prop];
        $result_koef=0.0024*$emission*pow($temperature,1.61);
        return $result_koef;
    }


    private function A($z,$x,$y,$orientation,$frontSourceFace){
        $temperature_N=$this->Raspredelenie_temperature[$z][$x][$y];
        $parametric_N=$this->thermophysical_properties($temperature_N);
        $L_n=$parametric_N["teploprovodnost"];
        switch ($orientation){
            case "X":
                if ($x==$frontSourceFace+1) $this->koef_Matrix["A"][$x]=0;
                else{
                    $temperature_Next=$this->Raspredelenie_temperature[$z][$x-1][$y];
                    $parametric_Next=$this->thermophysical_properties($temperature_Next);
                    $L_Next=$parametric_Next["teploprovodnost"];
                    $this->koef_Matrix["A"][$x]=$L_Next+$L_n;
                }
                break;
        }
    }

    private function B($z,$x,$y,$orientation,$frontSourceFace){
        $temperature_N=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x][$y]);
        $temperature_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x+1][$y]);
        $temperature_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x-1][$y]);

        $teploemkost_N=$temperature_N["teploemkost"];
        $plotnost_N=$temperature_N["plotnost"];
        $L_n=$temperature_N["teploprovodnost"];

        $L_previos=$temperature_previos["teploprovodnost"];
        $L_next=$temperature_next["teploprovodnost"];

        $prom_count=2*$teploemkost_N*$plotnost_N*pow($this->sourceShift/1000,2)/$this->timeStep;

        switch ($orientation){
            case "X":
                switch ($x){
                    case $this->partitionPlateX-1:
                        $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n);
                        break;

                    case $frontSourceFace+1:
                        $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n);
                        break;

                    default:
                        $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n)-($L_previos+$L_n);
                        break;
                }
                break;
        }
    }

    private function C($z,$x,$y,$orientation,$frontSourceFace){
        $temperature_N=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x][$y]);
        $temperature_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x+1][$y]);

        $L_n=$temperature_N["teploprovodnost"];
        $L_previos=$temperature_previos["teploprovodnost"];

    switch ($orientation){
        case "X":
            switch ($x){
                case $this->partitionPlateX-1:
                    $this->koef_Matrix["C"][$x]=0;
                    break;

                case $frontSourceFace+1:
                    $this->koef_Matrix["C"][$x]=0;
                    break;

                default:
                    $this->koef_Matrix["C"][$x]=$L_previos+$L_n;
                    break;
            }
            break;
    }
    }

    private function D($z,$x,$y,$orientation,$frontSourceFace){
        $temperature_N=$this->Raspredelenie_temperature[$z][$x][$y];
        $temperature_previos=$this->Raspredelenie_temperature[$z][$x+1][$y];
        $temperature_next=$this->Raspredelenie_temperature[$z][$x-1][$y];

        $proporites_N=$this->thermophysical_properties($temperature_N);
        $proporites_previos=$this->thermophysical_properties($temperature_previos);
        $proporites_next=$this->thermophysical_properties($temperature_next);

        $teploemkost_N=$proporites_N["teploemkost"];
        $plotnost_N=$proporites_N["plotnost"];
        $L_n=$proporites_N["teploprovodnost"];

        $L_previos=$proporites_previos["teploprovodnost"];
        $L_next=$proporites_next["teploprovodnost"];

        $prom_count=2*$teploemkost_N*$plotnost_N*pow($this->sourceShift/1000,2)/$this->timeStep;

    switch ($orientation){
        case "X":
            switch ($x){
                case $frontSourceFace+1:
                    $this->koef_Matrix["D"][$x]=$prom_count*$temperature_N+($L_n+$L_next)*$temperature_next;
                    break;

                default:
                    $this->koef_Matrix["D"][$x]=$prom_count*$temperature_N;
                    break;
            }
            break;
    }

    }

    private function Alpha($index){
        if($index<$this->partitionPlateX-1){
            $this->koef_Progon_Matrix["Alpha"][$index]=-$this->koef_Matrix["A"][$index]/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index+1]);
        }else{
            $this->koef_Progon_Matrix["Alpha"][$index]=-$this->koef_Matrix["A"][$index]/$this->koef_Matrix["B"][$index];
        }
    }

    private function Beta($index){
        if($index<$this->partitionPlateX-1){
            $this->koef_Progon_Matrix["Beta"][$index]=-($this->koef_Matrix["D"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Beta"][$index+1])/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index+1]);
        }else{
            $this->koef_Progon_Matrix["Beta"][$index]=-$this->koef_Matrix["D"][$index]/$this->koef_Matrix["B"][$index];
        }
    }

    #+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++==

    private function A_back($t,$z,$x,$y){
        $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
        if ($x==0) $this->koef_Matrix["A"][$x]=0;
        else{
            $L_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x-1][$y]);
            $this->koef_Matrix["A"][$x]=-($L_next["teploprovodnost"]+$L_n["teploprovodnost"]);
        }
    }

    private function B_back($t,$z,$x,$y){
        $prom_vicheslenie_2=(-2*$teploemkostN*$plotnostN*pow($this->sourceShift/1000,2))/$this->timeStep;

        $temperatureN=$this->Raspredelenie_temperature[$t-1][$z][$x][$y];
        $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x-1][$y]);
        $parametricNext=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x+1][$y]);

        $L_next=$parametricNext["teploprovodnost"];
        $L_previos=$parametric_previos["teploprovodnost"];

        if ($z==0){
            switch ($x){
                case $this->nextStartPosition-1:
                    $this->koef_Matrix["B"][$x]=-$prom_vicheslenie_2-($L_n+$L_previos)-2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000);
                    break;

                case 0:
                    $this->koef_Matrix["B"][$x]=-$prom_vicheslenie_2-($L_n+$L_next)-2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000);
                    break;

                default:
                    $this->koef_Matrix["B"][$x]=-$prom_vicheslenie_2-($L_n+$L_next)-($L_n+$L_previos)-2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000);
                    break;
            }
        }else{
            switch ($x){
                case $this->nextStartPosition-1:
                    $this->koef_Matrix["B"][$x]=-$prom_vicheslenie_2-($L_n+$L_previos);
                    break;

                case 0:
                    $this->koef_Matrix["B"][$x]=-$prom_vicheslenie_2-($L_n+$L_next);
                    break;

                default:
                    $this->koef_Matrix["B"][$x]=-$prom_vicheslenie_2-($L_n+$L_next)-($L_n+$L_previos);
                    break;
            }

        }
    }

    private function C_back($t,$z,$x,$y){
        $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
        $L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x+1][$y]);
        $this->koef_Matrix["C"][$x]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"];
    }

    private function D_back($t,$z,$x,$y){
        $temperatureN=$this->Raspredelenie_temperature[$t-1][$z][$x][$y];
        $temperaturePrevios=$this->Raspredelenie_temperature[$t-1][$z][$x+1][$y];

        $parametricN=$this->thermophysical_properties($temperatureN);
        $parametricSol=$this->thermophysical_properties($this->sourceTemperature);
        $parametricPrevios=$this->thermophysical_properties($temperaturePrevios);

        $L_sol=$parametricSol["teploprovodnost"];
        $L_previos=$parametricPrevios["teploprovodnost"];

        $plotnostN=$parametricN["plotnost"];//this is Ro proporites
        $teploemkostN=$parametricN["teploemkost"];// this is C proporites
        $L_n=$parametricN["teploprovodnost"];

        $prom_vicheslenie_2=(2*$teploemkostN*$plotnostN*pow($this->sourceShift/1000,2))/$this->timeStep;
        if ($z==0){
            switch ($x){
                case $this->nextStartPosition-1:
                    $this->koef_Matrix["D"][$x]=$prom_vicheslenie_2*$temperatureN-($L_n+$L_sol)*$this->sourceTemperature-2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000)*$this->ambient_temperature;
                    break;

                default:
                    $this->koef_Matrix["D"][$x]=$prom_vicheslenie_2*$temperatureN-2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000)*$this->ambient_temperature;
                    break;
            }
        }else{
            switch ($x){
                case $this->nextStartPosition-1:
                    $this->koef_Matrix["D"][$x]=$prom_vicheslenie_2*$temperatureN-($L_n+$L_sol)*$this->sourceTemperature;
                    break;

                default:
                    $this->koef_Matrix["D"][$x]=$prom_vicheslenie_2*$temperatureN;
                    break;
            }
        }

    }

    private function Alpha_back($index){
        if ($index!=$this->nextStartPosition-1){
            $this->koef_Progon_Matrix["Alpha"][$index]=(-$this->koef_Matrix["A"][$index])/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index+1]);
        }else{
            $this->koef_Progon_Matrix["Alpha"][$index]=-$this->koef_Matrix["A"][$index]/($this->koef_Matrix["B"][$index]);
        }
    }

    private function Beta_back($index){
        if ($index!=$this->nextStartPosition-1){
            $this->koef_Progon_Matrix["Beta"][$index]=($this->koef_Matrix["D"][$index]-$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Beta"][$index+1])/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index+1]);
        }else {
            $this->koef_Progon_Matrix["Beta"][$index]=$this->koef_Matrix["D"][$index]/$this->koef_Matrix["B"][$index];
        }
    }

    ##################################################################


    public  function show_Raspredelenie($t){
        $table.="Момент времени: {$t}<br>";
        for($z=0;$z<$this->partitionPlateZ;$z++){
            $table.="Слой( Z индекс): {$z}<br>";
            $table.="<table border='1' cellpadding='5'>";
            for($x=0;$x<$this->partitionPlateX;$x++){
                $x1=$x/10;
                $table.="<tr><th width='20px'>{$x1}</th>";
                for($y=0;$y<$this->partitionPlateY;$y++){
                    $table.="<th >{$this->Raspredelenie_temperature[$z][$x][$y]}</th>";
                }
                $table.="</tr>";
            }
            $table.="<table>";
        }
        echo $table;
    }

    public function show_all_parametrs(){
        echo "размеры пластины:<br>";
        echo "X: ".$this->plateX."<br>";
        echo "Y: ".$this->plateY."<br>";
        echo "Z: ".$this->plateZ."<br>";
        echo "температура пластины: ".$this->plateTemperature."<br>";

        //параметры для горелки
        echo "Размеры горелки:<br>";
        echo "X: ".$this->sourceX."<br>";
        echo "Y: ".$this->sourceY."<br>";
        echo "Z: ".$this->sourceZ."<br>";
        echo "Температура горелки: ".$this->sourceTemperature."<br>";
        echo "Скорость горелки: ".$this->sourceSpeed."мм/сек. <br>";
        echo "Время прохода горелки по пластине: ".$this->time."(".($this->plateX-$this->sourceX)/$this->sourceSpeed.")сек.<br>";
        echo "Перемещение горелки за один момент времени на: ".$this->sourceShift."мм<br>";

        //параметры шагов
        echo "Кол-во ячеек по оси Х пластины: ".$this->partitionPlateX."<br>";
        echo "Кол-во ячеек по оси Х горелки: ".$this->partitionSourceX."(".$this->sourceX/$this->sourceShift.")<br>";
        echo "Временной шаг: ".$this->timeStep."<br>";
        echo "Общее кол-во расматреваемых временных промежутков: ".$this->justMomentOfTime."<br>";

        echo "Температура окружающей среды: ".$this->ambient_temperature."<br>";

    }

    public  function show_koef_Matrix(){
        $table="<table><tr><th>№</th><th>A</th><th>B</th><th>C</th><th>D</th></tr>";
        for($i=0;$i<count($this->koef_Matrix["A"]);$i++){
            $table.="<tr><th>{$i}</th><td>{$this->koef_Matrix["A"][$i]}</td><td>{$this->koef_Matrix["B"][$i]}</td><td>{$this->koef_Matrix["C"][$i]}</td><td>{$this->koef_Matrix["D"][$i]}</td></tr>";
        }
        $table.="</table>";
        echo $table;

    }

    public function show_Progon_koef(){
        $table="<table><tr><th>№</th><th>Alpha</th><th>Beta</th></tr>";
        for($i=0;$i<count($this->koef_Progon_Matrix["Alpha"]);$i++){
            $table.="<tr><th>{$i}</th><td>{$this->koef_Progon_Matrix["Alpha"][$i]}</td><td>{$this->koef_Progon_Matrix["Beta"][$i]}</td></td>";
        }
        $table.="</table>";
        echo $table;

    }

}
?>
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

    private $settings;

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
        $date_now=date('Y-m-d H:i:s');
        $date_now=str_replace(":","-",$date_now);
        $dir_name="result/{$date_now}";
        
        if(mkdir("result/{$date_now}",0700)){
            $file_open=fopen("result/{$date_now}/settings.txt",'w');
            fwrite($file_open,$this->settings);
            fclose($file_open);
            echo "Папка создана с наименованием: '{$date_now}'<br>";
        }else die();

        $this->nextStartPosition=0;
        for ($t = 0; $t < $this->justMomentOfTime; $t++) {
           if ($t<$this->justMomentOfTime-1)$this->go_X_count_source_front_area($this->nextStartPosition);
            if ($t>0) $this->go_X_count_source_back_area($this->nextStartPosition);
            $this->go_Y_count();
            $this->go_z_count();
            $this->csv_write($t,$dir_name);
            //$this->show_Raspredelenie($t);
            $this->shift_temperature();
            $this->nextStartPosition++;
        }
    }

    private function go_X_count_source_front_area($startPosition){

        $frontSourceFace=($startPosition+$this->partitionSourceX)-1; //передная граница горелки
        for($z=0; $z<$this->partitionSourceZ; $z++){
            for($y=0; $y<$this->partitionSourceY; $y++){
                for($x=$this->partitionPlateX-1; $x>$frontSourceFace; $x--){
                    $this->A($z,$x,$y,"X",$frontSourceFace);
                    $this->B($z,$x,$y,"X", $frontSourceFace);
                    $this->C($z,$x,$y,"X",$frontSourceFace);
                    $this->D($z,$x,$y,"X",$frontSourceFace);
                    if($x>$frontSourceFace+1){
                        $this->Alpha($x,$this->partitionPlateX);
                        $this->Beta($x,$this->partitionPlateX);
                    }
                }
                $N=$frontSourceFace+1;

                $this->Raspredelenie_temperature[$z][$N][$y]=-round(($this->koef_Matrix["D"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N+1])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N+1]),2);

                //расчёт обратным шагом по всей оси x
                for($x=$N+1;$x<$this->partitionPlateX;$x++){
                    $this->Raspredelenie_temperature[$z][$x][$y]=round($this->koef_Progon_Matrix["Alpha"][$x]*$this->Raspredelenie_temperature[$z][$x-1][$y]+$this->koef_Progon_Matrix["Beta"][$x],2);
                }

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

    private function go_X_count_source_back_area($startPosition){
        $startPositionLocal=$startPosition;

        for($z=0; $z<$this->partitionSourceZ; $z++){
            for($y=0; $y<$this->partitionSourceY; $y++){
                for($x=0; $x<$startPositionLocal; $x++){
                    $this->A_back($z,$x,$y,$startPositionLocal-1);
                    $this->B_back($z,$x,$y,$startPositionLocal-1);
                    $this->C_back($z,$x,$y,$startPositionLocal-1);
                    $this->D_back($z,$x,$y,$startPositionLocal-1);
                    if($x<$startPositionLocal-1){
                        $this->Alpha_back($x);
                        $this->Beta_back($x);
                    }
                }
                $N=$startPositionLocal-1;

                $this->Raspredelenie_temperature[$z][$N][$y]=-round(($this->koef_Matrix["D"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]),2);

                //расчёт обратным шагом по всей оси x
                for($x=$N-1;$x>=0;$x--){
                    $this->Raspredelenie_temperature[$z][$x][$y]=round($this->koef_Progon_Matrix["Alpha"][$x]*$this->Raspredelenie_temperature[$z][$x+1][$y]+$this->koef_Progon_Matrix["Beta"][$x],2);
                }


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

    private function go_Y_count(){
        for($z=0; $z<$this->partitionSourceZ; $z++){
            for($x=0; $x<$this->partitionPlateX; $x++){
                for($y=$this->partitionPlateY-1; $y>=$this->partitionSourceY; $y--){
                    $this->A($z,$x,$y,"Y");
                    $this->B($z,$x,$y,"Y");
                    $this->C($z,$x,$y,"Y");
                    $this->D($z,$x,$y,"Y");
                    if($y>$this->partitionSourceY){
                        $this->Alpha($y,$this->partitionPlateY);
                        $this->Beta($y,$this->partitionPlateY);
                    }
                }


                $N=$this->partitionSourceY;

                $this->Raspredelenie_temperature[$z][$x][$N]=-round(($this->koef_Matrix["D"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N+1])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N+1]),2);

                //расчёт обратным шагом по всей оси x
                for($y=$N+1;$y<$this->partitionPlateY;$y++){
                    $this->Raspredelenie_temperature[$z][$x][$y]=round($this->koef_Progon_Matrix["Alpha"][$y]*$this->Raspredelenie_temperature[$z][$x][$y-1]+$this->koef_Progon_Matrix["Beta"][$y],2);
                }

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

    private function go_z_count(){
        for($y=0; $y<$this->partitionPlateY; $y++){
            for($x=0; $x<$this->partitionPlateX; $x++){
                for($z=$this->partitionPlateZ-1; $z>=$this->partitionSourceZ; $z--){

                    $this->A($z,$x,$y,"Z");
                    $this->B($z,$x,$y,"Z");
                    $this->C($z,$x,$y,"Z");
                    $this->D($z,$x,$y,"Z");
                    if($z>$this->partitionSourceZ){
                        $this->Alpha($z,$this->partitionPlateZ);
                        $this->Beta($z,$this->partitionPlateZ);
                    }
                }


                $N=(int)$this->partitionSourceZ;

                $this->Raspredelenie_temperature[$N][$x][$y]=-round(($this->koef_Matrix["D"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N+1])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N+1]),2);

                //расчёт обратным шагом по всей оси x
                for($z=$N+1;$z<$this->partitionPlateZ;$z++){
                    $this->Raspredelenie_temperature[$z][$x][$y]=round($this->koef_Progon_Matrix["Alpha"][$z]*$this->Raspredelenie_temperature[$z-1][$x][$y]+$this->koef_Progon_Matrix["Beta"][$z],2);
                }

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

    private function A($z,$x,$y,$orientation,$frontSourceFace=0){
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

            case "Y":
                if ($y==$this->partitionSourceY) $this->koef_Matrix["A"][$y]=0;
                else{
                    $temperature_Next=$this->Raspredelenie_temperature[$z][$x][$y-1];
                    $parametric_Next=$this->thermophysical_properties($temperature_Next);
                    $L_Next=$parametric_Next["teploprovodnost"];
                    $this->koef_Matrix["A"][$y]=$L_Next+$L_n;
                }
                break;

            case "Z":
                if ($z==$this->partitionSourceZ) $this->koef_Matrix["A"][$z]=0;
                else{
                    $temperature_Next=$this->Raspredelenie_temperature[$z-1][$x][$y];
                    $parametric_Next=$this->thermophysical_properties($temperature_Next);
                    $L_Next=$parametric_Next["teploprovodnost"];
                    $this->koef_Matrix["A"][$z]=$L_Next+$L_n;
                }
                break;
        }
    }

    private function B($z,$x,$y,$orientation,$frontSourceFace=0){
        $temperature_N=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x][$y]);

        $teploemkost_N=$temperature_N["teploemkost"];
        $plotnost_N=$temperature_N["plotnost"];
        $L_n=$temperature_N["teploprovodnost"];

        $prom_count=2*$teploemkost_N*$plotnost_N*pow($this->sourceShift/1000,2)/$this->timeStep;

        switch ($orientation){
            case "X":
                $temperature_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x+1][$y]);
                $temperature_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x-1][$y]);

                $L_previos=$temperature_previos["teploprovodnost"];
                $L_next=$temperature_next["teploprovodnost"];
                if ($z==0){
                    switch ($x){
                        case $this->partitionPlateX-1:
                            $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n)-2*$this->koef_heat_emission($temperature_N)*($this->sourceShift/1000);
                            break;

                        case $frontSourceFace+1:
                            $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n)-2*$this->koef_heat_emission($temperature_N)*($this->sourceShift/1000);
                            break;

                        default:
                            $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n)-($L_previos+$L_n)-2*$this->koef_heat_emission($temperature_N)*($this->sourceShift/1000);
                            break;
                     }
                    }else{
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
                }

                break;

            case "Y":
                $temperature_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x][$y+1]);
                $temperature_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x][$y-1]);
                //var_dump($this->Raspredelenie_temperature[$z][$x][$y-1]);
                $L_previos=$temperature_previos["teploprovodnost"];
                $L_next=$temperature_next["teploprovodnost"];
                if ($z==0){
                    switch ($y){
                        case $this->partitionPlateY-1:
                            $this->koef_Matrix["B"][$y]=-$prom_count-($L_next+$L_n)-2*$this->koef_heat_emission($temperature_N)*($this->sourceShift/1000);
                            break;

                        case $this->partitionSourceY:
                            $this->koef_Matrix["B"][$y]=-$prom_count-($L_next+$L_n)-2*$this->koef_heat_emission($temperature_N)*($this->sourceShift/1000);
                            break;

                        default:
                            $this->koef_Matrix["B"][$y]=-$prom_count-($L_next+$L_n)-($L_previos+$L_n)-2*$this->koef_heat_emission($temperature_N)*($this->sourceShift/1000);
                            break;
                    }
                }else{
                    switch ($y){
                        case $this->partitionPlateY-1:
                            $this->koef_Matrix["B"][$y]=-$prom_count-($L_next+$L_n);
                            break;

                        case $this->partitionSourceY:
                            $this->koef_Matrix["B"][$y]=-$prom_count-($L_next+$L_n);
                            break;

                        default:
                            $this->koef_Matrix["B"][$y]=-$prom_count-($L_next+$L_n)-($L_previos+$L_n);
                            break;
                    }
                }
                break;

            case "Z":
                $temperature_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z+1][$x][$y]);
                $temperature_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$z-1][$x][$y]);
                    //                var_dump($this->Raspredelenie_temperature[$z][$x][$y-1]);
                $L_previos=$temperature_previos["teploprovodnost"];
                $L_next=$temperature_next["teploprovodnost"];

                switch ($z){
                    case $this->partitionPlateZ-1:
                        $this->koef_Matrix["B"][$z]=-$prom_count-($L_next+$L_n)-2*$this->koef_heat_emission($temperature_N)*($this->sourceShift/1000);
                        break;

                    case $this->partitionSourceZ:
                        $this->koef_Matrix["B"][$z]=-$prom_count-($L_next+$L_n);
                        break;

                    default:
                        $this->koef_Matrix["B"][$z]=-$prom_count-($L_next+$L_n)-($L_previos+$L_n);
                        break;
                }
                break;
        }
    }

    private function C($z,$x,$y,$orientation,$frontSourceFace=0){
        $temperature_N=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x][$y]);
        $L_n=$temperature_N["teploprovodnost"];

            switch ($orientation){
                case "X":
                    $temperature_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x+1][$y]);
                    $L_previos=$temperature_previos["teploprovodnost"];
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

                case "Y":
                    $temperature_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x][$y+1]);
                    $L_previos=$temperature_previos["teploprovodnost"];
                    switch ($y){
                        case $this->partitionPlateY-1:
                            $this->koef_Matrix["C"][$y]=0;
                            break;

                        case $this->partitionSourceY:
                            $this->koef_Matrix["C"][$y]=0;
                            break;

                        default:
                            $this->koef_Matrix["C"][$y]=$L_previos+$L_n;
                            break;
                    }
                    break;

                case "Z":
                    $temperature_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z+1][$x][$y]);
                    $L_previos=$temperature_previos["teploprovodnost"];
                    switch ($z){
                        case $this->partitionPlateZ-1:
                            $this->koef_Matrix["C"][$z]=0;
                            break;

                        case $this->partitionSourceZ:
                            $this->koef_Matrix["C"][$z]=0;
                            break;

                        default:
                            $this->koef_Matrix["C"][$z]=$L_previos+$L_n;
                            break;
                    }
                    break;
            }
    }

    private function D($z,$x,$y,$orientation,$frontSourceFace=0){
        $temperature_N=$this->Raspredelenie_temperature[$z][$x][$y];

        $proporites_N=$this->thermophysical_properties($temperature_N);

        $teploemkost_N=$proporites_N["teploemkost"];
        $plotnost_N=$proporites_N["plotnost"];
        $L_n=$proporites_N["teploprovodnost"];

        $prom_count=2*$teploemkost_N*$plotnost_N*pow($this->sourceShift/1000,2)/$this->timeStep;

        switch ($orientation){
            case "X":
                $temperature_previos=$this->Raspredelenie_temperature[$z][$x+1][$y];
                $temperature_next=$this->Raspredelenie_temperature[$z][$x-1][$y];

                $proporites_previos=$this->thermophysical_properties($temperature_previos);
                $proporites_next=$this->thermophysical_properties($temperature_next);

                $L_previos=$proporites_previos["teploprovodnost"];
                $L_next=$proporites_next["teploprovodnost"];
                if ($z==0){
                    switch ($x){
                        case $frontSourceFace+1:
                            $this->koef_Matrix["D"][$x]=$prom_count*$temperature_N+($L_n+$L_next)*$temperature_next-2*$this->koef_heat_emission($temperature_N)*$this->ambient_temperature*($this->sourceShift/1000);
                            break;

                        default:
                            $this->koef_Matrix["D"][$x]=$prom_count*$temperature_N-2*$this->koef_heat_emission($temperature_N)*$this->ambient_temperature*($this->sourceShift/1000);
                            break;
                    }
                }else{
                    switch ($x){
                        case $frontSourceFace+1:
                            $this->koef_Matrix["D"][$x]=$prom_count*$temperature_N+($L_n+$L_next)*$temperature_next;
                            break;

                        default:
                            $this->koef_Matrix["D"][$x]=$prom_count*$temperature_N;
                            break;
                    }
                }
                break;

            case "Y":
                $temperature_previos=$this->Raspredelenie_temperature[$z][$x][$y+1];
                $temperature_next=$this->Raspredelenie_temperature[$z][$x][$y-1];

                $proporites_previos=$this->thermophysical_properties($temperature_previos);
                $proporites_next=$this->thermophysical_properties($temperature_next);

                $L_previos=$proporites_previos["teploprovodnost"];
                $L_next=$proporites_next["teploprovodnost"];
                if ($z==0){
                    switch ($y){
                        case $this->partitionSourceY:
                            $this->koef_Matrix["D"][$y]=$prom_count*$temperature_N+($L_n+$L_next)*$temperature_next-2*$this->koef_heat_emission($temperature_N)*$this->ambient_temperature*($this->sourceShift/1000);
                            break;

                        default:
                            $this->koef_Matrix["D"][$y]=$prom_count*$temperature_N-2*$this->koef_heat_emission($temperature_N)*$this->ambient_temperature*($this->sourceShift/1000);
                            break;
                    }
                }else{
                    switch ($y){
                        case $this->partitionSourceY:
                            $this->koef_Matrix["D"][$y]=$prom_count*$temperature_N+($L_n+$L_next)*$temperature_next;
                            break;

                        default:
                            $this->koef_Matrix["D"][$y]=$prom_count*$temperature_N;
                            break;
                    }
                }
                break;

            case "Z":
                $temperature_previos=$this->Raspredelenie_temperature[$z+1][$x][$y];
                $temperature_next=$this->Raspredelenie_temperature[$z-1][$x][$y];

                $proporites_previos=$this->thermophysical_properties($temperature_previos);
                $proporites_next=$this->thermophysical_properties($temperature_next);

                $L_previos=$proporites_previos["teploprovodnost"];
                $L_next=$proporites_next["teploprovodnost"];

                switch ($z){
                    case $this->partitionSourceZ:
                        $this->koef_Matrix["D"][$z]=$prom_count*$temperature_N+($L_n+$L_next)*$temperature_next;
                        break;

                    case $this->partitionPlateZ-1:
                        $this->koef_Matrix["D"][$z]=$prom_count*$temperature_N-2*$this->koef_heat_emission($temperature_N)*$this->ambient_temperature*($this->sourceShift/1000);
                        break;

                    default:
                        $this->koef_Matrix["D"][$z]=$prom_count*$temperature_N;
                        break;
                }
                break;
        }

    }

    private function Alpha($index, $plate_boundary){
        if($index<$plate_boundary-1){
            $this->koef_Progon_Matrix["Alpha"][$index]=-$this->koef_Matrix["A"][$index]/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index+1]);
        }else{
            $this->koef_Progon_Matrix["Alpha"][$index]=-$this->koef_Matrix["A"][$index]/$this->koef_Matrix["B"][$index];
        }
    }

    private function Beta($index, $plate_boundary){
        if($index<$plate_boundary-1){
            $this->koef_Progon_Matrix["Beta"][$index]=-($this->koef_Matrix["D"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Beta"][$index+1])/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index+1]);
        }else{
            $this->koef_Progon_Matrix["Beta"][$index]=-$this->koef_Matrix["D"][$index]/$this->koef_Matrix["B"][$index];
        }
    }

    #+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++==

    private function A_back($z,$x,$y,$backSourceFace){
        $temperature_N=$this->Raspredelenie_temperature[$z][$x][$y];
        $parametric_N=$this->thermophysical_properties($temperature_N);
        $L_n=$parametric_N["teploprovodnost"];
        if ($x==$backSourceFace) {
            $this->koef_Matrix["A"][$x] = 0;
        }else{
            $temperature_Next=$this->Raspredelenie_temperature[$z][$x+1][$y];
            $parametric_Next=$this->thermophysical_properties($temperature_Next);
            $L_next=$parametric_Next["teploprovodnost"];
            $this->koef_Matrix["A"][$x]=$L_next+$L_n;
        }
    }

    private function B_back($z,$x,$y, $backSourceFace){

        $temperatureN=$this->Raspredelenie_temperature[$z][$x][$y];

        $parametric_N=$this->thermophysical_properties($temperatureN);
        $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x-1][$y]);
        $parametric_Next=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x+1][$y]);

        $teploemkost_N=$parametric_N["teploemkost"];
        $plotnost_N=$parametric_N["plotnost"];
        $L_n=$parametric_N["teploprovodnost"];

        $L_next=$parametric_Next["teploprovodnost"];
        $L_previos=$parametric_previos["teploprovodnost"];

        $prom_count=2*$teploemkost_N*$plotnost_N*pow($this->sourceShift/1000,2)/$this->timeStep;
             if ($z==0){
                 switch ($x){
                     case $backSourceFace:
                         $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n)-2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000);
                         break;

                     default:
                         if ($x==0){
                             $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n)-2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000);
                         }else{
                             $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n)-($L_previos+$L_n)-2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000);
                         }
                         break;
                 }
             }else{
                 switch ($x){
                     case $backSourceFace:
                         $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n);
                         break;

                     default:
                         if ($x==0){
                             $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n);
                         }else{
                             $this->koef_Matrix["B"][$x]=-$prom_count-($L_next+$L_n)-($L_previos+$L_n);
                         }
                         break;
                 }
             }

        }

    private function C_back($z,$x,$y, $backSourceFace){
        $parametric_N=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x][$y]);
        $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$z][$x-1][$y]);

        $L_n=$parametric_N["teploprovodnost"];
        $L_previos=$parametric_previos["teploprovodnost"];
            switch ($x){
                case 0:
                    $this->koef_Matrix["C"][$x]=0;
                    break;

                case $backSourceFace:
                    $this->koef_Matrix["C"][$x]=0;
                    break;

                default:
                    if ($x==0){
                        $this->koef_Matrix["C"][$x]=0;
                    }else{
                        $this->koef_Matrix["C"][$x]=$L_previos+$L_n;
                    }
                    break;
            }
    }

    private function D_back($z,$x,$y, $backSourceFace){
        $temperature_N=$this->Raspredelenie_temperature[$z][$x][$y];
        $temperature_next=$this->Raspredelenie_temperature[$z][$x+1][$y];

        $proporites_N=$this->thermophysical_properties($temperature_N);
        $proporites_next=$this->thermophysical_properties($temperature_next);

        $teploemkost_N=$proporites_N["teploemkost"];
        $plotnost_N=$proporites_N["plotnost"];
        $L_n=$proporites_N["teploprovodnost"];

        $L_next=$proporites_next["teploprovodnost"];

        $prom_count=2*$teploemkost_N*$plotnost_N*pow($this->sourceShift/1000,2)/$this->timeStep;
        if ($z==0) {
            switch ($x) {
                case $backSourceFace:
                    $this->koef_Matrix["D"][$x] = $prom_count * $temperature_N + ($L_n + $L_next) * $temperature_next-2*$this->koef_heat_emission($temperature_N)*$this->ambient_temperature*($this->sourceShift/1000);
                    break;

                default:
                    $this->koef_Matrix["D"][$x] = $prom_count * $temperature_N-2*$this->koef_heat_emission($temperature_N)*$this->ambient_temperature*($this->sourceShift/1000);
                    break;
            }
        }else{
                switch ($x){
                    case $backSourceFace:
                        $this->koef_Matrix["D"][$x]=$prom_count*$temperature_N+($L_n+$L_next)*$temperature_next;
                        break;

                    default:
                        $this->koef_Matrix["D"][$x]=$prom_count*$temperature_N;
                        break;
                }
            }

        }

    private function Alpha_back($index){
        if ($index>0){
            $this->koef_Progon_Matrix["Alpha"][$index]=-$this->koef_Matrix["A"][$index]/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index-1]);
        }else{
            $this->koef_Progon_Matrix["Alpha"][$index]=-$this->koef_Matrix["A"][$index]/($this->koef_Matrix["B"][$index]);
        }
    }

    private function Beta_back($index){
        if ($index>0){
            $this->koef_Progon_Matrix["Beta"][$index]=-($this->koef_Matrix["D"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Beta"][$index-1])/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index-1]);
        }else {
            $this->koef_Progon_Matrix["Beta"][$index]=-$this->koef_Matrix["D"][$index]/$this->koef_Matrix["B"][$index];
        }
    }

    ##################################################################

    private function csv_write($t,$path){
            mkdir("{$path}/{$t}",0700);
            for ($z=0;$z<$this->partitionPlateZ;$z++){
                $file_open=fopen("{$path}/{$t}/{$z}.csv",'w');
                for($x=0;$x<$this->partitionPlateX;$x++){
                    fputcsv($file_open,$this->Raspredelenie_temperature[$z][$x],";");
                }
                fclose($file_open);
            }
    }

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
        $this->settings= "Время начала расчёта: ".date('Y-m-d H:i:s')."<br>";
        $this->settings.= "размеры пластины:<br>";
        $this->settings.= "X: ".$this->plateX."<br>";
        $this->settings.= "Y: ".$this->plateY."<br>";
        $this->settings.= "Z: ".$this->plateZ."<br>";
        $this->settings.= "температура пластины: ".$this->plateTemperature."<br>";

        //параметры для горелки
        $this->settings.= "Размеры горелки:<br>";
        $this->settings.= "X: ".$this->sourceX."<br>";
        $this->settings.= "Y: ".$this->sourceY."<br>";
        $this->settings.= "Z: ".$this->sourceZ."<br>";
        $this->settings.= "Температура горелки: ".$this->sourceTemperature."<br>";
        $this->settings.= "Скорость горелки: ".$this->sourceSpeed."мм/сек. <br>";
        $this->settings.= "Время прохода горелки по пластине: ".$this->time."(".($this->plateX-$this->sourceX)/$this->sourceSpeed.")сек.<br>";
        $this->settings.= "Перемещение горелки за один момент времени на: ".$this->sourceShift."мм<br>";

        //параметры шагов
        $this->settings.= "Кол-во ячеек по оси Х пластины: ".$this->partitionPlateX."<br>";
        $this->settings.= "Кол-во ячеек по оси Х горелки: ".$this->partitionSourceX."(".$this->sourceX/$this->sourceShift.")<br>";
        $this->settings.= "Временной шаг: ".$this->timeStep."<br>";
        $this->settings.= "Общее кол-во расматреваемых временных промежутков: ".$this->justMomentOfTime."<br>";

        $this->settings.= "Температура окружающей среды: ".$this->ambient_temperature."<br>";

        echo $this->settings;

        
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
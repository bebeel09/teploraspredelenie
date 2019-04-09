<?php

class Teploraspredelenie{

#ВХОДНЫЕ ПАРАМЕТРЫ
    #параметры пластины
   private $plateX;
   private $plateY;
   private $plateZ;
   private $plateTemperature; //начальная температура пластины 
   private $plateMaterial;

   private $ambient_temperature=10;//температура окружающей среды
    
    # Шаги разбиения
    private $step;//шаг по координатам
    
    private $timeStep; //отслеживаемые шаги по времени
    
    # параметры источника
    private $sourceX;
    private $sourceY;
    private $sourceZ;
    private $sourceTemperature; //температура горелки
    private $sourceSpeed;   //скорость прохода горелки по пластине

    private $koef_surface_E=array(
        "1500"=>0.6,
        "1000"=>0.59,
        "730"=>0.58,
        "600"=>0.56,
        "575"=>0.55,
        "500"=>0.54,
        "475"=>0.53,
        "400"=>0.51,
        "375"=>0.5,
        "300"=>0.48,
        "275"=>0.47,
        "225"=>0.46,
        "200"=>0.45,
        "175"=>0.44,
        "100"=>0.4,
        "75"=>0.35,
        "0"=>0.2
    );

#________________________________________________________________________________________________  
#------------------------------------------------------------------------------------------------

//ПАРАМЕТРЫ КОТОРЫЕ СЧИТАЕМ
    # разбиение размера источника на ячейки в соответствии с разбивкой материала
    private $partitionSourceX; // разбиение размера источника по оси Х для сохранения пропорций

    private $sourceShift; // изменение позиции источника за один расматирваемый момент
    private $partitionPlateX;//разбиение пластины на равные ячейки так что бы 1 ячейка=sourceShift
    private $time;//общее время прохода горелки по пластине
    private $justMomentOfTime;//общее количество отслежвыаемых моментов времени

    private $nextStartPosition=0;

    # матрица коэффицентов
    private $koef_Matrix=array(
        "A"=>array(),
        "B"=>array(),
        "C"=>array(),
        "D"=>array(),
    );

    # матрица прогоночных коэффицентов
    private $koef_Progon_Matrix=array(
        "Alpha"=>array(),
        "Beta"=>array(),
    );
    
    # результаты вычислений
    private $Raspredelenie_temperature=[[[[]]]];

#________________________________________________________________________________________________  
#------------------------------------------------------------------------------------------------


    function __construct($plate_proporites, $source_proporites, $step_proporites){
       global $plateMaterial;
       
        # параметры для пластины
        $this->plateX=$plate_proporites["plateX"];
        $this->plateY=$plate_proporites["plateY"];
        $this->plateZ=$plate_proporites["plateZ"];
        $this->plateTemperature=$plate_proporites["plateTemperature"];
        $this->plateMaterial=$plate_proporites["material"];

        # параметры для горелки
        $this->sourceX=$source_proporites["sourceX"];
        $this->sourceY=$source_proporites["sourceY"];
        $this->sourceZ=$source_proporites["sourceZ"];
        $this->sourceTemperature=$source_proporites["sourceTemperature"];
        $this->sourceSpeed=$source_proporites["sourceSpeed"];

        # параметры шагов
        $this->timeStep=$step_proporites["timeStep"];
        $this->step=1;

        $this->time=round(($this->plateX-$this->sourceX)/$this->sourceSpeed,1)+0.1;
        $this->justMomentOfTime=$this->time/$this->timeStep;

        $this->sourceShift=$this->sourceSpeed*$this->timeStep;
        $this->partitionPlateX=ceil($this->plateX/$this->sourceShift);
        $this->partitionSourceX=round($this->sourceX/$this->sourceShift);
        

        # генерация сеточной разбивки пластины и выставление начальных параметров в каждой ячейке
        $this->plateStartTemperature(); 
    }

    private function plateStartTemperature(){

        for($t=0; $t<$this->justMomentOfTime; $t++){
            for($z=0; $z<$this->plateZ; $z++){
                for($x=0; $x<$this->partitionPlateX; $x++){
                    for($y=0; $y<$this->plateY; $y++){
                        $this->Raspredelenie_temperature[$t][$z][$x][$y]=$this->plateTemperature; 
                    }
                }
            }
        }
        $this->nextStartPosition=0;
        for($t=0; $t<$this->justMomentOfTime; $t++){
            for($z=0; $z<$this->sourceZ; $z++){
                for($x=$this->nextStartPosition; $x<$this->partitionSourceX+$this->nextStartPosition; $x++){
                    for($y=0; $y<$this->sourceY; $y++){
                        $this->Raspredelenie_temperature[$t][$z][$x][$y]=$this->sourceTemperature; 
                    }
                }
            }
            $this->nextStartPosition++;
        }
        $this->nextStartPosition=0;
    }

    public function count_Raspr_Temperature(){
        for($t=1;$t<$this->justMomentOfTime;$t++){
            //по оси Y
            $this->go_Y($t);

            // по оси Z
             $this->go_Z($t);

             $this->nextStartPosition++;               
        }
        //по оси x
        $this->go_X($t); 
    }

    private function go_Y($time_moment){
        $t=$time_moment;
        for($y=0;$y<$this->plateY;$y++){
            $this->A($t,0,0,$y,"Y");
            $this->B($t,0,0,$y,"Y");
            $this->C($t,0,0,$y,"Y");
            $this->D($t,0,0,$y,"Y");
            if($y<$this->plateY-1){
                $this->Alpha($y);
                $this->Beta($y);
            }
        }
        
        $N=$this->plateY-1;
        $this->Raspredelenie_temperature[$t][0][0][$N]=round(-($this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1]+$this->koef_Matrix["D"][$N])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]),2);
        if ($this->Raspredelenie_temperature[$t][0][0][$N]<$this->plateTemperature){
            $this->Raspredelenie_temperature[$t][0][0][$N]=$this->plateTemperature;
        }
        //расчёт обратным шагом по оси Y
        for($y=$N-1;$y>=0;$y--){
            $this->Raspredelenie_temperature[$t][0][0][$y]=round($this->koef_Progon_Matrix["Alpha"][$y]*$this->Raspredelenie_temperature[$t][0][0][$y+1]+$this->koef_Progon_Matrix["Beta"][$y],2);
            if ($this->Raspredelenie_temperature[$t][0][0][$y]<$this->plateTemperature){
                $this->Raspredelenie_temperature[$t][0][0][$y]=$this->plateTemperature;
            }
        }

        $this->koef_Progon_Matrix=array(
            "Alpha"=>array(),
            "Beta"=>array(),
        );
    }

    private function go_Z($time_moment){
        $t=$time_moment;

        for($y=0;$y<$this->plateY;$y++){
            for($z=1;$z<$this->plateZ;$z++){
                $this->A($t,$z,0,$y,"Z");
                $this->B($t,$z,0,$y,"Z");
                $this->C($t,$z,0,$y,"Z");
                $this->D($t,$z,0,$y,"Z");
                if($z!=$this->plateZ-1){
                    $this->Alpha($z);
                    $this->Beta($z);
                }
            }
            $N=$this->plateZ-1;
            $this->Raspredelenie_temperature[$t][$N][0][$y]=round((-$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1]-$this->koef_Matrix["D"][$N])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]),2);
            if ($this->Raspredelenie_temperature[$t][$N][0][$y]<$this->plateTemperature){
                $this->Raspredelenie_temperature[$t][$N][0][$y]=$this->plateTemperature;
            }
            //расчёт обратным шагом по оси z
            for($z=$N-1;$z>0;$z--){
                $this->Raspredelenie_temperature[$t][$z][0][$y]=round($this->koef_Progon_Matrix["Alpha"][$z]*$this->Raspredelenie_temperature[$t][$z+1][0][$y]+$this->koef_Progon_Matrix["Beta"][$z],2);
                if ($this->Raspredelenie_temperature[$t][$z][0][$y]<$this->plateTemperature){
                    $this->Raspredelenie_temperature[$t][$z][0][$y]=$this->plateTemperature;
                }
            }
        }

        $this->koef_Progon_Matrix=array(
            "Alpha"=>array(),
            "Beta"=>array(),
        );

    } 

    private function go_X_count($start_index_Z,$exit_index_Z,$start_index_X,$exit_index_X,$start_index_Y,$exit_index_Y){
      
        for($t=1; $t<$this->justMomentOfTime;$t++){
            for($z=$start_index_Z; $z<$exit_index_Z; $z++){
                for($y=$start_index_Y; $y<$exit_index_Y; $y++){
                    for($x=$start_index_X; $x<$exit_index_X; $x++){
                        $this->A($t,$z,$x,$y,"X");
                        $this->B($t,$z,$x,$y,"X");
                        $this->C($t,$z,$x,$y,"X");
                        $this->D($t,$z,$x,$y,"X");
                        if($x<$exit_index_X-1){
                            $this->Alpha($x);
                            $this->Beta($x);
                        }
                    }
                    $N=$exit_index_X-1;

                    $this->Raspredelenie_temperature[$t][$z][$N][$y]=round((-$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1]-$this->koef_Matrix["D"][$N])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]),2);
                    if ($this->Raspredelenie_temperature[$t][$z][$N][$y]<$this->plateTemperature){
                        $this->Raspredelenie_temperature[$t][$z][$N][$y]=$this->plateTemperature;
                    }
                    //расчёт обратным шагом по всей оси x
                    for($x=$N-1;$x>=1;$x--){
                        $this->Raspredelenie_temperature[$t][$z][$x][$y]=round($this->koef_Progon_Matrix["Alpha"][$x]*$this->Raspredelenie_temperature[$t][$z][$x+1][$y]+$this->koef_Progon_Matrix["Beta"][$x],2);
                        if ($this->Raspredelenie_temperature[$t][$z][$x][$y]<$this->plateTemperature){
                            $this->Raspredelenie_temperature[$t][$z][$x][$y]=$this->plateTemperature;
                        }
                    }
                }
            }
        }
    }

    private function go_X_count_source_previos_area($start_index_Z,$exit_index_Z,$start_index_X,$start_index_Y,$exit_index_Y){
        $this->nextStartPosition=1;

        for($t=1; $t<$this->justMomentOfTime;$t++){
            for($z=$start_index_Z; $z<$exit_index_Z; $z++){
                for($y=$start_index_Y; $y<$exit_index_Y; $y++){
                    for($x=$start_index_X; $x<$this->nextStartPosition+1; $x++){
                        $this->A($t,$z,$x,$y,"X");
                        $this->B($t,$z,$x,$y,"X");
                        $this->C($t,$z,$x,$y,"X");
                        $this->D($t,$z,$x,$y,"X");
                        if($x<$this->nextStartPosition){
                            $this->Alpha($x);
                            $this->Beta($x);
                        }
                    }
                    $N=$this->nextStartPosition;

                    $this->Raspredelenie_temperature[$t][$z][$N][$y]=round((-$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1]-$this->koef_Matrix["D"][$N])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]),2);
                    if ($this->Raspredelenie_temperature[$t][$z][$N][$y]<$this->plateTemperature){
                        $this->Raspredelenie_temperature[$t][$z][$N][$y]=$this->plateTemperature;
                    }
                    //расчёт обратным шагом по всей оси x
                    for($x=$N-1;$x>=1;$x--){
                        $this->Raspredelenie_temperature[$t][$z][$x][$y]=round($this->koef_Progon_Matrix["Alpha"][$x]*$this->Raspredelenie_temperature[$t][$z][$x+1][$y]+$this->koef_Progon_Matrix["Beta"][$x],2);
                        if ($this->Raspredelenie_temperature[$t][$z][$x][$y]<$this->plateTemperature){
                            $this->Raspredelenie_temperature[$t][$z][$x][$y]=$this->plateTemperature;
                        }
                    }
                }
            }
            $this->nextStartPosition++;
        }
    }

    private function go_X_count_source_front_area($start_index_Z,$exit_index_Z,$exit_index_X,$start_index_Y,$exit_index_Y){
        $this->nextStartPosition=1;

        for($t=1; $t<$this->justMomentOfTime;$t++){
            for($z=$start_index_Z; $z<$exit_index_Z; $z++){
                for($y=$start_index_Y; $y<$exit_index_Y; $y++){
                    for($x=$this->nextStartPosition+$this->partitionSourceX; $x<$exit_index_X; $x++){
                        $this->A($t,$z,$x,$y,"X");
                        $this->B($t,$z,$x,$y,"X");
                        $this->C($t,$z,$x,$y,"X");
                        $this->D($t,$z,$x,$y,"X");
                        if($x<$exit_index_X-1){
                            $this->Alpha($x);
                            $this->Beta($x);
                        }
                    }
                    $N=$exit_index_X-1;

                    $this->Raspredelenie_temperature[$t][$z][$N][$y]=round((-$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1]-$this->koef_Matrix["D"][$N])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]),2);
                    if ($this->Raspredelenie_temperature[$t][$z][$N][$y]<$this->plateTemperature){
                        $this->Raspredelenie_temperature[$t][$z][$N][$y]=$this->plateTemperature;
                    }
                    //расчёт обратным шагом по всей оси x
                    for($x=$N-1;$x>=$this->nextStartPosition+$this->partitionSourceX;$x--){
                        $this->Raspredelenie_temperature[$t][$z][$x][$y]=round($this->koef_Progon_Matrix["Alpha"][$x]*$this->Raspredelenie_temperature[$t][$z][$x+1][$y]+$this->koef_Progon_Matrix["Beta"][$x],2);
                        if ($this->Raspredelenie_temperature[$t][$z][$x][$y]<$this->plateTemperature){
                            $this->Raspredelenie_temperature[$t][$z][$x][$y]=$this->plateTemperature;
                        }
                    }
                }
            }
            $this->nextStartPosition++;
        }
    }

    private function go_X($time_moment){

        $this->go_X_count(0,$this->sourceZ-1,1,$this->partitionPlateX,$this->sourceY-1,$this->plateY);
        $this->go_X_count($this->sourceZ-1,$this->plateZ,1,$this->partitionPlateX,0,$this->plateY);
        $this->go_X_count_source_previos_area(0,$this->sourceZ-1,1,0,$this->sourceY-1);
        $this->go_X_count_source_front_area(0,$this->sourceZ-1,$this->partitionPlateX,0,$this->sourceY-1);

        
    }

    //this is Lambda
    function thermophysical_properties( $temperature){
        $index=array(25,200,400,600,800,1000,1200,1300,1400,1462,1465,1470,1473,1477,1485,1495,1503,1508,1539,1600);
        for($i=0; $i<count($index); $i++){
            if ($temperature<$index[$i] ){
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

    function koef_heat_emission($temperature){
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

    //посчитать коэффицент "A"
    function A($t,$z,$x,$y,$orientation){
        $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
       
        switch ($orientation){
            case "X":
                if ($x==$this->partitionPlateX-1) $this->koef_Matrix["A"][$x]=0;    
                else{
                    $L_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x+1][$y]);
                    $this->koef_Matrix["A"][$x]=$L_next["teploprovodnost"]+$L_n["teploprovodnost"];         
                }
                break;
            case "Y":
                if ($y==$this->plateY-1) $this->koef_Matrix["A"][$y]=0;    
                else{
                    $L_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y+1]);
                    $this->koef_Matrix["A"][$y]=$L_next["teploprovodnost"]+$L_n["teploprovodnost"];                      
                }
                break;
            case "Z": 
                if ($z==$plateZ-1) $this->koef_Matrix["A"][$z]=0;
                else{    
                    $L_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z+1][$x][$y]);
                    $this->koef_Matrix["A"][$z]=$L_next["teploprovodnost"]+$L_n["teploprovodnost"]; 
                }             
            break;               
        }
    }

    //посчитать коэффицент "B"
    function B($t,$z,$x,$y,$orientation){
        $temperatureN=$this->Raspredelenie_temperature[$t-1][$z][$x][$y];

        $parametricN=$this->thermophysical_properties($temperatureN);
        $parametricSol=$this->thermophysical_properties($this->sourceTemperature);
        $parametricNext=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x+1][$y]);
        
        $plotnostN=$parametricN["plotnost"];//this is Ro proporites
        $teploemkostN=$parametricN["teploemkost"];// this is C proporites
        $L_n=$parametricN["teploprovodnost"];//this is Lamda proporites

        $L_sol=$parametricSol["teploprovodnost"];
        $L_next=$parametricNext["teploprovodnost"];

        $prom_vicheslenie=(-2*$teploemkostN*$plotnostN*pow($this->step/1000,2))/$this->timeStep;

        switch ($orientation){
                case "X":
                    $parametric_previos_mesh=$this->thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x-1][$y]);

                    $plotnost_previos_mesh=$parametric_previos_mesh["plotnost"];//this is Ro proporites
                    $teploemkost_previos_mesh=$parametric_previos_mesh["teploemkost"];// this is C proporites
                    $L_previos_mesh=$parametric_previos_mesh["teploprovodnost"];//this is Lamda proporites

                    $local_prom_vicheslenie=-(2*$teploemkost_previos_mesh*$plotnost_previos_mesh*pow($this->sourceShift/1000,2))/$this->timeStep;
                    switch ($x){
        
                        case 1:
                            $this->koef_Matrix["B"][$x]=$local_prom_vicheslenie-($L_next+$L_n)-($L_n+$L_previos_mesh)+2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000);
                        break;

                        case $this->partitionPlateX-1:
                            $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x-1][$y]);
                            $L_previos=$parametric_previos["teploprovodnost"];
                            $this->koef_Matrix["B"][$x]=$prom_vicheslenie-($L_previos+$L_n)+2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000);
                        break;

                        case $this->nextStartPosition:
                            if ($y==$this->plateY-1){
                                $this->koef_Matrix["B"][$x]=$local_prom_vicheslenie-($L_previos_mesh+$L_sol)-($L_next+$L_n)+2*$this->koef_heat_emission($this->Raspredelenie_temperature[$t][$z][$x-1][$y])*($this->sourceShift/1000);
                            }
                            
                        break;

                        default:
                            $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x+1][$y]);
                            $L_next=$parametric_next["teploprovodnost"];
                            $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x-1][$y]);
                            $L_previos=$parametric_previos["teploprovodnost"];
                            $this->koef_Matrix["B"][$x]=$prom_vicheslenie-($L_n+$L_previos)-($L_n+$L_next)+2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000);
                        break;
                            }
                break;

                case "Y":
                    switch ($y){
                        case 0:
                            $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y+1]);
                            $L_next=$parametric_next["teploprovodnost"];
                            $this->koef_Matrix["B"][$y]=$prom_vicheslenie-($L_next+$L_n)+2*$this->koef_heat_emission($temperatureN)*($this->step/1000);
                        break;

                        case $this->plateY-1:
                            $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y-1]);
                            $L_previos=$parametric_previos["teploprovodnost"];
                            $this->koef_Matrix["B"][$y]=$prom_vicheslenie-($L_n+$L_previos)+2*$this->koef_heat_emission($temperatureN)*($this->step/1000);
                        break;

                        default:
                            $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y+1]);
                            $L_next=$parametric_next["teploprovodnost"];
                            $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y-1]);
                            $L_previos=$parametric_previos["teploprovodnost"];
                            $this->koef_Matrix["B"][$y]=$prom_vicheslenie-($L_n+$L_previos)-($L_next+$L_n)+2*$this->koef_heat_emission($temperatureN)*($this->step/1000);
                        break;
                    }        
                break;

                case "Z":
                    switch ($z){
                        case 1:
                            $temperature_previos_mesh=$this->Raspredelenie_temperature[$t][$z-1][$x][$y];
                            $parametric_previos_mesh=$this->thermophysical_properties($temperature_previos_mesh);
                            
                            $plotnost_previos_mesh=$parametric_previos_mesh["plotnost"];//this is Ro proporites
                            $teploemkost_previos_mesh=$parametric_previos_mesh["teploemkost"];// this is C proporites
                            $L_previos_mesh=$parametric_previos_mesh["teploprovodnost"];//this is Lamda proporites

                            $parametric_next_mesh=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z+1][$x][$y]);
                            $L_next_mesh=$parametric_next_mesh["teploprovodnost"];
                    
                            $local_prom_vicheslenie=-(2*$teploemkost_previos_mesh*$plotnost_previos_mesh*pow($this->step/1000,2))/$this->timeStep;
                            $this->koef_Matrix["B"][$z]=$local_prom_vicheslenie-($L_previos_mesh+$L_n)-($L_next_mesh+$L_n);
                        break;

                        case $this->plateZ-1:
                            $parametric_previos_mesh=$this->thermophysical_properties($Raspredelenie_temperature[$t-1][$z-1][$x][$y]);
                            $L_previos_mesh=$parametric_previos_mesh["teploprovodnost"];
                            $this->koef_Matrix["B"][$z]=$prom_vicheslenie-($L_n+$L_previos_mesh)+2*$this->koef_heat_emission($temperatureN)*($this->step/1000);
                        break;

                        default:
                            $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z+1][$x][$y]);
                            $L_next=$parametric_next["teploprovodnost"];
                            $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z-1][$x][$y]);
                            $L_previos=$parametric_previos["teploprovodnost"];
                            $this->koef_Matrix["B"][$y]=$prom_vicheslenie-($L_n+$L_previos)-($L_next+$L_n);
                        break;
                        }
                break;
        }   
    }

    //посчитать коэффицент "С"
    function C($t,$z,$x,$y,$orientation){
        $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);

        switch ($orientation){
            case "X":
                switch ($x){

                    case 1:
                     if ($y==$this->sourceY-1){
                        $this->koef_Matrix["C"][$x]=0; 
                     }else{
                        $L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x-1][$y]);         
                        $this->koef_Matrix["C"][$x]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"];
                     }
                    break;
                    
                    default:
                    $L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x-1][$y]);         
                    $this->koef_Matrix["C"][$x]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"]; 
                    break;
                }
            break;
            
            case "Y":
                if ($y==0) $this->koef_Matrix["C"][$y]=0;        
                else{ 
                    $L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y-1]);                             
                    $this->koef_Matrix["C"][$y]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"]; 
                }
                break;
            case "Z": 
                if ($z==0) $this->koef_Matrix["C"][$z]=0;
                else{ 
                    $L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z-1][$x][$y]);                            
                    $this->koef_Matrix["C"][$z]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"]; 
                }
                break;
        }
            
    }   

    //посчитать коэффицент "D"
    function D($t,$z,$x,$y,$orientation){
        $temperature_previos=$this->Raspredelenie_temperature[$t-1][$z][$x][$y];

        $parametricN=$this->thermophysical_properties($temperature_previos);
        $parametricSol=$this->thermophysical_properties($this->sourceTemperature);
        
        $L_sol=$parametricSol["teploprovodnost"];

        $plotnostN=$parametricN["plotnost"];//this is Ro proporites
        $teploemkostN=$parametricN["teploemkost"];// this is C proporites

        $prom_vicheslenie=(2*$teploemkostN*$plotnostN*pow($this->step/1000,2))/$this->timeStep;
        switch($orientation){
            case "X": 
                $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x-1][$y]);
                $L_previos=$parametric_previos["teploprovodnost"];
                $prom_vicheslenie_X=(2*$teploemkostN*$plotnostN*pow($this->sourceShift/1000,2))/$this->timeStep;
                switch ($z){
                    case $z<$this->sourceZ:
                        switch ($y){
                            case $y>$this->sourceZ:
                                $temperature_previos_mesh=$this->Raspredelenie_temperature[$t][$z][$x-1][$y];
                                $parametric_previos_mesh=$this->thermophysical_properties($temperature_previos_mesh);

                                $plotnost_previos_mesh=$parametric_previos_mesh["plotnost"];//this is Ro proporites
                                $teploemkost_previos_mesh=$parametric_previos_mesh["teploemkost"];// this is C proporites
                                $L_previos_mesh=$parametric_previos_mesh["teploprovodnost"];//this is Lamda proporites

                                $local_prom_vicheslenie_X=(2*$teploemkost_previos_mesh*$plotnost_previos_mesh*pow($this->sourceShift/1000,2))/$this->timeStep;

                                switch ($x){
                                    case 1:
                                        $this->koef_Matrix["D"][$x]=$local_prom_vicheslenie_X*$temperature_previos_mesh-2*$this->koef_heat_emission($temperature_previos_mesh)*($this->sourceShift/1000)*$this->ambient_temperature;
                                    break;

                                    default:
                                        $this->koef_Matrix["D"][$x]=$prom_vicheslenie*$temperature_previos-2*$this->koef_heat_emission($temperature_previos)*($this->sourceShift/1000)*$this->ambient_temperature;
                                    break;
                                    }
                            break;

                            case $this->sourceY-1:
                                    switch ($x){
                                        case $this->nextStartPosition:
                                            if($x==1){
                                                $this->koef_Matrix["D"][$x]=$local_prom_vicheslenie_X*$temperature_previos_mesh-2*$this->koef_heat_emission($temperature_previos_mesh)*($this->sourceShift/1000)*$this->ambient_temperature+($L_previos_mesh+$L_sol)*$this->sourceTemperature; 
                                            }
                                        break;

                                        case $x>$this->nextStartPosition and $x<=$this->nextStartPosition+$this->partitionPlateX-1:
                                            $this->koef_Matrix["D"][$x]=$prom_vicheslenie-2*$this->koef_heat_emission($temperatureN)*($this->sourceShift/1000)*$this->ambient_temperature+($L_previos+$L_sol)*$this->sourceTemperature;
                                        break;
                                    }
                            break;
                                }
                    break;
                        }
            break;

            case "Y":
                switch ($y){
                    case 0:
                            $this->koef_Matrix["D"][$y]=$prom_vicheslenie*$temperature_previos-2*$this->ambient_temperature*$this->koef_heat_emission($temperature_previos)*($this->step/1000);
                            break;        

                            default:
                            $this->koef_Matrix["D"][$y]=$prom_vicheslenie*$temperature_previos-2*$this->ambient_temperature*$this->koef_heat_emission($temperature_previos)*($this->step/1000);
                            break;
                        }
            break;
            
            case "Z":
                switch ($z){
                    case 1:
                        $temperature_previos_mesh=$this->Raspredelenie_temperature[$t][$z-1][$x][$y];
                        $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos_mesh-2*$this->ambient_temperature*$this->koef_heat_emission($temperature_previos_mesh)*($this->step/1000);    
                    break;

                    case $this->plateZ-1:
                    $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos-2*$this->ambient_temperature*$this->koef_heat_emission($temperature_previos)*($this->step/1000);
                    break;

                    default:
                    $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos;
                    break;
                }
            break;
          
        }
    }

    private function Alpha($index){
       if ($index>0){
            $this->koef_Progon_Matrix["Alpha"][$index]=(-$this->koef_Matrix["A"][$index])/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index-1]);
        }else{ 
            $this->koef_Progon_Matrix["Alpha"][$index]=-$this->koef_Matrix["A"][$index]/($this->koef_Matrix["B"][$index]);
        }
    }

    private function Beta($index){
        if ($index>0){
            $this->koef_Progon_Matrix["Beta"][$index]=-($this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Beta"][$index-1]+$this->koef_Matrix["D"][$index])/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index-1]);
        }else {
            $this->koef_Progon_Matrix["Beta"][$index]=-$this->koef_Matrix["D"][$index]/$this->koef_Matrix["B"][$index];
            }
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

    public  function show_Raspredelenie(){
        $time_index=$this->sourceTime/$this->timeStep;
        
        for($t=0;$t<count($this->Raspredelenie_temperature);$t++){
        $table.="Момент времени: {$t}<br>";
            for($z=0;$z<count($this->Raspredelenie_temperature[0]);$z++){
                $table.="Слой( Z индекс): {$z}<br>";
                $table.="<table border='1' cellpadding='5'>";
                for($x=0;$x<count($this->Raspredelenie_temperature[0][0]);$x++){
                    $x1=$x/10;
                    $table.="<tr><th width='20px'>{$x1}</th>";
                    for($y=0;$y<count($this->Raspredelenie_temperature[0][0][0]);$y++){
                        $table.="<th >{$this->Raspredelenie_temperature[$t][$z][$x][$y]}</th>";
                    }
                    $table.="</tr>";
                }
                $table.="<table>";
               
        }
        } echo $table;

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
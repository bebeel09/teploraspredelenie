<?php

class Teploraspredelenie{

    //параметры пластины
   private $plateX;
   private $plateY;
   private $plateZ;
   private $plateTemperature; //начальная температура пластины 
   private $plateMaterial;
    
    //Шаги разбиения
    private $step;//шаг по координатам
    private $timeStep;//шаг от времени
    
    //параметры источника
    private $sourceX;
    private $sourceY;
    private $sourceZ;
    private $sourceTemperature; //температура горелки
    private $accessTime;   //Время прохода горелки по пластине

 


    //результаты вычислений
    private $Raspredelenie_temperature=[[[[]]]];

    //матрица коэффицентов
    private $koef_Matrix=array(
        "A"=>array(),
        "B"=>array(),
        "C"=>array(),
        "D"=>array()
    );

    function __construct($plate_proporites, $source_proporites, $step_proporites){
       global $plateMaterial;
       
        //параметры для пластины
        $this->plateX=$plate_proporites["plateX"];
        $this->plateY=$plate_proporites["plateY"];
        $this->plateZ=$plate_proporites["plateZ"];
        $this->plateTemperature=$plate_proporites["plateTemperature"];
        $this->plateMaterial=$plate_proporites["material"];

        //параметры для горелки
        $this->sourceX=$source_proporites["sourceX"];
        $this->sourceY=$source_proporites["sourceY"];
        $this->sourceZ=$source_proporites["sourceZ"];
        $this->sourceTemperature=$source_proporites["sourceTemperature"];
        $this->sourceTime=$source_proporites["sourceTime"];

        //параметры шагов
        $this->step=$step_proporites["step"];
        $this->timeStep=$step_proporites["timeStep"];
        $this->plateStartTemperature();
    }

    private function plateStartTemperature(){
        $time_index=$this->sourceTime/$this->timeStep;
        
        for($t=0; $t<$time_index; $t++){
            for($z=0; $z<$this->plateZ; $z++){
                for($x=0; $x<$this->plateX; $x++){
                    for($y=0; $y<$this->plateY; $y++){
                        $this->Raspredelenie_temperature[$t][$z][$x][$y]=$this->plateTemperature; 
                    }
                }
            }
        }
    }


    public  function show_Raspredelenie(){
        echo "<pre>";
        var_dump($this->Raspredelenie_temperature);
        echo "</pre>";
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
        return $this->plateMaterial[$index_proporites];
    
    }

    
    //посчитать коэффицент "A"
    function A($t,$z,$x,$y){
        $L_previos=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y+1]);
        $L_n=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y]);
        $this->koef_Matrix["A"]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"];
    }

    //посчитать коэффицент "B"
    function B($t,$z,$x,$y){
        $parametricN=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y]);
        $plotnostN=$parametricN["plotnost"];//this is Ro proporites
        $teploemkostN=$parametricN["teploemkost"];// this is C proporites
        $L_n=$parametricN["teploprovodnost"];//this is Lamda proporites

        switch ($y){
        case 0:
                $parametric_next=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y+1]);
                $L_next=$parametric_next["teploprovodnost"];
                $this->koef_Matrix["B"]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_next);
            break;
        case $this->plateY:
                $parametric_previos=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y-1]);
                $L_previos=$parametric_previos["teploprovodnost"];
                $this->koef_Matrix["B"]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_previos);
            break; 
        default:
                $parametric_next=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y+1]);
                $L_next=$parametric_next["teploprovodnost"];
                $parametric_previos=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y-1]);
                $L_previos=$parametric_previos["teploprovodnost"];
                $this->koef_Matrix["B"]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_next)-($L_n+$L_previos);
            break;
            }
    }

    //посчитать коэффицент "С"
    function C($t,$z,$x,$y){
        $L_previos=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y-1]);
        $L_n=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y]);
    $this->koef_Matrix["C"]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"];
    }

    //посчитать коэффицент "D"
    function D($t,$z,$x,$y){
        $parametricN=thermophysical_properties($this->Raspredelenie_temperature[$t][$z][$x][$y]);
        $plotnostN=$parametricN["plotnost"];//this is Ro proporites
        $teploemkostN=$parametricN["teploemkost"];// this is C proporites
        $L_n=$parametricN["teploprovodnost"];//this is Lamda proporites
        $this->koef_Matrix["B"]=(($teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep);
   
    }


    public function show_all_parametrs(){
       echo $this->plateX."<br>";
       echo $this->plateY."<br>";
       echo $this->plateZ."<br>";
       echo $this->plateTemperature."<br>";
       echo "<pre>";
       print_r($this->plateMaterial);
        echo "</pre>";
        //параметры для горелки
       echo $this->sourceX."<br>";
       echo $this->sourceY."<br>";
       echo $this->sourceZ."<br>";
       echo $this->sourceTemperature."<br>";
       echo $this->sourceTime."<br>";

        //параметры шагов
       echo $this->step."<br>";
       echo $this->timeStep."<br>";

    }

}




?>
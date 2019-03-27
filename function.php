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
        "D"=>array(),
    );

    private $koef_Progon_Matrix=array(
        "Alpha"=>array(),
        "Beta"=>array(),
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
        $this->Raspredelenie_temperature[0][0][0][0]=1500; 
        $this->Raspredelenie_temperature[1][0][0][0]=1500;
    }

    


    public function count_Raspr_Temperature(){
        $time_index=$this->sourceTime/$this->timeStep;

        //по оси Y
        for($t=1;$t<$time_index;$t++){
        for($y=0;$y<$this->plateY;$y++){
            $this->A($t,0,0,$y,$this->plateY-1);
            $this->B($t,0,0,$y);
            $this->C($t,0,0,$y);
            $this->D($t,0,0,$y);
           if($y!=$this->plateY-1){
               $this->Alpha($y);
               $this->Beta($y);
           }
        }
        $N=$this->plateY-1;
        $this->Raspredelenie_temperature[$t][0][0][$N]=(-$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1]-$this->koef_Matrix["D"][$N])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]);
        if ($this->Raspredelenie_temperature[$t][0][0][$N]<$this->plateTemperature){
            $this->Raspredelenie_temperature[$t][0][0][$N]=$this->plateTemperature;
        }

        //расчёт обратным шагом по оси Y
        for($y=$N-1;$y>-1;$y--){
            $this->Raspredelenie_temperature[$t][0][0][$y]=$this->koef_Progon_Matrix["Alpha"][$y]*$this->Raspredelenie_temperature[$t][0][0][$y+1]+$this->koef_Progon_Matrix["Beta"][$y];
            if ($this->Raspredelenie_temperature[$t][0][0][$y]<$this->plateTemperature){
                $this->Raspredelenie_temperature[$t][0][0][$y]=$this->plateTemperature;
            }
        }
        

        //по оси Z
        for($z=0;$z<$this->plateZ;$z++){
            $this->A($t,$z,0,0,$this->plateZ-1);
            $this->B($t,$z,0,0);
            $this->C($t,$z,0,0);
            $this->D($t,$z,0,0);
           if($z!=$this->plateZ-1){
               $this->Alpha($z);
               $this->Beta($z);
           }

           $N=$this->plateZ-1;
           $this->Raspredelenie_temperature[$t][$N][0][0]=(-$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1]-$this->koef_Matrix["D"][$N])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]);
           if ($this->Raspredelenie_temperature[$t][$N][0][0]<$this->plateTemperature){
               $this->Raspredelenie_temperature[$t][$N][0][0]=$this->plateTemperature;
           }
           //обратный шаг по оси Z
           for($z=$N-1;$z>-1;$z--){
               $this->Raspredelenie_temperature[$t][$z][0][0]=$this->koef_Progon_Matrix["Alpha"][$z]*$this->Raspredelenie_temperature[$t][0][0][$z+1]+$this->koef_Progon_Matrix["Beta"][$z];
               if ($this->Raspredelenie_temperature[$t][$z][0][0]<$this->plateTemperature){
                   $this->Raspredelenie_temperature[$t][$z][0][0]=$this->plateTemperature;
               }
           }

           $this->Raspredelenie_temperature[$t][0][$t][0]=1500;
        $this->Raspredelenie_temperature[$t+1][0][$t][0]=1500;
        }
       
        if($this->plateX-1>1){
            for($t=2;$t<$this->accessTime;$t++){
                for($z=0;$z<$this->plateZ;$z++){
                    for($x=0;$x<$this->plateX;$x++){
                        for($z=0;$z<$this->plateY;$y++){

                        }
                    }
                }

            }
        }
    }
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
    function A($t,$z,$x,$y,$direction){
        if ($y==$direction){
            $this->koef_Matrix["A"][$y]=0;    
        }else{
        $L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y+1]);
        $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
        $this->koef_Matrix["A"][$y]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"];
        }
    }

    //посчитать коэффицент "B"
    function B($t,$z,$x,$y){
        $parametricN=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
        $plotnostN=$parametricN["plotnost"];//this is Ro proporites
        $teploemkostN=$parametricN["teploemkost"];// this is C proporites
        $L_n=$parametricN["teploprovodnost"];//this is Lamda proporites

        switch ($y){
        case 0:
                $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y+1]);
                $L_next=$parametric_next["teploprovodnost"];
                $this->koef_Matrix["B"][$y]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_next);
            break;
        case $this->plateY-1:
                $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y-1]);
                $L_previos=$parametric_previos["teploprovodnost"];
                $this->koef_Matrix["B"][$y]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_previos);
            break; 
        default:
                $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y+1]);
                $L_next=$parametric_next["teploprovodnost"];
                $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y-1]);
                $L_previos=$parametric_previos["teploprovodnost"];
                $this->koef_Matrix["B"][$y]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_next)-($L_n+$L_previos);
            break;
            }
    }

    //посчитать коэффицент "С"
    function C($t,$z,$x,$y){
        if ($y==0){
            $this->koef_Matrix["C"][$y]=0;
        }else{
        $L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y-1]);
        $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
        $this->koef_Matrix["C"][$y]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"];
        }   
    }

    //посчитать коэффицент "D"
    function D($t,$z,$x,$y){
        $parametricN=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
        $plotnostN=$parametricN["plotnost"];//this is Ro proporites
        $teploemkostN=$parametricN["teploemkost"];// this is C proporites
        $this->koef_Matrix["D"][$y]=(($teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)*$this->Raspredelenie_temperature[$t-1][$z][$x][$y];
    }

    private function Alpha($index){
       if ($index>0){
            $this->koef_Progon_Matrix["Alpha"][$index]=(-$this->koef_Matrix["A"][$index])/($this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index-1]);
        }else $this->koef_Progon_Matrix["Alpha"][$index]=-$this->koef_Matrix["A"][$index]/($this->koef_Matrix["B"][$index]);
    }

    private function Beta($index){
        if ($index>0){
            $this->koef_Progon_Matrix["Beta"][$index]=($this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Beta"][$index-1]+$this->koef_Matrix["D"][$index])/(-$this->koef_Matrix["B"][$index]+$this->koef_Matrix["C"][$index]*$this->koef_Progon_Matrix["Alpha"][$index-1]);
        }else $this->koef_Progon_Matrix["Beta"][$index]=($this->koef_Matrix["D"][$index])/(-$this->koef_Matrix["B"][$index]);
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

    public  function show_Raspredelenie(){
        echo "<pre>";
        var_dump($this->Raspredelenie_temperature);
        echo "</pre>";
    }

    public  function show_koef_Matrix(){
        echo "<pre>";
        var_dump($this->koef_Matrix);
        echo "</pre>";
    }

    public function show_Progon_koef(){
        echo "<pre>";
        var_dump($this->koef_Progon_Matrix);
        echo "</pre>";

    }

}





?>
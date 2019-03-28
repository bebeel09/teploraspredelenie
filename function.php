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
            $this->A($t,0,0,$y,"Y");
            $this->B($t,0,0,$y,"Y");
            $this->C($t,0,0,$y,"Y");
            $this->D($t,0,0,$y);
           if($y!=$this->plateY-1){
               $this->Alpha($y);
               $this->Beta($y);
           }
        }
        $N=$this->plateY-1;
        $this->Raspredelenie_temperature[$t][0][0][$N]=round((-$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1]-$this->koef_Matrix["D"][$N])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]),2);
        if ($this->Raspredelenie_temperature[$t][0][0][$N]<$this->plateTemperature){
            $this->Raspredelenie_temperature[$t][0][0][$N]=$this->plateTemperature;
        }
        //расчёт обратным шагом по оси Y
        for($y=$N-1;$y>-1;$y--){
            $this->Raspredelenie_temperature[$t][0][0][$y]=round($this->koef_Progon_Matrix["Alpha"][$y]*$this->Raspredelenie_temperature[$t][0][0][$y+1]+$this->koef_Progon_Matrix["Beta"][$y],2);
            if ($this->Raspredelenie_temperature[$t][0][0][$y]<$this->plateTemperature){
                $this->Raspredelenie_temperature[$t][0][0][$y]=$this->plateTemperature;
            }
        }

        for($y=0;$y<$this->plateY;$y++){
            for($z=1;$z<$this->plateZ;$z++){
                $this->A($t,$z,0,$y,"Z");
                $this->B($t,$z,0,$y,"Z");
                $this->C($t,$z,0,$y,"Z");
                $this->D($t,$z,0,$y);
               if($y!=$this->plateY-1){
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
        for($z=0;$z<$this->plateZ;$z++){
            for($y=0;$y<$this->plateY;$y++){
                for($x=1;$x<$this->plateX;$x++){
                    $this->A($t,$z,$x,$y,"X");
                    $this->B($t,$z,$x,$y,"X");
                    $this->C($t,$z,$x,$y,"X");
                    $this->D($t,$z,$x,$y);
                   if($y!=$this->plateY-1){
                       $this->Alpha($x);
                       $this->Beta($x);
                   }
                }
                $N=$this->plateX-1;
                $this->Raspredelenie_temperature[$t][$z][$N][$y]=round((-$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Beta"][$N-1]-$this->koef_Matrix["D"][$N])/($this->koef_Matrix["B"][$N]+$this->koef_Matrix["C"][$N]*$this->koef_Progon_Matrix["Alpha"][$N-1]),2);
                if ($this->Raspredelenie_temperature[$t][$z][$N][$y]<$this->plateTemperature){
                    $this->Raspredelenie_temperature[$t][$z][$N][$y]=$this->plateTemperature;
                }
                //расчёт обратным шагом по оси z
                for($x=$N-1;$x>0;$x--){
                    $this->Raspredelenie_temperature[$t][$z][$x][$y]=round($this->koef_Progon_Matrix["Alpha"][$x]*$this->Raspredelenie_temperature[$t][$z][$x+1][$y]+$this->koef_Progon_Matrix["Beta"][$x],2);
                    if ($this->Raspredelenie_temperature[$t][$z][$x][$y]<$this->plateTemperature){
                        $this->Raspredelenie_temperature[$t][$z][$x][$y]=$this->plateTemperature;
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
    function A($t,$z,$x,$y,$orientation){
       
        switch ($orientation){
            case "X":
            if ($x==$this->plateX-1) $this->koef_Matrix["A"][$x]=0;    
            else{
                $L_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x+1][$y]);
                $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
                $this->koef_Matrix["A"][$x]=$L_next["teploprovodnost"]+$L_n["teploprovodnost"];         
            }
                break;
            case "Y":
            if ($y==$this->plateY-1) $this->koef_Matrix["A"][$y]=0;    
            else{
                $L_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y+1]);
                $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
                $this->koef_Matrix["A"][$y]=$L_next["teploprovodnost"]+$L_n["teploprovodnost"];                      
            }
                break;
            case "Z": 
            if ($z==$plateZ-1) $this->koef_Matrix["A"][$z]=0;
            else{    
                $L_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z+1][$x][$y]);
                $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
                $this->koef_Matrix["A"][$z]=$L_next["teploprovodnost"]+$L_n["teploprovodnost"]; 
            }             
            break;               
        }
    }

    //посчитать коэффицент "B"
    function B($t,$z,$x,$y,$orientation){
        $parametricN=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
        $plotnostN=$parametricN["plotnost"];//this is Ro proporites
        $teploemkostN=$parametricN["teploemkost"];// this is C proporites
        $L_n=$parametricN["teploprovodnost"];//this is Lamda proporites
        switch ($orientation){
                case "X":
                    switch ($x){
                        case 0:
                                $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x+1][$y]);
                                $L_next=$parametric_next["teploprovodnost"];
                                $this->koef_Matrix["B"][$x]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_next);
                            break;
                        case $this->plateY-1:
                                $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x-1][$y]);
                                $L_previos=$parametric_previos["teploprovodnost"];
                                $this->koef_Matrix["B"][$x]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_previos);
                            break; 
                        default:
                                $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x+1][$y]);
                                $L_next=$parametric_next["teploprovodnost"];
                                $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x-1][$y]);
                                $L_previos=$parametric_previos["teploprovodnost"];
                                $this->koef_Matrix["B"][$x]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_next)-($L_n+$L_previos);
                            break;
                            }

                break;

                case "Y":
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
                break;

                case "Z":
                switch ($z){
                    case 0:
                            $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z+1][$x][$y]);
                            $L_next=$parametric_next["teploprovodnost"];
                            $this->koef_Matrix["B"][$z]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_next);
                        break;
                    case $this->plateY-1:
                            $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z-1][$x][$y]);
                            $L_previos=$parametric_previos["teploprovodnost"];
                            $this->koef_Matrix["B"][$z]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_previos);
                        break; 
                    default:
                            $parametric_next=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z+1][$x][$y]);
                            $L_next=$parametric_next["teploprovodnost"];
                            $parametric_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z+1][$x][$y]);
                            $L_previos=$parametric_previos["teploprovodnost"];
                            $this->koef_Matrix["B"][$z]=((-$teploemkostN*$plotnostN*($this->step/1000)*($this->step/1000))/$this->timeStep)-($L_n+$L_next)-($L_n+$L_previos);
                        break;
                        }
            break;

        }   
       
    }

    //посчитать коэффицент "С"
    function C($t,$z,$x,$y,$orientation){
       
        switch ($orientation){
            case "X":
                if ($x==0) $this->koef_Matrix["C"][$x]=0;    
                else{$L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x-1][$y]);         
                $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
                $this->koef_Matrix["C"][$x]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"]; 
                }
                break;
            case "Y":
                if ($y==0) $this->koef_Matrix["C"][$y]=0;        
                else{
                $L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y-1]);                      
                $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
                $this->koef_Matrix["C"][$y]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"]; 
                }
                break;
            case "Z": 
                if ($z==0) $this->koef_Matrix["C"][$z]=0;
                else{  
                $L_previos=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z-1][$x][$y]);              
                $L_n=$this->thermophysical_properties($this->Raspredelenie_temperature[$t-1][$z][$x][$y]);
                $this->koef_Matrix["C"][$z]=$L_previos["teploprovodnost"]+$L_n["teploprovodnost"]; 
                }
                break;
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
        $time_index=$this->sourceTime/$this->timeStep;
        
        for($t=0;$t<count($this->Raspredelenie_temperature);$t++){
        $table.="Момент времени: {$t}<br>";
            for($z=0;$z<count($this->Raspredelenie_temperature[0]);$z++){
                $table.="Слой( Z индекс): {$z}<br>";
                $table.="<table border='1' cellpadding='5'>";
                for($x=0;$x<count($this->Raspredelenie_temperature[0][0]);$x++){
                    
                    $table.="<tr><th width='20px'>{$x}</th>";
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
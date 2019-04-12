 <?php
 case "Z":
 switch ($z){
     case $this->sourceZ:
         switch ($y){
             case $y>=0 and $y<$this->sourceY:
                 switch ($x){
                     case $x>=$this->nextStartPosition and $x<$this->nextStartPosition+$this->partitionSourceX:
                         $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos-($L_n+$L_sol)*$this->sourceTemperature;
                     break;

                     default:
                     $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos-($L_n+$L_previos)*$temperature_previos_mesh;
                     break;
                 }
             break;

             default:
                 if ($y==0){
                     switch ($x){
                         case $x>=$this->nextStartPosition and $x<$this->nextStartPosition+$this->partitionSourceX:
                         if ($x==0){
                             $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos-($L_n+$L_previos)*$temperature_previos_mesh;
                         }else{
                             $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos-($L_n+$L_sol)*$this->sourceTemperature;
                         }
                         break;

                         default:
                         $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos-($L_n+$L_previos)*$temperature_previos_mesh;
                         break;
                     }
                 }else{
                 $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos-($L_n+$L_previos)*$temperature_previos_mesh;
                 }
             break;
         }
     break;

     case $this->plateZ-1:
         $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos-2*$this->koef_heat_emission($temperature_previos)*($this->step/1000)*$this->ambient_temperature;
     break;

     default:
     $this->koef_Matrix["D"][$z]=$prom_vicheslenie*$temperature_previos;
     break;
 }
             
break;

?>
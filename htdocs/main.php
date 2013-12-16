<?php

//***************************************************************************
// WEB Interface of p4d / Linux - Heizungs Manager
// This code is distributed under the terms and conditions of the
// GNU GENERAL PUBLIC LICENSE. See the file LICENSE for details.
// Date 04.11.2010 - 08.12.2013  Jörg Wendel
//***************************************************************************

include("header.php");

  // -------------------------
  // establish db connection

  mysql_connect($mysqlhost, $mysqluser, $mysqlpass);
  mysql_select_db($mysqldb);
  mysql_query("set names 'utf8'");
  mysql_query("SET lc_time_names = 'de_DE'");

  // -------------------------
  // get last time stamp

  $result = mysql_query("select max(time), DATE_FORMAT(max(time),'%d. %M %Y   %H:%i') as maxPretty from samples;")
     or die("Error" . mysql_error());
  $row = mysql_fetch_array($result, MYSQL_ASSOC);
  $max = $row['max(time)'];
  $maxPretty = $row['maxPretty'];

  // ----------------
  // init

  $day   = isset($_GET['sday'])   ? $_GET['sday']   : (int)date("d");
  $month = isset($_GET['smonth']) ? $_GET['smonth'] : (int)date("m");
  $year  = isset($_GET['syear'])  ? $_GET['syear']  : (int)date("Y");
  $range = isset($_GET['range'])  ? $_GET['range']  : 1;

  // ------------------
  // State of P4 Daemon

  $p4dstate = requestAction("p4d-state", 3, $p4dNext);

  // ----------------
  // Status

  $result = mysql_query("select text from samples where time = '" . $max . "' and address = 3 and type = 'UD';")
     or die("Error" . mysql_error());
  $row = mysql_fetch_array($result, MYSQL_ASSOC);
  $time = $row['text'];

  $result = mysql_query("select text,value from samples where time = '" . $max . "' and address = 1 and type = 'UD';")
     or die("Error" . mysql_error());
  $row = mysql_fetch_array($result, MYSQL_ASSOC);
  $status = $row['text'];
  $state = $row['value'];

  $result = mysql_query("select text from samples where time = '" . $max . "' and address = 2 and type = 'UD';")
     or die("Error" . mysql_error());
  $row = mysql_fetch_array($result, MYSQL_ASSOC);
  $mode = $row['text'];

  echo " <div id=\"aStateInfo\">";

  if ($state == 19)
     echo  "  <div id=\"aStateOk\"><center>$status</center></div><br>";
  elseif ($state == 0)
     echo  "  <div id=\"aStateFail\"><center>$status</center></div><br>";
  elseif ($state == 3)
     echo  "  <div id=\"aStateHeating\"><center>$status</center></div><br>";
  else
     echo  "  <div id=\"aStateOther\"><center>$status</center></div><br>";

  echo $time ."<br>";
  echo "Betriebsmodus:  " . $mode ."<br>";
  echo " </div>";

  if ($state == 0 || $p4dstate != 0)
     echo "  <img id=\"aStateImage\" src=\"error.png\">";  
  elseif ($state == 3)
     echo "  <img id=\"aStateImage\" src=\"fire.png\">";
  else
     echo "  <img id=\"aStateImage\" src=\"p4.png\">";

  echo " <div id=\"aP4dInfo\">";

  if ($p4dstate == 0)
  {
    echo  "  <div id=\"aStateOk\"><center>P4 Daemon ONLINE</center></div><br/>";
    echo  "  <center>Nächste Aufzeichnung $p4dNext</center>";
  }
  else
    echo  "  <div id=\"aStateFail\"><center>Warning: P4 Daemon OFFLINE</center></div>";

  echo " </div>";

  echo "<br>\n";

  // ----------------
  // 

  echo " <div id=\"aSelect\">";
  echo "  <form name='navigation' method='get'>\n";
  echo "    <center>Zeitraum der Charts<br></center>\n";
  echo datePicker("Start", "s", $year, $day, $month);

  echo "     <select name=\"range\">\n";
  echo "        <option value='1' "  . ($range == 1  ? "SELECTED" : "") . ">Tag</option>\n";
  echo "        <option value='7' "  . ($range == 7  ? "SELECTED" : "") . ">Woche</option>\n";
  echo "        <option value='31' " . ($range == 31 ? "SELECTED" : "") . ">Monat</option>\n";
  echo "     </select>\n";

  echo "     <input type=submit value=\"Go\">";

  echo "  </form>\n";
  echo " </div>\n";

  $from = date_create_from_format('!Y-m-d', $year.'-'.$month.'-'.$day)->getTimestamp();

  // ------------------
  // table

  echo "  <table class=\"tableLight\" cellspacing=0 rules=rows style=\"position:absolute; top:290px; left:50px;\">\n";

  echo "    <tr class=\"tableHead\"><td/><td/><td><center>" . $maxPretty . "</center><td/><td/></tr>\n";

  echo "      <tr class=\"tableHead1\">\n";
  echo "        <td>Id</td>\n";
  echo "        <td>Sensor</td>\n";
  echo "        <td>Type</td>\n";
  echo "        <td>Wert</td>\n";
  echo "      </tr>\n";

  $strQuery = sprintf("select s.address as s_address, s.type as s_type, s.time as s_time, s.value as s_value, s.text as s_text, f.title as f_title, f.unit as f_unit 
              from samples s, valuefacts f where f.state = 'A' and f.address = s.address and f.type = s.type and s.time = '%s';", $max);

  $result = mysql_query($strQuery)
     or die("Error" . mysql_error());

  $i = 0;

  while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
  {
     $value = $row['s_value'];
     $text = $row['s_text'];
     $title = $row['f_title'];
     $unit = $row['f_unit'];
     $address = $row['s_address'];
     $type = $row['s_type'];
     $txtaddr = sprintf("0x%x", $address);

     if ($unit == "zst" || $unit == "dig")
        $unit = "";
     else if ($unit == "U")
        $unit = " u/min";
     else if ($unit == "T")
     {
        $unit = "";
        $value = $text;
     }

     $url = "<a href=\"#\" onclick=\"window.open('detail.php?width=1200&height=600&address=$address&type=$type&from=" . $from . "&range=" . $range . " ','_blank'," 
        . "'scrollbars=yes,width=1200,height=600,resizable=yes,left=120,top=120')\">";
     
     if ($i++ % 2)
        echo "   <tr class=\"tableDark\">\n";
     else
        echo "   <tr class=\"tableLight\">\n";
     
     echo "      <td>" . $txtaddr . "</td>\n";   
     echo "      <td>" . $type . "</td>\n";   
     echo "      <td>" . $url . $title . "</a></td>\n";
     echo "      <td>$value$unit</td>\n";
     echo "   </tr>\n";
  }

  echo "  </table>\n";

  mysql_close();

include("footer.php");
?>

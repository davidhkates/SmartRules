<?php
declare(strict_types=1);

session_start();

ini_set('max_execution_time', 0); // to get unlimited php script execution time

if(empty($_SESSION['i'])){
    $_SESSION['i'] = 0;
}

$total = 100;
for($i=$_SESSION['i'];$i<$total;$i++)
{
    $_SESSION['i'] = $i;
    $percent = intval($i/$total * 100)."%";

    sleep(1); // Here call your time taking function like sending bulk sms etc.
    $progress_html = "<div style=\"width:{$percent};background:linear-gradient(to bottom, rgba(125,126,125,1) 0%,rgba(14,14,14,1) 100%); ;height:35px;\">&nbsp;</div>";
    $status_html = "<div style=\"text-align:center; font-weight:bold;\">{$percent} is processed.</div>";
    $timer_html = $i;

    echo "<script>";
    echo "$('#progress_bar').html({$progress_html});";
    echo "$('#status_message').html({$status_html});";
    echo "$('#status_timer').html({$timer_html});";
    echo "</script>";

    ob_flush();
    flush();
}

echo '<script>';
echo 'parent.document.getElementById("information").innerHTML="<div style=\"text-align:center; font-weight:bold;\">Process completed</div>';
echo '</script>';

session_destroy();

?>

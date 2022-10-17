<?php
declare(strict_types=1);

/***********************************************************************************
 * MASTER PHP FILE THAT INCLUDES OTHER CODE for SMARTHINGS RULES API
 ***********************************************************************************/

    // require_once "dbconnect.php";
	// require_once "pipeline_processing.php";
	// require_once "pipeline_display.php";
	// require_once "pipeline_utilities.php";

$room_array=[];

function smartthings_api($command):array {
    // call smartthings API to get locations
    $bearer_token = "8ecca2c7-daf4-4aef-bbed-0538b9906862";
    $options = array('http' => array(
        'method'  => 'GET',
        'header' => 'Authorization: Bearer '. $bearer_token
    ));
    $context  = stream_context_create($options);
    $api_url = "https://api.smartthings.com/v1/".$command;
    $response_json = file_get_contents($api_url, false, $context);

    $decoded_json = json_decode($response_json, true);
    return $decoded_json['items'];
}

function locations_dropdown($location):void {
    // call smartthings API to get locations
    $items = smartthings_api('locations');
    foreach($items as $item) {
        echo "<option value='".$item['locationId']."'";
        if ($item['locationId']==$location) {
            echo " selected";
        }
        echo ">".$item['name']."</option>";
    }
}

function rooms_dropdown($location, $room):void {
    // call smartthings API to get rooms for selected location
    $items = smartthings_api('locations/'.$location.'/rooms');
    global $room_array;
    $room_array = [];
    foreach($items as $item) {
        $room_name = $item['name'];
        $room_id = $item['roomId'];

        echo "<option value='".$room_id."'".(($room==$room_id)?" selected":"").">".$room_name."</option>";
        $room_array[$room_name] = $room_id;
    }
}

function display_rooms($location, $room):void {
    $items = smartthings_api('locations/'.$location.'/rooms');
    global $room_array;
    $room_array = [];
    foreach($items as $item) {
        $room_name = $item['name'];
        $room_id = $item['roomId'];

        echo "<tr><td><input type='radio' name='room' id='$room_id' value='$room_id' onchange=\"form.submit();\"";
        echo (($room==$room_id)?' checked':'')."/>".$room_name."</td>";
        echo "<td>".$item['roomId']."</td></tr>";
        $room_array[$room_name] = $item['roomId'];
    }
}

function display_devices($location, $room):void {
    global $room_array;
    $uri = "devices?locationId=".$location;
    if ($room) {
        $uri .= "&roomId=" . $room;
    }
    // echo 'Devices API request URI: '.$uri;
    $items = smartthings_api($uri);

    foreach($items as $item) {
        echo "<tr><td>".$item['label']."</td>";
        // echo "<td>".$item['roomId']."</td>";
        echo "<td>".array_search($item['roomId'], $room_array)."</td>";
        echo "<td>".$item['deviceId']."</td></tr>";
    }
}

function display_rules($location):void {
    $items = smartthings_api('rules?locationId='.$location);
    foreach($items as $item) {
        $rule_id = $item['id'];
        $rule_actions = $item['actions'];
        echo "<tr class='rule_select' id='".$rule_id."'><td>" . $item['name'] . "</td>";
        echo "<td>".$item['id']."</td>";
        echo "<td id='action_".$rule_id."' style='display:none;'>";
        // echo print_r($item['actions']);
        echo json_encode($item['actions'], JSON_PRETTY_PRINT);
        echo "</td></tr>";
    }
}

function modes_dropdown($location):void {
    $items = smartthings_api('locations/'.$location.'/modes');
    foreach($items as $item) {
        echo "<option value='".$item['id']."'>".$item['name']."</option>";
    }
}

/*
function parse_json($response):void {

}

function output_to_json($array):void {
    echo json_encode($array) . "\r\n";
}

// display data specified in query in a scrollable table w/ or w/out formatting
function display_table($json, $select_type=""):void {
    // echo 'Table display query: ' . $query . '<br/>';
    // $result = execute_query($db, $query);

    // display table rows and columns
    $starting_column = 0;
    echo "<tbody>";
    while ($row = 0) {
        echo "<tr>";

            switch ($select_type) {
                case 'checkbox':
                    checkbox_group($group_name, $key_value, '', false, false, true);
                    break;
                case 'radio':
                    // added this to mark the non-inferred (original source) concept in radio group (details page)
                    // TODO - clean up and make somewhat more elegant when time permits, if necessary
                    radio_option($group_name, $key_value, '', (($_POST[$group_name]==$key_value)&&!$inferred_record), false, true);
                    break;
                case 'button':
                    button_group($group_name, $key_value, 'Map', false, true);
                    break;
            }
            $starting_column = 3;
        }

        // display remaining visible table columns
        for ($x = $starting_column; $x < $columns; $x++) {
            $value = $row[$x];
        }
        echo "</tr>\n";
    }
    echo "</tbody>";
}
*/



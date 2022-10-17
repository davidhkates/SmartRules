<!DOCTYPE HTML>
<link href="smartthings.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SmartThings Rules Portal</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
    <!--script src ="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script-->
    <script src="smartthings.js"></script>
	<?php require_once "smartthings.php"; ?>
    <script type="text/javascript">
        $(document).ready(function () {
            $('table.display').DataTable();
        })
    </script>
</head>
<body>
<main>
    <header>
        <h1>SmartThings Home Automation Rules API</h1>
    </header>

    <br><p>
    <form id="details_form" action="" method="post">
        <input type="hidden" id="tab_shown" name="tab_shown" value="details" />

        <?php
        $location = ($_POST['location']);
        $room = ($_POST['room']);
        $rule = ($_POST['rule']);
        // echo "Currently selected location ID: " . $location;
        // echo "Currently selected room ID: " . $room;

        /*
        switch ($_POST['submit_details']) {
            case 'Clear':
                delete_pipeline_tables($db, $org, $domain);
                break;
            case 'Load':
                delete_pipeline_tables($db, $org, $domain);
                load_pipeline_bronze($db, $org, $domain);
                break;
            case 'Run':
                delete_pipeline_silver($db, $org, $domain);
                load_pipeline_silver($db, $org, $domain);
                load_pipeline_gold($db, $org, $domain);
                break;
            case 'Harmonize':
                harmonize_code_vocab($db, $org, $domain);
                break;
            case 'Push':
                push_pipeline_tables($db, $domain);
                break;
        }
        */
        ?>

        <table style="layout-table;">
            <tr>
                <td colspan="2" style="background-color: #336791; color: white;"><h2>SmartThings Rules Builder</h2></td>
            </tr>
            <tr>
                <td style="width:10%; vertical-align:top;">
                    <p><label for="location" style="font-weight: bold;">Choose&nbsp;a&nbsp;location:</label>
                        <select name="location" id="location" onchange="form.submit();">
                            <?php locations_dropdown($location); ?>
                        </select>

                    <p><label for="location" style="font-weight: bold;">Choose&nbsp;a&nbsp;room:</label>
                        <select name="room" id="room" onchange="form.submit();">
                            <?php rooms_dropdown($location, $room); ?>
                        </select>

                    <p><input type="submit" name="submit_details" value="Clear" title="Clear bronze and silver zone">
                        <input type="submit" name="submit_details" value="Load" title="Load data from S3 into bronze">
                        <input type="submit" name="submit_details" value="Run" title="Run SQL to populate silver and gold">
                    <input type="hidden" id="selected_record" name="selected_record"/>
                    <!--p><input type="submit" name="lookup" value="Lookup"></p-->

                    <!--Drag concept for mapping here
                    <div id="dropzone" ondrop="drop(event)" ondragover="allowDrop(event)" style="background-color: orange">&nbsp;</div> -->
                </td>
                <td style="width:90%;">
                    <table><tr>
                        <td><h3>Devices</h3>
                            <table class='display table-heading' style='width:100%;'>
                                <thead><tr><th>Name</th><th>Room ID</th><th>Device ID</th></tr></thead>
                                <tbody><?php display_devices($location, $room); ?></tbody>
                            </table>
                        </td>

                        <td style="vertical-align:top;">
                            <h3>Modes</h3>
                            <select id="mode""><?php modes_dropdown($location); ?></select>
                            <h3>Time</h3>
                            <input type="text" placeholder="Start Time" />
                            <input type="text" placeholder="End Time" />
                            <h3>Constructor</h3>
                        </td></tr>
                    </table>

                    <h3>Rules</h3>
                    <table class='display table-heading wrap' style='width:100%;'>
                        <thead><tr><th>Name</th><th>Rule ID</th><!--th>Actions</th--></tr></thead>
                        <tbody><?php display_rules($location); ?></tbody>
                    </table>

                    <h3>Rule Definition</h3>
                    <pre><div id="rule_actions" /></pre>

                </td>
            </tr>
        </table>
    </form>

</main>
</body>
</html>
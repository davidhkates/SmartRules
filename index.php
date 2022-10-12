<!DOCTYPE HTML>
<link href="upn_styles.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UPN Clinical Data Factory Dashboard</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
    <!--script src ="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script-->
    <script src="upn_scripts.js"></script>
	<?php require_once "pipeline_functions.php"; ?>
    <script type="text/javascript">
        $(document).ready(function () {
            $('table.display').DataTable();
        })
    </script>
</head>
<body>
<main>
    <header style="background-color:#E0EFED;">
        <h1>Niwot SmartThings Home Automation</h1>
    </header>
        </ul>&nbsp;&nbsp;
        <img id="wait_spinner" src="inc/roller.gif" alt="processing please wait" style="width:24px;height:24px;display:none;"/>
    </nav>

    <div id="details" style="display:<?php display_tab('details', true); ?>;">
        <?php require_once "details.php"; ?>
    </div>

</main>
</body>
</html>
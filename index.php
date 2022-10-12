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
        <a href="https://hello.sevenbridges.com/unifiedpatientnetwork"><img alt="UPN" src="inc/upn_banner.png" height="40"/></a>
        <h1>Clinical Data Pipeline Dashboard</h1>
    </header>
    <nav>
        <ul id="mainMenu">
            <li name="summary"><a href="#">Summary</a></li>
            <li name="details"><a href="#">Pipeline</a></li>
            <li name="mapping"><a href="#">Ontology</a></li>
            <li name="quality"><a href="#">Quality</a></li>
            <li name="projects"><a href="#">Projects</a></li>
            <li name="config"><a href="#">Config</a></li>
            <li name="overview"><a href="#">Overview</a></li>
        </ul>&nbsp;&nbsp;
        <img id="wait_spinner" src="inc/roller.gif" alt="processing please wait" style="width:24px;height:24px;display:none;"/>
    </nav>

    <br><p>
    <div id="summary" style="display:<?php display_tab('summary'); ?>;">
        <?php require_once "summary.php"; ?>
    </div>
    <div id="details" style="display:<?php display_tab('details', true); ?>;">
        <?php require_once "details.php"; ?>
    </div>
    <div id="mapping" style="display:<?php display_tab('mapping'); ?>;">
        <?php require_once "mapping.php"; ?>
    </div>
    <div id="quality" style="display:none;">
        <?php require_once "quality.php"; ?>
    </div>
    <div id="projects" style="display:<?php display_tab('projects'); ?>;">
        <?php require_once "projects.php"; ?>
    </div>
    <div id="config" style="display:<?php display_tab('config'); ?>;">
        <?php require_once "config.php"; ?>
    </div>
    <div id="overview" style="display:none;">
        <?php require_once "overview.php"; ?>
    </div>

</main>
</body>
</html>
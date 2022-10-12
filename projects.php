<form id="projects_form" action="" method="post">
    <input type="hidden" id="tab_shown" name="tab_shown" value="projects"/>

    <?php
    $project_name = $_POST['project_name'];
    switch ($_POST['submit_project']) {
        case 'Update':
            break;
        case 'Save':
            save_cohort($db, $_POST);
            break;
        case 'Add':
            // add_project($db);
            break;
    }

    $project_id = $_POST['project_group'];
    $cohort_members = ( $_POST['cohort_members'] ?: 'all');
    ?>

    <table style="layout-table">
        <tr>
            <td colspan="3" style="background-color: #336791; color: white"><h2>UPN Project Management</h2></td>
        </tr>

        <tr>
            <td style="width:5%; vertical-align:top;">
                <p><b>Project Name:</b></p>
                <p><input type="text" name="project_name" id="project_name" value="<?php echo $project_name; ?>"/></p>

                <p><input type="submit" name="submit_project" value="Add"/>
                    <input type="submit" name="submit_project" value="Update"/>
                    <input type="submit" name="submit_project" value="Save"/></p>

                <b>Show patients:</b><br/>
                <?php
                radio_option('cohort_members', 'all', 'All', true);
                radio_option('cohort_members', 'members', 'Members');
                radio_option('cohort_members', 'available', 'Available');
                ?>

                <p><i>Business development and project managers will use this page to define cohorts and
                        project parameters (e.g., embargo period)</i></p>
            </td>
            <td style="width:90%">
                <h3>Projects</h3>
                <div class="tableFixHead">
                    <table id="" class="display" style="width:100%">
                        <thead>
                        <tr>
                            <th>Select</th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Customer Type</th>
                            <th>Embargo Start Date</th>
                            <th>Embargo End Date</th>
                        </tr>
                        </thead>
                        <?php
                        $query = "select project_id, name, description, type, embargo_start, embargo_end ";
                        $query .= "from config.project order by embargo_start;";
                        display_table($db, $query, 'radio', 'project_group', 0);
                        ?>
                    </table>
                </div>

                <h3>Patients in Cohort</h3>
                <div class="tableFixHead">
                    <table id="" class="display" style="width:100%">
                        <thead>
                        <tr>
                            <th>Select</th>
                            <th>UPN ID</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Race</th>
                            <th>Ethnicity</th>
                        </tr>
                        </thead>
                        <?php
                        display_cohort($db, $project_id, $cohort_members);
                        ?>
                    </table>
                </div>

            </td>
            <td style="width:5%">&nbsp</td>
        </tr>
    </table>
</form>

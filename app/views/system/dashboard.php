<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Dashboard</h2>
</header>

<!-- start: page -->
<div class="row">
    <div class="col-lg-12 col-xl-4">
        <div class="row">
            <div class="col-12">
                <div class="card card-modern">
                    <div class="card-body p-0">
                        <div class="widget-user-info">
                            <div class="widget-user-info-header">
                                <h2 class="font-weight-bold text-color-dark text-5">
                                    Hello, <?= $_SESSION['user']['givenname'] . " " . $_SESSION['user']['sn']; ?></h2>
                                <p class="mb-0"><?= $_SESSION['user']['group']['name']; ?></p>

                                <div class="widget-user-acrostic bg-primary">
                                    <span class="font-weight-bold"><?= $_SESSION['user']['givenname'][0] . $_SESSION['user']['sn'][0]; ?></span>
                                </div>
                            </div>
                            <div class="widget-user-info-body">
                                <div class="row">
                                    <div class="col">
                                        <a href="<?= $this->L("users/profile") ?>"
                                           class="btn btn-light btn-xl border font-weight-semibold text-color-dark text-3 mt-4">View Profile</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        if((isset($data['settings']['file_check']))&&($data['settings']['file_check']['active'])){
            include "widget_file_check.php";
        }
        ?>
    </div>
    <?php
    if((isset($data['settings']['graph']))&&($data['settings']['graph']['active'])){
        include "widget_graph.php";
    }
    ?>
</div>

<?php
    if((isset($data['settings']['latest_list']))&&($data['settings']['latest_list']['active'])){
        include "widget_latest_list.php";
    }
?>


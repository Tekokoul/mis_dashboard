<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">System Information</h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Host name: <b><?=$data['hostname'];?></b></span></li>
            <li><span>System: <b><?=$data['system'];?></b></span></li>
            <li><span>Kernel: <b><?=$data['kernel'];?></b></span></li>
            <li><span>Architecture: <b><?=$data['architecture'];?></b></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-md-6">
        <section class="card card-primary mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-code"></i>&nbsp;&nbsp;Installation</h2>
            </header>
            <div class="card-body">
                Project: <b><?=_PROJECT_NAME;?></b><br>
                Version: <b><?=_PROJECT_VERSION;?></b><br>
                URL: <b><?=_PROJECT_URL;?></b><br>
                Debug mode: <b><?= _DEBUG_MODE ? '<span class="text-danger">ON</span>' : '<span class="text-success">OFF</span>';?></b><br>
                Current commit Version: <b><?=$data['commit_version'];?></b><br><br>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card card-dark mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-database"></i>&nbsp;&nbsp;Software</h2>
            </header>
            <div class="card-body">
                OS Version: <b><?=$data['os_version'];?></b><br>
                Web Server Version: <b><?=$data['http_version'];?></b><br>
                MySQL Server Version: <b><?=$data['mysql_version'];?></b><br>
                PHP Version: <b><?=$data['php_version'];?></b><br>
<!--                TypeSense Version: <b>--><?php //=$data['typesense_version'];?><!--</b><br>-->
                git Version: <b><?=$data['git_version'];?></b><br><br>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card card-dark mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-microchip"></i>&nbsp;&nbsp;CPU</h2>
            </header>
            <div class="card-body">
                Number of CPUs/Cores: <b><?=$data['cpu_number'];?></b><br>
                Vendor: <b><?=$data['cpu_vendor'];?></b><br>
                Model: <b><?=$data['cpu_model'];?></b><br>
                CPU Frequency: <b><?=$data['cpu_frequency'];?></b><br>
                Cache Size: <b><?=$data['cpu_cache'];?></b><br><br>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card card-dark mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-memory"></i>&nbsp;&nbsp;Memory</h2>
            </header>
            <div class="card-body">
                Total Physical Memory: <b><?=$data['memory_total'];?></b><br>
                Used Physical Memory: <b><?=$data['memory_used'];?></b><br>
                Free Physical Memory: <b><?=$data['memory_free'];?></b><br>
                Total Swap Memory: <b><?=$data['swap_total'];?></b><br>
                Used Swap Memory: <b><?=$data['swap_used'];?></b><br>
                Free Swap Memory: <b><?=$data['swap_free'];?></b>
            </div>
        </section>
    </div>

    <div class="col-md-12">
        <section class="card card-dark mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-clock"></i>&nbsp;&nbsp;Server Time</h2>
            </header>
            <div class="card-body">
                Uptime: <b><?=$data['uptime'];?></b><br>
                Time: <b><?=$data['date'];?></b><br>
                Timezone: <b><?=$data['tz']?></b>
            </div>
        </section>
    </div>

    <div class="col-md-12">
        <section class="card card-light mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fab fa-php"></i>&nbsp;&nbsp;PHP</h2>
            </header>
            <div class="card-body">
                <div class="scrollable" data-plugin-scrollable style="height: 500px;">
                    <div class="scrollable-content">
                <div class="row">
                <?php
                foreach ($data['php'] as $key => $value){
                    print "<span class='col-lg-4 col-md-12'>".$key." = <b>".$value."</b></span>";
                }
                ?>
                </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
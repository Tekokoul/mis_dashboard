<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">System Information</h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Host: <b><?=display($data['hostname']);?></b></span></li>
            <li><span>System: <b><?=display($data['system'].' '.$data['kernel'].' '.$data['architecture']);?></b></span></li>
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
                Project: <b><?=display(_PROJECT_NAME);?></b><br>
                Version: <b><?=display(_PROJECT_VERSION);?></b><br>
                URL: <b><?=display(_PROJECT_URL);?></b><br>
                Debug mode: <b><?= _DEBUG_MODE ? '<span class="text-danger">ON</span>' : '<span class="text-success">OFF</span>';?></b><br>
                Build: <b><?=display($data['commit_version']);?></b>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card card-dark mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-database"></i>&nbsp;&nbsp;Software</h2>
            </header>
            <div class="card-body">
                OS family: <b><?=display($data['os_version']);?></b><br>
                Web server: <b><?=display($data['http_version']);?></b><br>
                Database: <b><?=display($data['mysql_version']);?></b><br>
                PHP: <b><?=display($data['php_version']);?></b>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card card-dark mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-microchip"></i>&nbsp;&nbsp;Runtime</h2>
            </header>
            <div class="card-body">
                Load average (1 / 5 / 15 min): <b><?=display($data['load_average']);?></b><br>
                PHP memory limit: <b><?=display($data['memory_limit']);?></b><br>
                Memory in use: <b><?=display($data['memory_used']);?></b>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card card-dark mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-clock"></i>&nbsp;&nbsp;Server time</h2>
            </header>
            <div class="card-body">
                Time: <b><?=display($data['date']);?></b><br>
                Timezone: <b><?=display($data['tz']);?></b>
            </div>
        </section>
    </div>
    <div class="col-md-12">
        <section class="card card-light mb-4">
            <header class="card-header">
                <h2 class="card-title"><i class="fab fa-php"></i>&nbsp;&nbsp;PHP settings that matter here</h2>
            </header>
            <div class="card-body">
                <div class="row">
                <?php foreach ($data['php'] as $key => $value): ?>
                    <span class="col-lg-4 col-md-12"><?=display($key);?> = <b><?=display((string)$value);?></b></span>
                <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>
</div>

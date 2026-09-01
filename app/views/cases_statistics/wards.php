<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Wards</h2>
</header>
<div class="row">
    <div class="col-lg-12 col-md-12">
        <section class="card">
            <div class="card-body">
                <div class="row">
                <div class="col-lg-6 col-md-12">
                    <h4>MOST ACTIVE WARDS</h4>
                    <div>
                        <canvas id="chart-0"></canvas>
                    </div>
                    <div>
                    <select class="select-1 form-control col-md-6" id="jform_profile_constituency" name="constituency" aria-invalid="false">
                        <?php foreach ($data['constituencies'] as $constituency) { ?>
                            <option value="<?= $constituency['id'] ?>"><?= $constituency['name'] ?></option>
                        <?php } ?>
                    </select>
                    <div class="form-group">
                        <a style="margin-top: 0px!important;" href="#" class="button button-3d button-rounded refresh-statistics-wards-active"><i class="icon-repeat"></i>Refresh</a>
                    </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12">
                    <h4>ISSUE FREQUENCY</h4>
                    <div>
                        <canvas id="chart-1"></canvas>
                    </div>
                    <div>
                        <select class="select-1 form-control col-md-6" id="jform_profile_constituency" name="constituency" aria-invalid="false">
                            <?php foreach ($data['constituencies'] as $constituency) { ?>
                                <option value="<?= $constituency['id'] ?>"><?= $constituency['name'] ?></option>
                            <?php } ?>
                        </select>
                        <div class="form-group">
                            <a style="margin-top: 0px!important;" href="#" class="button button-3d button-rounded refresh-statistics-wards-active"><i class="icon-repeat"></i>Refresh</a>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </section>
    </div>
</div>
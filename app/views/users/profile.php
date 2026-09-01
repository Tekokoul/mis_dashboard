<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Profile</h2>
</header>
<div class="row">
    <div class="col col-6">
        <section class="card card-primary">
            <header class="card-header">
                <h2 class="card-title">User Information</h2>
            </header>
            <div class="card-body">
                <form class="form-horizontal form-bordered" method="POST">
                    <?php
                    foreach ($data['user'] as $key => $value){
                        ?>
                        <div class="form-group row pb-4">
                            <label class="col-lg-3 control-label text-lg-end pt-1"><?=ucfirst($key);?></label>
                            <div class="col-lg-6">
                                <p class="form-control-static mb-0"><?=$value;?></p>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                    <div class="form-group row pb-4">
                        <label class="col-lg-3 control-label text-lg-end pt-1">Password:</label>
                        <div class="col-lg-6">
                            <p class="form-control-static mb-0"><a class="modal-password" data-id="" href="#passwordModal">Change password</a></p>
                        </div>
                    </div>
                </form>
            </div>
        </section>
        <section class="card card-dark">
            <header class="card-header">
                <h2 class="card-title">User logs (last 5 days)</h2>
            </header>
            <div class="card-body">
                <div class="scrollable" data-plugin-scrollable style="height: 600px;">
                    <div class="scrollable-content">
                        <table class="table table-striped table-no-more table-bordered mb-0">
                            <thead>
                            <tr>
                                <th style="width: 50%"><span class="font-weight-normal text-4">Action</span></th>
                                <th><span class="font-weight-normal text-4">Date</span></th>
                            </tr>
                            </thead>
                            <tbody class="log-viewer">
                            <?php
                            foreach ($data['logs'] as $log){
                                ?>
                                <tr>
                                    <td data-title="Action">
                                        <?=display($log['action']);?>
                                    </td>
                                    <td data-title="Date">
                                        <?=display_time($log['log_date']);?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <div class="col col-6">
        <form class="ecommerce-form action-buttons-fixed" action="<?=$this->L("users/settings_update")?>" method="post">
            <section class="card card-dark ">
                <header class="card-header">
                    <h2 class="card-title">Settings</h2>
                </header>
                <div class="card-body">
                    <div class="row">
                        <div>
                            <?php
                            $html = "";
                            foreach ($data['model']['common'] as $field=>$value) {
                                $prepopulated = $data['data'][$field] ?? "";
                                $html .= chooseElement($field, $value, $prepopulated);
                            }
                            print $html;
                            ?>
                        </div>
                    </div>
                </div>
                <footer class="card-footer text-end">
                    <button class="submit-button btn btn-primary btn-px-4 py-3 font-weight-semibold line-height-1">Submit </button>
                    <button type="reset" class="btn btn-default btn-px-4 py-3 line-height-1">Cancel</button>
                </footer>
            </section>
        </form>
    </div>
</div>
<div id="passwordModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
        <form id="passwordform" method="post" action="<?=$this->L("users/password_update")?>">
            <header class="card-header">
                <h2 class="card-title">Change password</h2>
            </header>
            <div class="card-body">
                <div class="modal-wrapper">
                    <div class="modal-text">
                        <div class="form-group row align-items-center pb-3">
                            <label class="col-lg-3 control-label text-lg-end mb-0">Password <span class='text-danger'>*</span> </label>
                            <div class="col-lg-8"><input type="password" class="form-control form-control-modern" id="pw_password_1" name="password" required></div>
                        </div>
                        <div class="form-group row align-items-center pb-3">
                            <label class="col-lg-3 control-label text-lg-end mb-0">Repeat Password <span class='text-danger'>*</span> </label>
                            <div class="col-lg-8"><input type="password" class="form-control form-control-modern" id="pw_password_2" name="password" required></div>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="card-footer">
                <div class="row">
                    <div class="col-md-12 text-end">
                        <button class="btn btn-primary modal-update">Update</button>
                        <button class="btn btn-default modal-dismiss">Cancel</button>
                    </div>
                </div>
            </footer>
        </form>
    </section>
</div>